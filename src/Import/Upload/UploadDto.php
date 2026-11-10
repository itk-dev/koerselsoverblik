<?php

declare(strict_types=1);

namespace App\Import\Upload;

use App\Import\ImportRangeDto;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The data of the upload form: the extract and the date range of the export. The form type adds
 * the constraints of the file, because the size limit is a setting.
 */
#[ValidImportRange]
final class UploadDto
{
    #[Assert\NotNull(message: 'upload.file_required')]
    public ?UploadedFile $file = null;

    #[Assert\NotNull(message: 'upload.date_required')]
    public ?\DateTimeImmutable $firstDate = null;

    #[Assert\NotNull(message: 'upload.date_required')]
    public ?\DateTimeImmutable $lastDate = null;

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
            throw new \LogicException('The upload has no date range.');
        }

        return ImportRangeDto::create($this->firstDate, $this->lastDate, $maxDays);
    }
}
