<?php

declare(strict_types=1);

namespace App\Import\Upload;

use App\Import\ImportRangeDto;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Keeps the uploaded extracts that wait for the confirmation. The file has a random name in the
 * upload directory, and the session has the upload under a random token. See "Pipeline" in
 * docs/architecture.md.
 */
final readonly class UploadStore
{
    private const string SESSION_KEY = 'driving_data_uploads';

    /**
     * An upload file that is older than this, in seconds, is deleted.
     */
    private const int MAX_AGE = 3600;

    private const int TOKEN_BYTES = 16;

    public function __construct(
        private RequestStack $requestStack,
        private ClockInterface $clock,
        #[Autowire('%kernel.project_dir%/var/uploads/driving_data')]
        private string $directory,
    ) {
    }

    /**
     * Deletes the old files, and then keeps the uploaded file.
     */
    public function save(UploadedFile $file, ImportRangeDto $range, string $mappingName): PendingUploadDto
    {
        $this->purge();

        $token = bin2hex(random_bytes(self::TOKEN_BYTES));
        $moved = $file->move($this->directory, $token.'.xlsx');
        $upload = new PendingUploadDto($token, $moved->getPathname(), $file->getClientOriginalName(), $range, $mappingName, $this->clock->now());

        $uploads = $this->uploads();
        $uploads[$token] = $upload;
        $this->requestStack->getSession()->set(self::SESSION_KEY, $uploads);

        return $upload;
    }

    /**
     * Gives the upload, or null if the token is unknown or the file is deleted.
     */
    public function find(string $token): ?PendingUploadDto
    {
        $upload = $this->uploads()[$token] ?? null;

        return null !== $upload && is_file($upload->path) ? $upload : null;
    }

    /**
     * Gives the upload and removes it from the session, so that the next request cannot use it.
     * The file stays.
     */
    public function take(string $token): ?PendingUploadDto
    {
        $upload = $this->find($token);
        $this->forget($token);

        return $upload;
    }

    /**
     * Deletes the file and removes the upload from the session.
     */
    public function delete(PendingUploadDto $upload): void
    {
        if (is_file($upload->path)) {
            unlink($upload->path);
        }
        $this->forget($upload->token);
    }

    /**
     * Deletes the upload files that are older than one hour.
     *
     * @return int the number of deleted files
     */
    public function purge(): int
    {
        $limit = $this->clock->now()->getTimestamp() - self::MAX_AGE;
        $deleted = 0;
        foreach (glob($this->directory.'/*.xlsx') ?: [] as $path) {
            if (filemtime($path) < $limit) {
                unlink($path);
                ++$deleted;
            }
        }

        return $deleted;
    }

    private function forget(string $token): void
    {
        $uploads = $this->uploads();
        unset($uploads[$token]);
        $this->requestStack->getSession()->set(self::SESSION_KEY, $uploads);
    }

    /**
     * @return array<string, PendingUploadDto>
     */
    private function uploads(): array
    {
        /** @var array<string, PendingUploadDto> $uploads */
        $uploads = $this->requestStack->getSession()->get(self::SESSION_KEY, []);

        return $uploads;
    }
}
