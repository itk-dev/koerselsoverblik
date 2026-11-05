<?php

namespace App\Tests\Integration\Form;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

/**
 * Checks that a date and time field shows local time and gives UTC, and that a date field has no
 * conversion. See ADR 005.
 */
final class TimeZoneTypeExtensionTest extends KernelTestCase
{
    public function testADateTimeFieldGivesUtc(): void
    {
        $form = $this->form(DateTimeType::class, ['widget' => 'single_text', 'input' => 'datetime_immutable']);

        $form->submit('2026-01-05T07:10');

        self::assertUtc('2026-01-05 06:10:00', $form->getData());
    }

    public function testADateTimeFieldShowsLocalTime(): void
    {
        $form = $this->form(DateTimeType::class, ['widget' => 'single_text', 'input' => 'datetime_immutable']);

        $form->setData(new \DateTimeImmutable('2026-06-05 05:10:00', new \DateTimeZone('UTC')));

        self::assertSame('2026-06-05T07:10', $form->getViewData());
    }

    public function testATimeFieldWithAReferenceDateGivesUtc(): void
    {
        $form = $this->form(TimeType::class, [
            'input' => 'datetime_immutable',
            'reference_date' => new \DateTimeImmutable('2026-01-05', new \DateTimeZone('UTC')),
        ]);

        $form->submit('07:10');

        self::assertUtc('2026-01-05 06:10:00', $form->getData());
    }

    /**
     * A time with no date has no offset, so it is not converted.
     */
    public function testATimeFieldWithNoReferenceDateHasNoConversion(): void
    {
        $form = $this->form(TimeType::class, ['input' => 'datetime_immutable']);

        $form->submit('07:10');

        self::assertUtc('1970-01-01 07:10:00', $form->getData());
    }

    public function testADateFieldHasNoConversion(): void
    {
        $form = $this->form(DateType::class, ['widget' => 'single_text', 'input' => 'datetime_immutable']);

        $form->submit('2026-01-05');

        self::assertUtc('2026-01-05 00:00:00', $form->getData());
    }

    /**
     * @param class-string<DateTimeType|DateType|TimeType> $type
     * @param array<string, mixed>                         $options
     *
     * @return FormInterface<mixed>
     */
    private function form(string $type, array $options): FormInterface
    {
        $factory = self::getContainer()->get('form.factory');
        self::assertInstanceOf(FormFactoryInterface::class, $factory);

        return $factory->create($type, null, $options);
    }

    private static function assertUtc(string $expected, mixed $actual): void
    {
        self::assertInstanceOf(\DateTimeImmutable::class, $actual);
        self::assertSame($expected.' UTC', $actual->format('Y-m-d H:i:s T'));
    }
}
