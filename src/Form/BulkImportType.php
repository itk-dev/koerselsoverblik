<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\Dropzone\Form\DropzoneType;

/**
 * The drop area of the bulk upload. The form is not submitted: the bulk-upload Stimulus controller
 * sends each file in its own request.
 *
 * @extends AbstractType<null>
 */
final class BulkImportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('files', DropzoneType::class, [
            'label' => 'upload.bulk.files',
            'multiple' => true,
            'mapped' => false,
            'placeholder' => 'upload.bulk.drop',
            'attr' => ['accept' => '.xlsx'],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        // The requests of the controller have their own CSRF token.
        $resolver->setDefaults(['csrf_protection' => false]);
    }
}
