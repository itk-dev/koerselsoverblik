<?php

declare(strict_types=1);

namespace App\Import\Upload;

/**
 * The first and the last date of a form. ValidImportRange checks them with the rules of
 * ImportRangeDto.
 */
interface DateRangeInput
{
    public ?\DateTimeImmutable $firstDate { get; }

    public ?\DateTimeImmutable $lastDate { get; }
}
