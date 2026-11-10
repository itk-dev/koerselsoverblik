<?php

declare(strict_types=1);

namespace App\Tests\Unit\Import\Upload;

use App\Import\Upload\UploadDto;
use App\Import\Upload\ValidImportRange;
use App\Import\Upload\ValidImportRangeValidator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * Checks the date range of an upload: the last date is not before the first date, and the range
 * has at most APP_DRIVING_DATA_MAX_DAYS days.
 *
 * @extends ConstraintValidatorTestCase<ValidImportRangeValidator>
 */
final class ValidImportRangeValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): ValidImportRangeValidator
    {
        return new ValidImportRangeValidator(31);
    }

    public function testValidRangeHasNoViolation(): void
    {
        $this->validator->validate(self::upload('2026-01-01', '2026-01-31'), new ValidImportRange());

        $this->assertNoViolation();
    }

    public function testLastDateBeforeTheFirstDateIsAViolation(): void
    {
        $this->validator->validate(self::upload('2026-01-05', '2026-01-04'), new ValidImportRange());

        $this->buildViolation('upload.end_before_start')
            ->atPath('property.path.lastDate')
            ->assertRaised();
    }

    public function testRangeWithTooManyDaysIsAViolation(): void
    {
        $this->validator->validate(self::upload('2026-01-01', '2026-02-01'), new ValidImportRange());

        $this->buildViolation('upload.range_too_long')
            ->setParameter('{max}', '31')
            ->atPath('property.path.lastDate')
            ->assertRaised();
    }

    /**
     * A missing date has its own violation from NotNull.
     */
    public function testMissingDateHasNoViolation(): void
    {
        $upload = new UploadDto();
        $upload->firstDate = new \DateTimeImmutable('2026-01-01');

        $this->validator->validate($upload, new ValidImportRange());

        $this->assertNoViolation();
    }

    public function testRangeOfAValidUpload(): void
    {
        $range = self::upload('2026-01-01', '2026-01-31')->range(31);

        self::assertSame('2026-01-01', $range->firstDate->format('Y-m-d'));
        self::assertSame('2026-01-31', $range->lastDate->format('Y-m-d'));
    }

    private static function upload(string $firstDate, string $lastDate): UploadDto
    {
        $upload = new UploadDto();
        $upload->firstDate = new \DateTimeImmutable($firstDate);
        $upload->lastDate = new \DateTimeImmutable($lastDate);

        return $upload;
    }
}
