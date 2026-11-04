<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * A route (vognløb) from the extract, for example "Ø4-VB-01". The import of driving data creates
 * it. It is not created by hand.
 */
#[ORM\Entity]
class Route
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * @param string $sourceKey the route name in the extract
     */
    public function __construct(
        #[ORM\Column(length: 255, unique: true)]
        private string $sourceKey,
        #[ORM\ManyToOne(inversedBy: 'routes')]
        #[ORM\JoinColumn(nullable: false)]
        private SubAgreement $subAgreement,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSourceKey(): string
    {
        return $this->sourceKey;
    }

    public function getSubAgreement(): SubAgreement
    {
        return $this->subAgreement;
    }
}
