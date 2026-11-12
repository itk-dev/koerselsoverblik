<?php

declare(strict_types=1);

namespace App\Import;

use App\Entity\ImportBatch;
use App\Entity\RejectedStopRow;
use App\Entity\Route;
use App\Entity\StopRow;
use App\Entity\SubAgreement;
use App\Import\Extract\RejectedRowDto;
use App\Import\Extract\RejectionGroups;
use App\Import\Extract\RejectionReason;
use App\Import\Extract\StopRowDto;
use App\Import\Mapping\MappingDto;
use App\Repository\SourceRowRepository;
use App\Repository\SubAgreementRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Replaces the stop rows of a date range with the rows of a file (ADR 003).
 *
 * Each extract holds whole days and all routes of a day. Thus the replacer deletes the rows of all
 * mappings in the range. Then each date in the range holds exactly the rows of the file. The
 * caller runs the replacement in a transaction. See "Pipeline" in docs/architecture.md.
 */
final readonly class StopRowReplacer
{
    /**
     * The number of rows to write before the entity manager forgets them, so that the memory use
     * does not grow with the file.
     */
    private const int GROUP_SIZE = 500;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private SubAgreementRepository $subAgreements,
        private SourceRowRepository $sourceRows,
    ) {
    }

    /**
     * The parse of the rows starts after the delete. A parse error then also undoes the delete,
     * when the caller rolls back the transaction. The replacer does not check the range or the row
     * keys again, because the parser rejects the rows outside the range and the duplicate keys.
     *
     * @param iterable<StopRowDto|RejectedRowDto> $rows  the rows of the file
     * @param ImportBatch                         $batch a batch that the entity manager manages. The first flush inserts it before the rows that refer to it.
     */
    public function replace(ImportRangeDto $range, MappingDto $mapping, iterable $rows, ImportBatch $batch): ImportResultDto
    {
        $deletedRows = $this->deleteRange($range);

        $rejections = new RejectionGroups();
        $warnings = new RejectionGroups();
        // MariaDB compares text with no regard to case, so the maps use the names in lower case.
        // Then two names that differ only in case are one route, also before the flush.
        /** @var array<string, Route> $routes the routes of the file, keyed by the route name in lower case */
        $routes = [];
        /** @var array<string, SubAgreement> $subAgreements the sub-agreements of the file, keyed by the reference in lower case */
        $subAgreements = [];
        /** @var array<string, true> $dates the dates with rows, as Y-m-d */
        $dates = [];
        $insertedRows = 0;
        /** @var list<StopRow|RejectedStopRow> $written the rows since the last flush */
        $written = [];

        foreach ($rows as $row) {
            if ($row instanceof RejectedRowDto) {
                $rejections->add($row->field, $row->reason, $row->rowNumber);
                // The upload for the date of a row outside the range imports it.
                if (RejectionReason::OutsideRange !== $row->reason) {
                    $written[] = new RejectedStopRow($row, $batch);
                }
            } else {
                foreach ($row->warnings as $warning) {
                    $warnings->add($warning->field, $warning->reason, $row->rowNumber);
                }
                $route = $routes[mb_strtolower($row->route)] ??= $this->route($row->route, $subAgreements);
                $written[] = new StopRow($row, $mapping->name, $route, $batch);
                $dates[$row->serviceDate->format('Y-m-d')] = true;
                ++$insertedRows;
            }

            if (\count($written) >= self::GROUP_SIZE) {
                $this->write($written);
                $written = [];
            }
        }
        $this->write($written);

        return new ImportResultDto(
            deletedRows: $deletedRows,
            insertedRows: $insertedRows,
            rejectedRows: $rejections->total(),
            rejections: $rejections->groups(),
            warningCount: $warnings->total(),
            warnings: $warnings->groups(),
            emptyDates: array_values(array_diff($range->dates(), array_keys($dates))),
        );
    }

    /**
     * Deletes the stop rows and the rejected rows of the range. A rejected row with no date belongs
     * to the range when the whole range of its import is in the range.
     *
     * Thus a rejected row with no date of a larger import stays until an import covers the whole
     * range of that import. Such rows can collect, but the date of a row is not known, so a smaller
     * import must not delete it.
     *
     * A DQL delete does not cascade. Thus the replacer deletes the copies of the deleted rows after
     * the rows.
     *
     * @return int the number of deleted stop rows
     */
    private function deleteRange(ImportRangeDto $range): int
    {
        $parameters = [
            'first' => [$range->firstDate, Types::DATE_IMMUTABLE],
            'last' => [$range->lastDate, Types::DATE_IMMUTABLE],
        ];

        $deleteRejected = $this->entityManager->createQuery(
            'DELETE FROM '.RejectedStopRow::class.' rejected'
            .' WHERE rejected.serviceDate BETWEEN :first AND :last'
            .' OR (rejected.serviceDate IS NULL AND rejected.importBatch IN ('
            .'SELECT batch.id FROM '.ImportBatch::class.' batch WHERE batch.firstDate >= :first AND batch.lastDate <= :last))',
        );
        $deleteRows = $this->entityManager->createQuery(
            'DELETE FROM '.StopRow::class.' row WHERE row.serviceDate BETWEEN :first AND :last',
        );
        foreach ($parameters as $name => [$value, $type]) {
            $deleteRejected->setParameter($name, $value, $type);
            $deleteRows->setParameter($name, $value, $type);
        }
        $deleteRejected->execute();

        /** @var int $deleted a DQL delete gives the number of deleted rows */
        $deleted = $deleteRows->execute();
        $this->sourceRows->deleteOrphans();

        return $deleted;
    }

    /**
     * Finds the route by its name, or creates it. A new route finds or creates its sub-agreement.
     * A new sub-agreement has no supplier, so its routes count as "Unknown".
     *
     * @param array<string, SubAgreement> $subAgreements the sub-agreements of the file, keyed by the reference in lower case. A new sub-agreement is not saved yet, so the repository cannot find it.
     */
    private function route(string $name, array &$subAgreements): Route
    {
        $route = $this->entityManager->getRepository(Route::class)->findOneBy(['sourceKey' => $name]);
        if (null !== $route) {
            return $route;
        }

        $reference = SubAgreementReference::fromRouteName($name);
        $key = mb_strtolower($reference);
        $subAgreement = $subAgreements[$key] ?? $this->subAgreements->findOneBy(['reference' => $reference]);
        if (null === $subAgreement) {
            $subAgreement = new SubAgreement()->setReference($reference);
            $this->entityManager->persist($subAgreement);
        }
        $subAgreements[$key] = $subAgreement;

        $route = new Route($name, $subAgreement);
        $this->entityManager->persist($route);

        return $route;
    }

    /**
     * Saves the rows, and then the entity manager forgets them. The routes and the batch stay
     * managed, so that the next rows can refer to them.
     *
     * @param list<StopRow|RejectedStopRow> $rows
     */
    private function write(array $rows): void
    {
        foreach ($rows as $row) {
            $this->entityManager->persist($row);
        }
        $this->entityManager->flush();
        foreach ($rows as $row) {
            $this->entityManager->detach($row);
        }
    }
}
