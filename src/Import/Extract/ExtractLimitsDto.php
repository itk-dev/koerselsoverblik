<?php

namespace App\Import\Extract;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The limits for one extract. They keep the memory use and the time of an import low. See
 * "Data protection" in docs/architecture.md.
 */
final readonly class ExtractLimitsDto
{
    /**
     * @param int $maxFileSize         the maximum file size in bytes
     * @param int $maxUncompressedSize the maximum total size in bytes of the files in the archive
     * @param int $maxRows             the maximum row number in a sheet
     * @param int $maxColumns          the maximum number of columns in a row
     */
    public function __construct(
        #[Autowire(param: 'app.driving_data_max_file_size')]
        public int $maxFileSize,
        #[Autowire(param: 'app.driving_data_max_uncompressed_size')]
        public int $maxUncompressedSize,
        #[Autowire(param: 'app.driving_data_max_rows')]
        public int $maxRows,
        #[Autowire(param: 'app.driving_data_max_columns')]
        public int $maxColumns,
    ) {
    }
}
