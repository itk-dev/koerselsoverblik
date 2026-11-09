<?php

declare(strict_types=1);

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
    /**
     * The maximum number of files in the archive. A workbook from Excel has fewer than 100 files.
     */
    public const int MAX_FILES = 1000;

    private const string LETTERS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    /**
     * Excel has at most three letters in a column, "XFD". OpenSpout rejects a reference with more.
     */
    private const int MAX_COLUMN_LETTERS = 3;

    public function __construct(
        private ExtractLimitsDto $limits,
    ) {
    }

    /**
     * @throws InvalidExtractException if the extract is above a limit, is not an xlsx file, has a file that cannot be read or has a DOCTYPE
     */
    public function inspect(string $path): void
    {
        if (!is_file($path)) {
            throw new InvalidExtractException('The extract is not a file.');
        }
        if (filesize($path) > $this->limits->maxFileSize) {
            throw new InvalidExtractException(\sprintf('The extract is larger than %d bytes.', $this->limits->maxFileSize));
        }

        // PHP closes the archive when it frees $zip at the end of the method.
        $zip = new \ZipArchive();
        if (true !== $zip->open($path, \ZipArchive::RDONLY)) {
            throw new InvalidExtractException('The extract is not an xlsx file.');
        }
        if ($zip->numFiles > self::MAX_FILES) {
            throw new InvalidExtractException(\sprintf('The extract has more than %d files.', self::MAX_FILES));
        }

        // libzip does not check the uncompressed size that the archive gives for a file. Thus the
        // inspector counts the bytes that it reads.
        $remaining = $this->limits->maxUncompressedSize;
        for ($index = 0; $index < $zip->numFiles; ++$index) {
            $remaining -= $this->inspectFile($zip, $index, $remaining);
        }
    }

    /**
     * Copies one file of the archive to a temporary stream, and then reads its XML.
     *
     * @return int the uncompressed size of the file
     */
    private function inspectFile(\ZipArchive $zip, int $index, int $remaining): int
    {
        // An encrypted file, or a file with a compression method that libzip cannot read, gives no
        // stream.
        $stream = $zip->getStreamIndex($index);
        /** @var resource $copy PHP frees the stream at the end of the method. */
        $copy = fopen('php://temp', 'w+b');
        // One byte more than the remaining size shows that the file is too large. Bad compressed
        // data gives a warning and false. The exception replaces the warning.
        $size = false === $stream ? false : @stream_copy_to_stream($stream, $copy, $remaining + 1);
        if (false === $size) {
            throw new InvalidExtractException('The extract has a file that cannot be read.');
        }
        if ($size > $remaining) {
            throw new InvalidExtractException(\sprintf('The extract is larger than %d bytes when it is not compressed.', $this->limits->maxUncompressedSize));
        }

        rewind($copy);
        $this->inspectXml($copy);

        return $size;
    }

    /**
     * @param resource $stream
     */
    private function inspectXml($stream): void
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

                // OpenSpout finds the nodes by the name without the prefix. The inspector checks the
                // nodes in all the XML files, also in the files that are not sheets, for example
                // xl/calcChain.xml. That can reject a valid file, but it cannot accept a bad file.
                switch ($reader->localName) {
                    case 'dimension':
                        // For example "A1:M13". OpenSpout makes each row as wide as the last column.
                        if (1 === preg_match('/:([A-Z]+)/', (string) $reader->getAttribute('ref'), $matches)) {
                            $this->checkColumn(self::columnNumber($matches[1]));
                        }
                        break;
                    case 'row':
                        $number = $reader->getAttribute('r');
                        $row = null === $number ? $row + 1 : (int) $number;
                        if ($row > $this->limits->maxRows) {
                            throw new InvalidExtractException(\sprintf('The extract has more than %d rows.', $this->limits->maxRows));
                        }
                        $column = 0;
                        // For example "1:5". OpenSpout uses (int) on the value after the colon.
                        foreach (preg_split('/[:\s]+/', (string) $reader->getAttribute('spans')) ?: [] as $part) {
                            $this->checkColumn((int) $part);
                        }
                        break;
                    case 'c':
                        $reference = $reader->getAttribute('r');
                        $column = null === $reference ? $column + 1 : self::columnNumber($reference);
                        $this->checkColumn($column);
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

    private function checkColumn(int $column): void
    {
        if ($column > $this->limits->maxColumns) {
            throw new InvalidExtractException(\sprintf('The extract has more than %d columns.', $this->limits->maxColumns));
        }
    }

    /**
     * Gives the number of the column in a cell reference, for example 1 for "A1" and 28 for "AB7".
     */
    private static function columnNumber(string $reference): int
    {
        $letters = substr($reference, 0, strspn($reference, self::LETTERS));
        if (\strlen($letters) > self::MAX_COLUMN_LETTERS) {
            throw new InvalidExtractException('The extract has a cell reference that is not valid.');
        }

        return array_reduce(
            str_split($letters),
            static fn (int $number, string $letter): int => $number * 26 + \ord($letter[0]) - \ord('A') + 1,
            0,
        );
    }
}
