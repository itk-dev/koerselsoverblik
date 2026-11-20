<?php

declare(strict_types=1);

namespace App\Admin;

use App\Import\ImportRangeDto;
use App\Import\Upload\DateRangeInput;
use App\Import\Upload\ValidImportRange;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The data of the range form on the driving data page: the date range, and a route name that
 * limits the stop rows.
 */
#[ValidImportRange]
final class RowsQueryDto implements DateRangeInput
{
    #[Assert\NotNull(message: 'upload.date_required')]
    public ?\DateTimeImmutable $firstDate = null;

    #[Assert\NotNull(message: 'upload.date_required')]
    public ?\DateTimeImmutable $lastDate = null;

    public ?string $route = null;

    public static function forDate(\DateTimeImmutable $date): self
    {
        $query = new self();
        $query->firstDate = $date;
        $query->lastDate = $date;

        return $query;
    }

    /**
     * Gives the date range of a valid form.
     *
     * @param int $maxDays the value of APP_DRIVING_DATA_MAX_DAYS
     *
     * @throws \LogicException if a date is missing
     */
    public function range(int $maxDays): ImportRangeDto
    {
        if (null === $this->firstDate || null === $this->lastDate) {
            throw new \LogicException('The query has no date range.');
        }

        return ImportRangeDto::create($this->firstDate, $this->lastDate, $maxDays);
    }
}
