<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Supplier;
use App\Supplier\AssignmentDto;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<AssignmentDto>
 */
final class AssignmentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('supplier', EntityType::class, [
                'label' => 'assignment.supplier',
                'class' => Supplier::class,
                'choice_label' => 'name',
                'placeholder' => 'assignment.choose_supplier',
                'query_builder' => self::suppliersByName(...),
            ])
            ->add('validFrom', DateType::class, [
                'label' => 'assignment.valid_from',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('validTo', DateType::class, [
                'label' => 'assignment.valid_to',
                'help' => 'assignment.valid_to_help',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => false,
            ]);
    }

    /**
     * @param EntityRepository<Supplier> $repository
     */
    private static function suppliersByName(EntityRepository $repository): QueryBuilder
    {
        return $repository->createQueryBuilder('supplier')->orderBy('supplier.name');
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => AssignmentDto::class]);
    }
}
