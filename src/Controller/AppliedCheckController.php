<?php
/**
 * Created by PhpStorm.
 * User: mauro
 * Date: 12/28/18
 * Time: 1:31 PM
 */

namespace App\Controller;

use App\Entity\AppliedCheck;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class AppliedCheckController extends AdminController
{
    /**
     * @param Request $request
     * @Route(name="import_applied_checks", path="/import/appliedChecks")
     */
    public function import(Request $request)
    {
        $formBuilder = $this->createFormBuilder()
            ->setAttribute('class', 'form-vertical new-form');

        $formBuilder->add(
            'reportFile',
            FileType::class,
            [
                'label' => 'Informe de cheques aplicados ',
                'required' => true,
            ]
        )->add(
            'Import',
            SubmitType::class,
            [
                'attr' => [
                    'class' => 'btn btn-primary action-save',
                ],
                'label' => 'Importar',
            ]
        )->getForm();

        $form = $formBuilder->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();

            foreach ($form->getData() as $name => $item) {
                if (!is_null($item) && $item->getType() == 'file' ) {
                    if ( in_array($item->getMimeType(), ['application/wps-office.xls', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel', 'application/octet-stream'])) {
                        $fileName = 'AppliedChecks_' . (new \DateTimeImmutable())->format('d-m-y') . '.' . $item->guessExtension();
                        $item->move($this->getParameter('reports_path'), $fileName);

                        $lines = $this->getExcelReportProcessor()->getAppliedChecks(
                            IOFactory::load($this->getParameter('reports_path') . DIRECTORY_SEPARATOR . $fileName)
                        );

                        foreach ($lines as $k => $line) {
                            $appliedCheck = new AppliedCheck();
                            $appliedCheck
                                ->setAmount($line['amount'])
                                ->setDate($line['date'])
                                ->setType($line['type'])
                                ->setDestination($line['destination'])
                                ->setIssuer($line['issuer'])
                                ->setSourceBank($line['sourceBank'])
                                ->setNumber($line['number']);

                            $em->persist($appliedCheck);
                        }

                        $em->flush();

                        $this->addFlash(
                            'success',
                            'Cheques aplicados importados'
                        );
                    } else {
                        $this->addFlash(
                            'error',
                            'El informe de Cheques aplicados no tiene el formato correcto'
                        );
                    }
                }
            }
        }

        return $this->render(
            'admin/import_excel_reports.html.twig',
            [
                'form' => $form->createView(),
                'reportName' => 'checks.applied',
            ]
        );
    }

    /**
     * @param Request $request
     * @Route(path="/processedAppliedChecks", name="process_applied_checks")
     */

    public function process(Request $request)
    {
        /*
         * @todo This could be much more intelligent since the destination of the check is part of the
         * Excel file...
         */

        $bankRepository = $this->getDoctrine()->getRepository('App:Bank');
        $banks = $bankRepository->findAll();
        $destinationBanks = [];
        foreach ( $banks as $bank ) {
            $destinationBanks[ $bank->getNombre() ] = 'bank_'.$bank->getId();
        }

        $transactionRepository = $this->getDoctrine()->getRepository('App:Movimiento');
        $debits = $transactionRepository->findNonCheckProjectedDebits();
        $destinationDebits = [];
        foreach ( $debits as $debit ) {
            $destinationDebits[ $debit->getConcepto() . ' ('. $debit->getImporte().')' ] = 'debit_'.$debit->getId();
        }

        $destinations = [
            'Banks' => $destinationBanks,
            'Debitos proyectados' => $destinationDebits,
            'checks.received.destination.other' => [
                'checks.received.destination.applied_outside' => 'outside',
            ]
        ];

        $appliedCheckRepository = $this
            ->getDoctrine()
            ->getRepository('App:AppliedCheck');

        $nonProcessed = $appliedCheckRepository
            ->findNonProcessed()
        ;

        $formBuilder = $this->createFormBuilder();

        foreach ($nonProcessed as $k => $check) {
            $formBuilder
                ->add(
                    'check_' . $k,
                    ChoiceType::class,
                    [
                        'choices' => $destinations,
                        'required' => false,
                    ]
                )
            ;
        }

        $formBuilder->add(
            'apply',
            SubmitType::class,
            [
                'attr' => [
                    'class' => 'btn btn-primary',
                ]
            ]
        );

        $form = $formBuilder->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            foreach ($nonProcessed as $k => $check) {
                $destination = $form['check_'.$k]->getData();

                if ( empty( $destination ) ) {

                    continue;
                }
                if ( $destination == 'outside' ) {
                    $check->applyOutside();
                    $em->persist($check);
                } else {
                    $parts = preg_split('/_/', $destination );

                    if ( $parts[0] == 'bank' ) {
                        $recipientBank = $bankRepository->find( $parts[1] );
                        $childCredit = $recipientBank->createCredit(
                            $check->getAmount(),
                            $check->getCreditDate(),
                            $this->trans('check.acreditation') . ' ' . $check->getNumber()
                        );

                        $check->setChildCredit( $childCredit );
                        $em->persist($check);
                    } elseif ( $parts[0] == 'debit') {
                        if ( $projectedDebit = $transactionRepository->find( $parts[1] ) ) {
                            $check->applyToDebit( $projectedDebit );
                            $projectedDebit->setWitness( $check );
                            $em->persist( $projectedDebit );
                        }
                    }
                }
            }

            $em->flush();

            return $this->redirectToRoute('process_applied_checks');
        }

        return $this->render(
            'admin/process_applied_checks.html.twig',
            [
                'form' => $form->createView(),
                'nonProcessed' => $nonProcessed,
                'appliedOutside' => $appliedCheckRepository->findAppliedOutside(),
            ]
        );
    }

    /**
     * @Route(name="received_check_unApplyOutside", path="/checks/received/{id}/unApplyOutside")
     * @param ChequeEmitido $appliedCheck
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function unApplyOutisde( AppliedCheck $appliedCheck )
    {
        $appliedCheck->unApplyOutside();

        $entityManager = $this
            ->getDoctrine()
            ->getManager();

        $entityManager
            ->persist($appliedCheck)
        ;

        $entityManager->flush();

        return $this->redirectToRoute('process_applied_checks');
    }
}