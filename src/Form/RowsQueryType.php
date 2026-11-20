<?php

declare(strict_types=1);

namespace App\Form;

use App\Admin\RowsQueryDto;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * The range form of the driving data page. The form sends a GET request with no prefix, so that
 * the URL of a range is short and a link can open it, for example
 * ?firstDate=2026-01-05&lastDate=2026-01-05.
 *
 * @extends AbstractType<RowsQueryDto>
 */
final class RowsQueryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstDate', DateType::class, [
                'label' => 'upload.first_date',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('lastDate', DateType::class, [
                'label' => 'upload.last_date',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('route', TextType::class, [
                'label' => 'driving_data.route',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RowsQueryDto::class,
            'method' => 'GET',
            // The form changes no data.
            'csrf_protection' => false,
            // The page numbers of the lists are in the same query.
            'allow_extra_fields' => true,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}
