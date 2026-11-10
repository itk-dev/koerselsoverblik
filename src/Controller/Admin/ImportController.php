<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\ImportBatch;
use App\Entity\User;
use App\Form\ImportType;
use App\Import\DrivingDataImporter;
use App\Import\Extract\InvalidExtractException;
use App\Import\Extract\MissingColumnsException;
use App\Import\Mapping\MappingProvider;
use App\Import\RowIdConflictException;
use App\Import\Upload\PendingUploadDto;
use App\Import\Upload\UploadDto;
use App\Import\Upload\UploadStore;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * Uploads an extract: the form, the preview, the confirmation and the result. No data changes
 * before the confirmation. See "Upload driving data" in the README.
 */
#[IsGranted('ROLE_MANAGE_DRIVING_DATA')]
#[Route('/admin/import')]
final class ImportController extends AbstractController
{
    private const string TOKEN = '[0-9a-f]{32}';

    /**
     * @param int $maxDays the value of APP_DRIVING_DATA_MAX_DAYS
     */
    public function __construct(
        private readonly DrivingDataImporter $importer,
        private readonly UploadStore $uploads,
        private readonly MappingProvider $mappings,
        private readonly ManagerRegistry $doctrine,
        private readonly LoggerInterface $logger,
        #[Autowire(param: 'app.driving_data_max_days')]
        private readonly int $maxDays,
    ) {
    }

    #[Route('', name: 'admin_import', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $upload = new UploadDto();
        $form = $this->createForm(ImportType::class, $upload);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $upload->file instanceof UploadedFile) {
            $pending = $this->uploads->save($upload->file, $upload->range($this->maxDays), $this->mappings->default()->name);

            return $this->redirectToRoute('admin_import_preview', ['token' => $pending->token]);
        }

        return $this->render('admin/import/index.html.twig', ['form' => $form]);
    }

    #[Route('/preview/{token}', name: 'admin_import_preview', requirements: ['token' => self::TOKEN], methods: ['GET'])]
    public function preview(string $token): Response
    {
        $upload = $this->uploads->find($token);
        if (null === $upload) {
            return $this->expired();
        }

        try {
            $preview = $this->importer->preview($upload->path, $upload->range, $this->mappings->get($upload->mappingName), 0);
        } catch (InvalidExtractException $exception) {
            return $this->failed($upload, $exception);
        }

        return $this->render('admin/import/preview.html.twig', ['upload' => $upload, 'preview' => $preview]);
    }

    #[Route('/preview/{token}/confirm', name: 'admin_import_confirm', requirements: ['token' => self::TOKEN], methods: ['POST'])]
    public function confirm(Request $request, string $token): Response
    {
        $this->checkCsrfToken($request, $token);
        // Take the upload before the import, so that a second click imports nothing.
        $upload = $this->uploads->take($token);
        if (null === $upload) {
            return $this->expired();
        }

        $user = $this->getUser();
        try {
            $batch = $this->importer->import($upload->path, $upload->range, $this->mappings->get($upload->mappingName), $user instanceof User ? $user : null, $upload->fileName);
        } catch (InvalidExtractException|RowIdConflictException $exception) {
            // Doctrine closes the entity manager after a failed transaction.
            $this->doctrine->resetManager();

            return $this->failed($upload, $exception);
        }
        $this->uploads->delete($upload);

        return $this->redirectToRoute('admin_import_result', ['id' => $batch->getId()]);
    }

    #[Route('/preview/{token}/cancel', name: 'admin_import_cancel', requirements: ['token' => self::TOKEN], methods: ['POST'])]
    public function cancel(Request $request, string $token): Response
    {
        $this->checkCsrfToken($request, $token);
        $upload = $this->uploads->find($token);
        if (null !== $upload) {
            $this->uploads->delete($upload);
        }
        $this->addFlash('success', 'upload.cancelled');

        return $this->redirectToRoute('admin_import');
    }

    #[Route('/{id}', name: 'admin_import_result', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function result(ImportBatch $batch): Response
    {
        return $this->render('admin/import/result.html.twig', ['batch' => $batch]);
    }

    private function checkCsrfToken(Request $request, string $token): void
    {
        if (!$this->isCsrfTokenValid('import-'.$token, $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('The CSRF token is not valid.');
        }
    }

    private function expired(): Response
    {
        $this->addFlash('error', 'upload.expired');

        return $this->redirectToRoute('admin_import');
    }

    /**
     * Deletes the upload, and shows the problem in Danish. The log line has no value from the
     * extract.
     */
    private function failed(PendingUploadDto $upload, InvalidExtractException|RowIdConflictException $exception): Response
    {
        $this->uploads->delete($upload);
        $this->logger->warning('An upload of driving data failed: {message}', [
            'message' => $exception->getMessage(),
            'user' => $this->getUser()?->getUserIdentifier(),
        ]);

        $this->addFlash('error', match (true) {
            $exception instanceof MissingColumnsException => new TranslatableMessage('upload.missing_columns', ['columns' => implode(', ', $exception->columns)]),
            $exception instanceof InvalidExtractException => new TranslatableMessage('upload.problem.'.$exception->problem->value, ['limit' => $exception->limit ?? 0]),
            default => new TranslatableMessage('upload.row_id_conflict'),
        });

        return $this->redirectToRoute('admin_import');
    }
}
