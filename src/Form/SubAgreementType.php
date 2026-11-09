<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\SubAgreement;
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
        $builder->add('reference', TextType::class, [
            'label' => 'sub_agreement.reference',
            'help' => 'sub_agreement.reference_help',
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => SubAgreement::class]);
    }
}
