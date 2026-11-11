<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\SubAgreement;
use App\Entity\Supplier;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<SubAgreement>
 */
final class SubAgreementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // The import finds the sub-agreement of a route by the reference, so the edit page does
        // not change it.
        if (true === $options['edit_reference']) {
            $builder->add('reference', TextType::class, [
                'label' => 'sub_agreement.reference',
                'help' => 'sub_agreement.reference_help',
            ]);
        }
        $builder->add('supplier', EntityType::class, [
            'label' => 'sub_agreement.supplier',
            'class' => Supplier::class,
            'choice_label' => 'name',
            'placeholder' => 'sub_agreement.no_supplier',
            'query_builder' => self::suppliersByName(...),
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
        $resolver->setDefaults([
            'data_class' => SubAgreement::class,
            'edit_reference' => true,
        ]);
        $resolver->setAllowedTypes('edit_reference', 'bool');
    }
}
