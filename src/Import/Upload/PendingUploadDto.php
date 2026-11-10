<?php

declare(strict_types=1);

namespace App\Import\Upload;

use App\Import\ImportRangeDto;

/**
 * An uploaded extract that waits for the confirmation of the administrator.
 */
final readonly class PendingUploadDto
{
    /**
     * @param string $token    the random key of the upload in the session and in the URL
     * @param string $path     the file in the upload directory, with a random name
     * @param string $fileName the name of the file on the computer of the administrator
     */
    public function __construct(
        public string $token,
        public string $path,
        public string $fileName,
        public ImportRangeDto $range,
        public string $mappingName,
        public \DateTimeImmutable $uploadedAt,
    ) {
    }
}
