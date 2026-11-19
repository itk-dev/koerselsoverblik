<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\BulkImportType;
use App\Form\ImportType;
use App\Import\AllRowsRejectedException;
use App\Import\DrivingDataImporter;
use App\Import\Extract\InvalidExtractException;
use App\Import\InvalidImportRangeException;
use App\Import\Mapping\MappingProvider;
use App\Import\NoStopRowsException;
use App\Import\RowIdConflictException;
use App\Import\Upload\UploadFailure;
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
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * The bulk upload. The browser sends each file in its own request: first a check, then the
 * import. Each response is the row of the file in the list. The date range comes from the file.
 */
#[IsGranted('ROLE_MANAGE_DRIVING_DATA')]
#[Route('/admin/import/bulk')]
final class BulkImportController extends AbstractController
{
    private const string TOKEN = '[0-9a-f]{32}';

    private const string CSRF_ID = 'bulk-import';

    /**
     * @param int          $maxDays     the value of APP_DRIVING_DATA_MAX_DAYS
     * @param positive-int $maxFileSize the value of APP_DRIVING_DATA_MAX_FILE_SIZE
     */
    public function __construct(
        private readonly DrivingDataImporter $importer,
        private readonly UploadStore $uploads,
        private readonly MappingProvider $mappings,
        private readonly ManagerRegistry $doctrine,
        private readonly LoggerInterface $logger,
        private readonly ValidatorInterface $validator,
        #[Autowire(param: 'app.driving_data_max_days')]
        private readonly int $maxDays,
        #[Autowire(param: 'app.driving_data_max_file_size')]
        private readonly int $maxFileSize,
    ) {
    }

    #[Route('', name: 'admin_import_bulk', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/import/bulk.html.twig', [
            'form' => $this->createForm(BulkImportType::class),
            'maxDays' => $this->maxDays,
        ]);
    }

    /**
     * Checks one file and keeps it for the import. It changes no data.
     */
    #[Route('/check', name: 'admin_import_bulk_check', methods: ['POST'])]
    public function check(Request $request): Response
    {
        $this->checkCsrfToken($request);
        $file = $request->files->get('file');
        $file = $file instanceof UploadedFile ? $file : null;
        $name = $file?->getClientOriginalName() ?? '';

        // A file with no data is skipped, not failed.
        if (0 === $file?->getSize()) {
            return $this->skippedRow($name, 'upload.bulk.skipped.empty');
        }
        $violations = $this->validator->validate($file, [new NotNull(message: 'upload.file_required'), ImportType::fileConstraint($this->maxFileSize)]);
        if (null === $file || \count($violations) > 0) {
            return $this->failedRow($name, (string) $violations[0]?->getMessage());
        }

        $mapping = $this->mappings->default();
        try {
            $range = $this->importer->rangeOf($file->getPathname(), $mapping, $this->maxDays);
            $preview = $this->importer->preview($file->getPathname(), $range, $mapping, 0);
        } catch (NoStopRowsException) {
            return $this->skippedRow($name, 'upload.bulk.skipped.no_stop_rows');
        } catch (InvalidExtractException|AllRowsRejectedException|InvalidImportRangeException $exception) {
            return $this->failed($name, $exception);
        }
        $upload = $this->uploads->save($file, $range, $mapping->name);

        return $this->render('admin/import/_bulk_row.html.twig', [
            'name' => $upload->fileName,
            'state' => 'checked',
            'firstDate' => $range->firstDate,
            'lastDate' => $range->lastDate,
            'counts' => ['insert' => $preview->summary->stopRows, 'delete' => $preview->rowsToDelete, 'reject' => $preview->summary->rejectedRows],
            'importUrl' => $this->generateUrl('admin_import_bulk_import', ['token' => $upload->token]),
        ]);
    }

    /**
     * Imports one checked file.
     */
    #[Route('/{token}', name: 'admin_import_bulk_import', requirements: ['token' => self::TOKEN], methods: ['POST'])]
    public function import(Request $request, string $token): Response
    {
        $this->checkCsrfToken($request);
        // Take the upload before the import, so that a second request imports nothing.
        $upload = $this->uploads->take($token);
        if (null === $upload) {
            return $this->failedRow('', new TranslatableMessage('upload.expired'));
        }

        $user = $this->getUser();
        try {
            $batch = $this->importer->import($upload->path, $upload->range, $this->mappings->get($upload->mappingName), $user instanceof User ? $user : null, $upload->fileName);
        } catch (InvalidExtractException|RowIdConflictException|NoStopRowsException $exception) {
            // Doctrine closes the entity manager after a failed transaction.
            $this->doctrine->resetManager();
            $this->uploads->delete($upload);

            return $this->failed($upload->fileName, $exception);
        }
        $this->uploads->delete($upload);

        return $this->render('admin/import/_bulk_row.html.twig', [
            'name' => $upload->fileName,
            'state' => 'imported',
            'firstDate' => $batch->getFirstDate(),
            'lastDate' => $batch->getLastDate(),
            'counts' => ['insert' => $batch->getRowsInserted(), 'delete' => $batch->getRowsDeleted(), 'reject' => $batch->getRowsRejected()],
            'resultUrl' => $this->generateUrl('admin_import_result', ['id' => $batch->getId()]),
        ]);
    }

    private function checkCsrfToken(Request $request): void
    {
        if (!$this->isCsrfTokenValid(self::CSRF_ID, (string) $request->headers->get('X-CSRF-Token'))) {
            throw $this->createAccessDeniedException('The CSRF token is not valid.');
        }
    }

    /**
     * The log line has no value from the extract.
     */
    private function failed(string $name, InvalidExtractException|RowIdConflictException|NoStopRowsException|AllRowsRejectedException|InvalidImportRangeException $exception): Response
    {
        $this->logger->warning('A bulk upload of driving data failed: {message}', [
            'message' => $exception->getMessage(),
            'user' => $this->getUser()?->getUserIdentifier(),
        ]);

        return $this->failedRow($name, UploadFailure::message($exception, $this->maxDays));
    }

    private function skippedRow(string $name, string $reason): Response
    {
        return $this->render('admin/import/_bulk_row.html.twig', ['name' => $name, 'state' => 'skipped', 'error' => new TranslatableMessage($reason)]);
    }

    private function failedRow(string $name, string|TranslatableMessage $error): Response
    {
        return $this->render('admin/import/_bulk_row.html.twig', ['name' => $name, 'state' => 'failed', 'error' => $error], new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));
    }
}
