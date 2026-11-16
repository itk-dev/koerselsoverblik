<?php

declare(strict_types=1);

namespace App\Entity;

use App\Report\PunctualityCountDto;
use App\Repository\LocationDayTotalsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * The measured stops of one location, route and day: the completed pickups and drop-offs with a
 * delay. They are derived from the stop rows, and `LocationDayTotalsBuilder` builds them again for
 * each import. The table has all routes, also the routes of an ignored sub-agreement, because the
 * queries apply the filters. It has no person ids. See "Data model" in docs/architecture.md.
 */
#[ORM\Entity(repositoryClass: LocationDayTotalsRepository::class)]
#[ORM\UniqueConstraint(columns: ['route_id', 'service_date', 'location_name'])]
#[ORM\Index(columns: ['service_date'])]
class LocationDayTotals
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private int $onTime;

    #[ORM\Column]
    private int $measured;

    /**
     * @param string $locationName     the institution of the stops, never shown with a person id
     * @param int    $toleranceMinutes the value of APP_PUNCTUALITY_TOLERANCE_MINUTES when the counters were built
     */
    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false)]
        private Route $route,
        #[ORM\Column(type: Types::DATE_IMMUTABLE)]
        private \DateTimeImmutable $serviceDate,
        #[ORM\Column(length: 255)]
        private string $locationName,
        #[ORM\Column]
        private int $toleranceMinutes,
        PunctualityCountDto $stops,
    ) {
        $this->onTime = $stops->onTime;
        $this->measured = $stops->measured;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRoute(): Route
    {
        return $this->route;
    }

    public function getServiceDate(): \DateTimeImmutable
    {
        return $this->serviceDate;
    }

    public function getLocationName(): string
    {
        return $this->locationName;
    }

    public function getToleranceMinutes(): int
    {
        return $this->toleranceMinutes;
    }

    public function getStops(): PunctualityCountDto
    {
        return new PunctualityCountDto($this->onTime, $this->measured);
    }
}
