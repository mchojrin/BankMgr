<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping as ORM;
use http\Exception\InvalidArgumentException;

/**
 * @ORM\Entity(repositoryClass="App\Repository\BancoRepository")
 * @ORM\Table(name="bank")
 */
class Bank
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $nombre;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Movimiento", mappedBy="bank")
     * @ORM\OrderBy({"fecha"="ASC"})
     */
    private $movimientos;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\FixedExpense", mappedBy="bank")
     */
    private $gastosFijos;

    /**
     * @ORM\OneToMany(targetEntity="SaldoBancario", mappedBy="bank", orphanRemoval=true, indexBy="fecha")
     * @ORM\OrderBy({"fecha"="ASC"})
     */
    private $saldos;

    /**
     * @ORM\Column(type="smallint", nullable=true)
     */
    private $codigo;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\BankXLSStructure", mappedBy="bank")
     */
    private $xlsStructure = null;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\ExtractoBancario", mappedBy="bank", orphanRemoval=true)
     */
    private $extractos;

    /**
     * @return mixed
     */
    public function getCodigo()
    {
        return $this->codigo;
    }

    /**
     * @param mixed $codigo
     * @return Bank
     */
    public function setCodigo($codigo)
    {
        $this->codigo = $codigo;
        return $this;
    }

    public function __construct()
    {
        $this->movimientos = new ArrayCollection();
        $this->gastosFijos = new ArrayCollection();
        $this->saldos = new ArrayCollection();
        $this->extractos = new ArrayCollection();
    }

    public function getId()
    {
        return $this->id;
    }

    public function getNombre(): ?string
    {
        return $this->nombre;
    }

    public function setNombre(string $nombre): self
    {
        $this->nombre = $nombre;

        return $this;
    }

    /**
     * @return Collection|Movimiento[]
     */
    public function getMovimientos(): Collection
    {
        return $this->movimientos;
    }

    public function addMovimiento(Movimiento $movimiento): self
    {
        if (!$this->movimientos->contains($movimiento)) {
            $this->movimientos[] = $movimiento;
            $movimiento->setBank($this);
        }

        return $this;
    }

    public function removeMovimiento(Movimiento $movimiento): self
    {
        if ($this->movimientos->contains($movimiento)) {
            $this->movimientos->removeElement($movimiento);
            // set the owning side to null (unless already changed)
            if ($movimiento->getBank() === $this) {
                $movimiento->setBank(null);
            }
        }

        return $this;
    }

    public function __toString()
    {
        return $this->getNombre();
    }

    /**
     * @return Collection|FixedExpense[]
     */
    public function getGastosFijos(): Collection
    {
        return $this->gastosFijos;
    }

    public function addGastoFijo(FixedExpense $gastosFijo): self
    {
        if (!$this->gastosFijos->contains($gastosFijo)) {
            $this->gastosFijos[] = $gastosFijo;
            $gastosFijo->setBank($this);
        }

        return $this;
    }

    public function removeGastoFijo(FixedExpense $gastosFijo): self
    {
        if ($this->gastosFijos->contains($gastosFijo)) {
            $this->gastosFijos->removeElement($gastosFijo);
            // set the owning side to null (unless already changed)
            if ($gastosFijo->getBank() === $this) {
                $gastosFijo->setBank(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|SaldoBancario[]
     */
    public function getSaldos(): Collection
    {
        return $this->saldos;
    }

    public function addSaldo(SaldoBancario $saldo): self
    {
        if (!$this->saldos->contains($saldo)) {
            $this->saldos[ $saldo->getFecha()->format('Y-m-d') ] = $saldo;
            $saldo->setBank($this);
        }

        return $this;
    }

    public function removeSaldo(SaldoBancario $saldo): self
    {
        if ($this->saldos->contains($saldo)) {
            $this->saldos->removeElement($saldo);
            // set the owning side to null (unless already changed)
            if ($saldo->getBank() === $this) {
                $saldo->setBank(null);
            }
        }

        return $this;
    }

    /**
     * @param \DateTimeInterface $date
     * @return SaldoBancario
     * @throws \Exception
     */
    public function getExpectedBalance(\DateTimeInterface $date) : SaldoBancario
    {
        if ( $date > (new \DateTimeImmutable() ) ) {

            throw new InvalidArgumentException( __METHOD__.' only works on past dates' );
        }

        $prevDate = (new \DateTimeImmutable($date->format('Y/m/d')))->sub(new \DateInterval('P1D'));
        $lastActualBalance = $this->getLastActualBalanceBefore($prevDate);

        if ( empty($lastActualBalance ) ) {
            $calculatedBalance = new SaldoBancario();
            $calculatedBalance
                ->setValor( 0 )
                ->setBank( $this )
                ->setFecha( $prevDate )
                ;
        } else {
            $calculatedBalance = clone $lastActualBalance;
        }

        foreach ( $this->getTransactionsBetween( $calculatedBalance->getFecha(), $date, true ) as $transaction ) {
            $calculatedBalance->setValor( $calculatedBalance->getValor() + $transaction->getImporte() );
        }

        $calculatedBalance->setFecha( $date );

        return $calculatedBalance;
    }

    /**
     * @param \DateTimeInterface $date
     * @return SaldoBancario|null
     * This method will simply search into the collection for a balance loaded by the user
     */
    public function getPastActualBalance( \DateTimeInterface $date ) :? SaldoBancario
    {
        if ( $date > (new \DateTimeImmutable() ) ) {

            throw new InvalidArgumentException( __METHOD__.' only works on past dates' );
        }

        foreach ($this->getSaldos() as $saldoBancario ) {
            if ( $date == $saldoBancario->getFecha() ) {

                return $saldoBancario;
            }
        }

        return null;
    }

    /**
     * @param \DateTimeInterface|null $date
     * @return SaldoBancario
     * @deprecated use more specific methods instead
     */
    public function getBalance(\DateTimeImmutable $date = null ): ?SaldoBancario
    {
        $balances = $this->getSaldos();

        if ( $balances->isEmpty() ) {

            return null;
        } elseif ( empty($date) ) {

            return $balances->last();
        }

        $oneDay = new \DateInterval('P1D');

        while ( !$balances->containsKey( $date->format('Y-m-d') ) && $balances->first()->getFecha() < $date ) {
            $date = $date->sub( $oneDay );
        }

        return $balances->containsKey( $date->format('Y-m-d') ) ? $balances->get( $date->format('Y-m-d') ) : null;
    }

    /**
     * @param float $value
     * @return SaldoBancario
     * @throws \Exception
     */
    public function createBalance( \DateTimeInterface $date, float $value )
    {
        $newBalance = new SaldoBancario();

        $newBalance
            ->setBank( $this )
            ->setFecha( $date )
            ->setValor( $value )
            ;
        
        return $newBalance;
    }

    /**
     * @param \DateTimeInterface $desiredDate
     * @return SaldoBancario|null
     * @throws \Exception
     */
    public function getLastActualBalanceBefore(\DateTimeInterface $desiredDate ) :? SaldoBancario
    {
        $balances = $this->getSaldos();

        $lastKnownBalance = null;

        $balanceIterator = $balances->getIterator();

        while ( $balanceIterator->valid() ) {
            $currentBalance = $balanceIterator->current();
            if ( $currentBalance->getFecha() > $desiredDate ) {

                break;
            } else {
                $lastKnownBalance = $currentBalance;
                $balanceIterator->next();
            }
        }

        return $lastKnownBalance;
    }

    /**
     * @param \DateTimeInterface $desiredBalanceDate
     * @return SaldoBancario
     */
    public function getFutureBalance(\DateTimeInterface $desiredBalanceDate): SaldoBancario
    {
        $today = new \DateTimeImmutable( 'today 00:00:00' );
        if ( $desiredBalanceDate < $today ) {

            throw new InvalidArgumentException( __METHOD__.' only works on future dates' );
        }

        $lastKnownBalance = $this->getLastActualBalanceBefore($desiredBalanceDate);
        $currentBalanceValue = $lastKnownBalance ? $lastKnownBalance->getValor() : 0;

        if ( $lastKnownBalance ) {
            foreach ($this->getTransactionsBetween( $lastKnownBalance->getFecha(), $desiredBalanceDate, false ) as $movimiento) {
                $currentBalanceValue += $movimiento->getImporte();
            }
        }

        return $this->createBalance( $desiredBalanceDate, $currentBalanceValue );
    }

    /**
     * @param \DateTimeInterface $fechaInicio
     * @param \DateTimeInterface $fechaFin
     * @return Collection
     */
    public function getTransactionsBetween(\DateTimeInterface $fechaInicio, \DateTimeInterface $fechaFin, bool $concretados = null ) : Collection
    {
        $criteria = Criteria::create()
            ->andWhere(
                Criteria::expr()
                    ->gt('fecha', $fechaInicio)
            )
            ->andWhere(
                Criteria::expr()
                    ->lte('fecha', $fechaFin)
            );

        return $this
            ->getMovimientos()
            ->matching( $criteria )
            ->filter( function( Movimiento $m ) use ( $concretados ) {
                if ( $concretados === true ) {

                    return $m->isConcretado();
                } elseif ( $concretados === false ) {

                    return !$m->isConcretado();
                } else {

                    return true;
                }
            });
    }

    /**
     * @return BankXLSStructure
     */
    public function getXLSStructure(): ?BankXLSStructure
    {
        return $this->xlsStructure;
    }

    /**
     * @param BankXLSStructure|null $xlsStructure
     * @return Bank
     */
    public function setXLSStructure(BankXLSStructure $xlsStructure = null )
    {
        $this->xlsStructure = $xlsStructure;

        return $this;
    }

    /**
     * @param int|null $limit
     *
     * @return Collection
     */
    public function getDebitosProyectados( int $limit = null ): Collection
    {
        $criteria = Criteria::create()
            ->andWhere(Criteria::expr()->isNull('witnessId'))
            ->andWhere(Criteria::expr()->lt('importe', 0))
            ->orderBy(['fecha' => 'ASC'])
        ;

        if ( $limit ) {
            $criteria->setMaxResults( $limit );
        }

        return $this->movimientos->matching( $criteria );
    }

    /**
     * @param int|null $limit
     * @return Collection
     */
    public function getCreditosProyectados( int $limit = null ) : Collection
    {
        $criteria = Criteria::create()
            ->andWhere(Criteria::expr()->isNull('witnessId'))
            ->andWhere(Criteria::expr()->gt('importe', 0))
            ->orderBy(['fecha' => 'ASC'])
        ;

        if ( $limit ) {
            $criteria->setMaxResults( $limit );
        }

        return $this->movimientos->matching( $criteria );
    }

    /**
     * @return Collection|ExtractoBancario[]
     */
    public function getExtractos(): Collection
    {
        return $this->extractos;
    }

    public function addExtracto(ExtractoBancario $extracto): self
    {
        if (!$this->extractos->contains($extracto)) {
            $this->extractos[] = $extracto;
            $extracto->setBank($this);
        }

        return $this;
    }

    public function removeExtracto(ExtractoBancario $extracto): self
    {
        if ($this->extractos->contains($extracto)) {
            $this->extractos->removeElement($extracto);
            // set the owning side to null (unless already changed)
            if ($extracto->getBank() === $this) {
                $extracto->setBank(null);
            }
        }

        return $this;
    }

    /**
     * @param float $amount
     * @param \DateTimeInterface $date
     * @param string $concept
     * @return Movimiento
     */
    public function createCredit( float $amount, \DateTimeInterface $date, string $concept )
    {
        $credit = new Movimiento();
        $credit
            ->setImporte( abs( $amount ) )
            ->setFecha( $date )
            ->setConcepto( $concept )
        ;

        $this->addMovimiento( $credit );

        return $credit;
    }
}
