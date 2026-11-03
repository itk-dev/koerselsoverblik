<?php

namespace App\Import\Extract;

/**
 * Checks an extract before OpenSpout opens it.
 *
 * An xlsx file is a zip archive of XML files. OpenSpout does not check the size of a row, and it
 * ignores a DOCTYPE. Thus the inspector reads each XML file in the archive one node at a time. It
 * uses the rules of OpenSpout to find the row number and the column of each row and cell. It does
 * not refer to the names of the files, because the workbook can give other names to them.
 */
final readonly class ExtractInspector
{
    private const string LETTERS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    /**
     * @throws InvalidExtractException if the extract is above a limit, is not an xlsx file or has a DOCTYPE
     */
    public function inspect(string $path, ExtractLimitsDto $limits): void
    {
        if (!is_file($path)) {
            throw new InvalidExtractException('The extract is not a file.');
        }
        if (filesize($path) > $limits->maxFileSize) {
            throw new InvalidExtractException(\sprintf('The extract is larger than %d bytes.', $limits->maxFileSize));
        }

        // PHP closes the archive when it frees $zip at the end of the method.
        $zip = new \ZipArchive();
        if (true !== $zip->open($path, \ZipArchive::RDONLY)) {
            throw new InvalidExtractException('The extract is not an xlsx file.');
        }

        $size = 0;
        for ($index = 0; $index < $zip->numFiles; ++$index) {
            /** @var array{size: int} $stat */
            $stat = $zip->statIndex($index);
            $size += $stat['size'];
        }
        if ($size > $limits->maxUncompressedSize) {
            throw new InvalidExtractException(\sprintf('The extract is larger than %d bytes when it is not compressed.', $limits->maxUncompressedSize));
        }

        for ($index = 0; $index < $zip->numFiles; ++$index) {
            /** @var resource $stream */
            $stream = $zip->getStreamIndex($index);
            self::inspectXml($stream, $limits);
        }
    }

    /**
     * @param resource $stream
     */
    private static function inspectXml($stream, ExtractLimitsDto $limits): void
    {
        $useInternalErrors = libxml_use_internal_errors(true);
        // Only the errors of this file count, not the errors of other code.
        libxml_clear_errors();
        // LIBXML_NONET stops a network access. Without LIBXML_DTDLOAD and LIBXML_NOENT, the reader
        // does not load a DTD or replace an entity. PHP frees the reader at the end of the method.
        $reader = \XMLReader::fromStream($stream, null, \LIBXML_NONET);

        try {
            $isXml = false;
            $row = 0;
            $column = 0;
            while ($reader->read()) {
                $isXml = true;
                if (\XMLReader::DOC_TYPE === $reader->nodeType) {
                    throw new InvalidExtractException('The extract has a DOCTYPE.');
                }
                if (\XMLReader::ELEMENT !== $reader->nodeType) {
                    continue;
                }

                // OpenSpout finds the nodes by the name without the prefix.
                switch ($reader->localName) {
                    case 'dimension':
                        // For example "A1:M13". OpenSpout makes each row as wide as the last column.
                        if (1 === preg_match('/:([A-Z]+)/', (string) $reader->getAttribute('ref'), $matches)) {
                            self::checkColumn(self::columnNumber($matches[1]), $limits);
                        }
                        break;
                    case 'row':
                        $number = $reader->getAttribute('r');
                        $row = null === $number ? $row + 1 : (int) $number;
                        if ($row > $limits->maxRows) {
                            throw new InvalidExtractException(\sprintf('The extract has more than %d rows.', $limits->maxRows));
                        }
                        $column = 0;
                        // For example "1:5". OpenSpout uses (int) on the value after the colon.
                        foreach (preg_split('/[:\s]+/', (string) $reader->getAttribute('spans')) ?: [] as $part) {
                            self::checkColumn((int) $part, $limits);
                        }
                        break;
                    case 'c':
                        $reference = $reader->getAttribute('r');
                        $column = null === $reference ? $column + 1 : self::columnNumber($reference);
                        self::checkColumn($column, $limits);
                        break;
                }
            }

            // A file that is not XML gives no nodes. OpenSpout cannot read it as a sheet.
            if ($isXml && [] !== libxml_get_errors()) {
                throw new InvalidExtractException('The extract has an XML file that is not valid.');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($useInternalErrors);
        }
    }

    private static function checkColumn(int $column, ExtractLimitsDto $limits): void
    {
        if ($column > $limits->maxColumns) {
            throw new InvalidExtractException(\sprintf('The extract has more than %d columns.', $limits->maxColumns));
        }
    }

    /**
     * Gives the number of the column in a cell reference, for example 1 for "A1" and 28 for "AB7".
     */
    private static function columnNumber(string $reference): int
    {
        $letters = substr($reference, 0, strspn($reference, self::LETTERS));

        return array_reduce(
            str_split($letters),
            static fn (int $number, string $letter): int => $number * 26 + \ord($letter) - \ord('A') + 1,
            0,
        );
    }
}
