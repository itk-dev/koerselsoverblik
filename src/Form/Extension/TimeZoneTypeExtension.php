<?php

namespace App\Form\Extension;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Gives the date and time fields the value in UTC, and shows it in the time zone of the
 * application. A date field has no conversion. See ADR 005.
 */
final class TimeZoneTypeExtension extends AbstractTypeExtension
{
    /**
     * @param string $timeZone the value of APP_TIMEZONE
     */
    public function __construct(
        #[Autowire('%app.timezone%')]
        private readonly string $timeZone,
    ) {
    }

    public static function getExtendedTypes(): iterable
    {
        return [DateTimeType::class, TimeType::class];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('model_timezone', 'UTC');
        $resolver->setDefault('view_timezone', function (Options $options): string {
            // A time with no date has no offset. TimeType converts a time only with a reference date.
            if ($options->offsetExists('reference_date') && null === $options['reference_date']) {
                return 'UTC';
            }

            return $this->timeZone;
        });
    }
}
