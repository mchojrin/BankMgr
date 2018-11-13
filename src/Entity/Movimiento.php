<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\MovimientoRepository")
 */
class Movimiento
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
    private $concepto;

    /**
     * @ORM\Column(type="date")
     */
    private $fecha;

    /**
     * @ORM\Column(type="decimal", precision=10, scale=2)
     */
    private $importe;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Banco", inversedBy="movimientos")
     * @ORM\JoinColumn(nullable=false)
     */
    private $banco;

    /**
     * @ORM\Column(type="boolean")
     */
    private $concretado = false;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\GastoFijo", inversedBy="movimientos")
     */
    private $clonDe;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\RenglonExtracto", mappedBy="movimiento", cascade={"persist", "remove"})
     */
    private $renglonExtracto;

    public function getId()
    {
        return $this->id;
    }

    public function getConcepto(): ?string
    {
        return $this->concepto;
    }

    public function setConcepto(string $concepto): self
    {
        $this->concepto = $concepto;

        return $this;
    }

    public function getFecha(): ?\DateTimeInterface
    {
        return $this->fecha;
    }

    public function setFecha(\DateTimeInterface $fecha): self
    {
        $this->fecha = $fecha;

        return $this;
    }

    public function getImporte()
    {
        return $this->importe;
    }

    public function setImporte($importe): self
    {
        $this->importe = $importe;

        return $this;
    }

    public function getBanco(): ?Banco
    {
        return $this->banco;
    }

    public function setBanco(?Banco $banco): self
    {
        $this->banco = $banco;

        return $this;
    }

    public function __toString()
    {
        return $this->getFecha()->format('d/m/Y').': '.($this->importe < 0 ? '-' : '').'$'.abs($this->importe).' ('.$this->getConcepto().')';
    }

    /**
     * @return bool
     */
    public function getConcretado()
    {
        return $this->concretado;
    }

    /**
     * @param bool $concretado
     * @return Movimiento
     */
    public function setConcretado(bool $concretado)
    {
        $this->concretado = $concretado;
        return $this;
    }

    public function __construct()
    {
        $this->setFecha( new \DateTime() );
    }

    public function getClonDe(): ?GastoFijo
    {
        return $this->clonDe;
    }

    public function setClonDe(?GastoFijo $clonDe): self
    {
        $this->clonDe = $clonDe;

        return $this;
    }

    public function getRenglonExtracto(): ?RenglonExtracto
    {
        return $this->renglonExtracto;
    }

    public function setRenglonExtracto(?RenglonExtracto $renglonExtracto): self
    {
        $this->renglonExtracto = $renglonExtracto;

        // set (or unset) the owning side of the relation if necessary
        $newMovimiento = $renglonExtracto === null ? null : $this;
        if ($newMovimiento !== $renglonExtracto->getMovimiento()) {
            $renglonExtracto->setMovimiento($newMovimiento);
        }

        return $this;
    }
}
