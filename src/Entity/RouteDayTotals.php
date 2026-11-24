<?php

declare(strict_types=1);

namespace App\Entity;

use App\Report\DayTotalsDto;
use App\Repository\RouteDayTotalsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * The counters of the figures for one route and one day. They are derived from the stop rows and
 * the route runs, and `RouteDayTotalsBuilder` builds them again for each import. The table has
 * all routes, also the routes of an ignored sub-agreement, because the queries apply the filters.
 * See "Data model" in docs/architecture.md.
 */
#[ORM\Entity(repositoryClass: RouteDayTotalsRepository::class)]
#[ORM\UniqueConstraint(columns: ['route_id', 'service_date'])]
#[ORM\Index(columns: ['service_date'])]
class RouteDayTotals
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private int $pickupOnTime;

    #[ORM\Column]
    private int $pickupMeasured;

    #[ORM\Column]
    private int $dropOffOnTime;

    #[ORM\Column]
    private int $dropOffMeasured;

    #[ORM\Column]
    private int $firstPickupOnTime;

    #[ORM\Column]
    private int $firstPickupMeasured;

    #[ORM\Column]
    private int $lastDropOnTime;

    #[ORM\Column]
    private int $lastDropMeasured;

    #[ORM\Column]
    private int $lastDropUpTo15;

    #[ORM\Column]
    private int $lastDropUpTo30;

    #[ORM\Column]
    private int $lastDropOver30;

    #[ORM\Column]
    private int $trips;

    #[ORM\Column]
    private int $completedTrips;

    #[ORM\Column]
    private int $cancelledTrips;

    #[ORM\Column]
    private int $pendingTrips;

    #[ORM\Column]
    private int $runs;

    #[ORM\Column]
    private int $soloRuns;

    #[ORM\Column]
    private int $notCancelledRuns;

    #[ORM\Column]
    private int $cancelledInTimeTrips;

    #[ORM\Column]
    private int $cancelledLateTrips;

    /**
     * @param int $toleranceMinutes the value of APP_PUNCTUALITY_TOLERANCE_MINUTES when the counters were built
     */
    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false)]
        private Route $route,
        #[ORM\Column(type: Types::DATE_IMMUTABLE)]
        private \DateTimeImmutable $serviceDate,
        #[ORM\Column]
        private int $toleranceMinutes,
        DayTotalsDto $totals,
    ) {
        $this->pickupOnTime = $totals->pickupOnTime;
        $this->pickupMeasured = $totals->pickupMeasured;
        $this->dropOffOnTime = $totals->dropOffOnTime;
        $this->dropOffMeasured = $totals->dropOffMeasured;
        $this->firstPickupOnTime = $totals->firstPickupOnTime;
        $this->firstPickupMeasured = $totals->firstPickupMeasured;
        $this->lastDropOnTime = $totals->lastDropOnTime;
        $this->lastDropMeasured = $totals->lastDropMeasured;
        $this->lastDropUpTo15 = $totals->lastDropUpTo15;
        $this->lastDropUpTo30 = $totals->lastDropUpTo30;
        $this->lastDropOver30 = $totals->lastDropOver30;
        $this->trips = $totals->trips;
        $this->completedTrips = $totals->completedTrips;
        $this->cancelledTrips = $totals->cancelledTrips;
        $this->pendingTrips = $totals->pendingTrips;
        $this->runs = $totals->runs;
        $this->soloRuns = $totals->soloRuns;
        $this->notCancelledRuns = $totals->notCancelledRuns;
        $this->cancelledInTimeTrips = $totals->cancelledInTimeTrips;
        $this->cancelledLateTrips = $totals->cancelledLateTrips;
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

    public function getToleranceMinutes(): int
    {
        return $this->toleranceMinutes;
    }

    public function getTotals(): DayTotalsDto
    {
        return new DayTotalsDto(
            $this->pickupOnTime,
            $this->pickupMeasured,
            $this->dropOffOnTime,
            $this->dropOffMeasured,
            $this->firstPickupOnTime,
            $this->firstPickupMeasured,
            $this->lastDropOnTime,
            $this->lastDropMeasured,
            $this->lastDropUpTo15,
            $this->lastDropUpTo30,
            $this->lastDropOver30,
            $this->trips,
            $this->completedTrips,
            $this->cancelledTrips,
            $this->pendingTrips,
            $this->runs,
            $this->soloRuns,
            $this->notCancelledRuns,
            $this->cancelledInTimeTrips,
            $this->cancelledLateTrips,
        );
    }
}
