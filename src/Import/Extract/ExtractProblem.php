<?php

declare(strict_types=1);

namespace App\Import\Extract;

/**
 * The problems that stop the read of an extract. The upload page translates the code. The
 * command and the logs show the English message.
 */
enum ExtractProblem: string
{
    case NotFile = 'not_file';
    case TooLarge = 'too_large';
    case NotXlsx = 'not_xlsx';
    case TooManyFiles = 'too_many_files';
    case Unreadable = 'unreadable';
    case TooLargeUncompressed = 'too_large_uncompressed';
    case Doctype = 'doctype';
    case TooManyRows = 'too_many_rows';
    case InvalidXml = 'invalid_xml';
    case TooManyColumns = 'too_many_columns';
    case InvalidCellReference = 'invalid_cell_reference';
    case InvalidWorkbook = 'invalid_workbook';
    case NoHeaderRow = 'no_header_row';
    case NoSheet = 'no_sheet';
    case MissingColumns = 'missing_columns';

    /**
     * Gives the English message. It has the limit or the number from the mapping, never a value
     * from the extract.
     *
     * @param ?int $limit the limit, the header row or the sheet index of the problem
     */
    public function message(?int $limit): string
    {
        return \sprintf(match ($this) {
            self::NotFile => 'The extract is not a file.',
            self::TooLarge => 'The extract is larger than %d bytes.',
            self::NotXlsx => 'The extract is not an xlsx file.',
            self::TooManyFiles => 'The extract has more than %d files.',
            self::Unreadable => 'The extract has a file that cannot be read.',
            self::TooLargeUncompressed => 'The extract is larger than %d bytes when it is not compressed.',
            self::Doctype => 'The extract has a DOCTYPE.',
            self::TooManyRows => 'The extract has more than %d rows.',
            self::InvalidXml => 'The extract has an XML file that is not valid.',
            self::TooManyColumns => 'The extract has more than %d columns.',
            self::InvalidCellReference => 'The extract has a cell reference that is not valid.',
            self::InvalidWorkbook => 'The extract is not a valid xlsx file.',
            self::NoHeaderRow => 'The extract has no header row %d.',
            self::NoSheet => 'The extract has no sheet with the index %d.',
            self::MissingColumns => 'The extract does not have all the columns of the mapping.',
        }, $limit ?? 0);
    }
}
