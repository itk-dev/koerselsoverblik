<?php

declare(strict_types=1);

namespace App\Admin;

/**
 * One column of the copy of a row, as the detail pages show it. The page shows the message if the
 * column has one, else the value. A value is text from the extract, so the page does not
 * translate it.
 */
final readonly class SourceColumnDto
{
    /**
     * @param string      $column  the column name in the extract
     * @param string|null $value   the display text of the cell, or null if the column has a message
     * @param string|null $message the translation key of the display text, for example "common.yes"
     * @param bool        $hidden  the value is the person id, and does not show
     * @param bool        $cause   the value caused the rejection of the row
     */
    public function __construct(
        public string $column,
        public ?string $value,
        public ?string $message = null,
        public bool $hidden = false,
        public bool $cause = false,
    ) {
    }
}
