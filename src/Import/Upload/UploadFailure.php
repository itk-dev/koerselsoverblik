<?php

declare(strict_types=1);

namespace App\Import\Upload;

use App\Import\Extract\InvalidExtractException;
use App\Import\Extract\MissingColumnsException;
use App\Import\InvalidImportRangeException;
use App\Import\NoStopRowsException;
use App\Import\RowIdConflictException;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * The Danish message of a failed upload, for the single upload and the bulk upload. The message
 * has no value from the extract.
 */
final class UploadFailure
{
    /**
     * @param int $maxDays the value of APP_DRIVING_DATA_MAX_DAYS
     */
    public static function message(InvalidExtractException|NoStopRowsException|RowIdConflictException|InvalidImportRangeException $exception, int $maxDays): TranslatableMessage
    {
        return match (true) {
            $exception instanceof MissingColumnsException => new TranslatableMessage('upload.missing_columns', ['columns' => implode(', ', $exception->columns)]),
            $exception instanceof InvalidExtractException => new TranslatableMessage('upload.problem.'.$exception->problem->value, ['limit' => $exception->limit ?? 0]),
            $exception instanceof NoStopRowsException => new TranslatableMessage('upload.no_stop_rows'),
            $exception instanceof InvalidImportRangeException => new TranslatableMessage('upload.bulk.too_many_days', ['limit' => $maxDays]),
            default => new TranslatableMessage('upload.row_id_conflict'),
        };
    }
}
