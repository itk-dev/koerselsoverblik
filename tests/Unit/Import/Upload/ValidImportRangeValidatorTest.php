<?php

declare(strict_types=1);

namespace App\Tests\Unit\Import\Upload;

use App\Import\Upload\UploadDto;
use App\Import\Upload\ValidImportRange;
use App\Import\Upload\ValidImportRangeValidator;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
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
        $this->validate(self::upload('2026-01-01', '2026-01-31'), new ValidImportRange());

        $this->assertNoViolation();
    }

    public function testLastDateBeforeTheFirstDateIsAViolation(): void
    {
        $this->validate(self::upload('2026-01-05', '2026-01-04'), new ValidImportRange());

        $this->buildViolation('upload.end_before_start')
            ->atPath('property.path.lastDate')
            ->assertRaised();
    }

    public function testRangeWithTooManyDaysIsAViolation(): void
    {
        $this->validate(self::upload('2026-01-01', '2026-02-01'), new ValidImportRange());

        $this->buildViolation('upload.range_too_long')
            ->setParameter('{max}', '31')
            ->atPath('property.path.lastDate')
            ->assertRaised();
    }

    /**
     * The message names the real problem: a range of one day has no last date before the first
     * date, so it is too long when the maximum is less than one day.
     */
    public function testOneDayAboveTheMaximumIsTooLong(): void
    {
        $this->validator = new ValidImportRangeValidator(0);

        $this->validate(self::upload('2026-01-01', '2026-01-01'), new ValidImportRange());

        $this->buildViolation('upload.range_too_long')
            ->setParameter('{max}', '0')
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

        $this->validate($upload, new ValidImportRange());

        $this->assertNoViolation();
    }

    public function testValueThatIsNotAnUploadHasNoViolation(): void
    {
        $this->validate(null, new ValidImportRange());

        $this->assertNoViolation();
    }

    public function testOtherConstraintIsAnError(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validate(self::upload('2026-01-01', '2026-01-31'), new NotNull());
    }

    public function testRangeOfAValidUpload(): void
    {
        $range = self::upload('2026-01-01', '2026-01-31')->range(31);

        self::assertSame('2026-01-01', $range->firstDate->format('Y-m-d'));
        self::assertSame('2026-01-31', $range->lastDate->format('Y-m-d'));
    }

    /**
     * The controller reads the range only from a valid form.
     */
    public function testRangeOfAnUploadWithNoDateIsAnError(): void
    {
        $this->expectException(\LogicException::class);

        new UploadDto()->range(31);
    }

    public function testRangeOfAnUploadWithNoLastDateIsAnError(): void
    {
        $upload = new UploadDto();
        $upload->firstDate = new \DateTimeImmutable('2026-01-01');

        $this->expectException(\LogicException::class);

        $upload->range(31);
    }

    private static function upload(string $firstDate, string $lastDate): UploadDto
    {
        $upload = new UploadDto();
        $upload->firstDate = new \DateTimeImmutable($firstDate);
        $upload->lastDate = new \DateTimeImmutable($lastDate);

        return $upload;
    }
}
