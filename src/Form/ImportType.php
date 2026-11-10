<?php

declare(strict_types=1);

namespace App\Form;

use App\Import\Upload\UploadDto;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

/**
 * The upload of an extract with the date range of the export.
 *
 * @extends AbstractType<UploadDto>
 */
final class ImportType extends AbstractType
{
    /**
     * The MIME types of an xlsx file. Some systems see it as a zip file. The parser checks the
     * content.
     */
    private const array XLSX_MIME_TYPES = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/zip',
    ];

    /**
     * @var positive-int
     */
    private readonly int $maxFileSize;

    /**
     * @param int $maxFileSize the value of APP_DRIVING_DATA_MAX_FILE_SIZE
     *
     * @throws \InvalidArgumentException if the size limit is below one byte
     */
    public function __construct(
        #[Autowire(param: 'app.driving_data_max_file_size')]
        int $maxFileSize,
    ) {
        if ($maxFileSize < 1) {
            throw new \InvalidArgumentException('APP_DRIVING_DATA_MAX_FILE_SIZE must be at least 1.');
        }
        $this->maxFileSize = $maxFileSize;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('file', FileType::class, [
                'label' => 'upload.file',
                'help' => 'upload.file_help',
                'attr' => ['accept' => '.xlsx'],
                'constraints' => [
                    new File(
                        maxSize: $this->maxFileSize,
                        extensions: ['xlsx' => self::XLSX_MIME_TYPES],
                        extensionsMessage: 'upload.not_xlsx',
                        maxSizeMessage: 'upload.too_large',
                    ),
                ],
            ])
            ->add('firstDate', DateType::class, [
                'label' => 'upload.first_date',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('lastDate', DateType::class, [
                'label' => 'upload.last_date',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => UploadDto::class,
            // PHP drops the body of a request above post_max_size. The form then shows this error.
            'post_max_size_message' => 'upload.request_too_large',
        ]);
    }
}
