<?php

declare(strict_types=1);

namespace App\Tests\Unit\Import\Upload;

use App\Import\ImportRangeDto;
use App\Import\Upload\PendingUploadDto;
use App\Import\Upload\UploadStore;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Checks that an upload waits for the confirmation in a file with a random name and in the
 * session, and that old upload files are deleted.
 */
final class UploadStoreTest extends TestCase
{
    private string $directory;

    private RequestStack $requestStack;

    private MockClock $clock;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/'.uniqid('uploads_', true);
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        $this->requestStack = new RequestStack();
        $this->requestStack->push($request);
        $this->clock = new MockClock('2026-01-20 10:00:00');
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }
    }

    public function testSaveKeepsTheFileAndTheUpload(): void
    {
        $upload = $this->store()->save($this->uploadedFile('januar.xlsx'), self::range(), 'default');

        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $upload->token);
        self::assertSame($this->directory.'/'.$upload->token.'.xlsx', $upload->path);
        self::assertFileExists($upload->path);
        self::assertSame('januar.xlsx', $upload->fileName);
        self::assertEquals(self::range(), $upload->range);
        self::assertSame('default', $upload->mappingName);
        self::assertSame('2026-01-20 10:00:00', $upload->uploadedAt->format('Y-m-d H:i:s'));
        self::assertEquals($upload, $this->store()->find($upload->token));
    }

    public function testEachUploadHasItsOwnToken(): void
    {
        $first = $this->store()->save($this->uploadedFile('a.xlsx'), self::range(), 'default');
        $second = $this->store()->save($this->uploadedFile('b.xlsx'), self::range(), 'default');

        self::assertNotSame($first->token, $second->token);
        self::assertEquals($first, $this->store()->find($first->token));
        self::assertEquals($second, $this->store()->find($second->token));
    }

    public function testUnknownTokenHasNoUpload(): void
    {
        self::assertNull($this->store()->find('0123456789abcdef0123456789abcdef'));
    }

    /**
     * The purge deletes the file of an upload that waits too long. Then the upload has expired.
     */
    public function testUploadWithNoFileHasExpired(): void
    {
        $upload = $this->store()->save($this->uploadedFile('a.xlsx'), self::range(), 'default');
        unlink($upload->path);

        self::assertNull($this->store()->find($upload->token));
    }

    /**
     * The confirmation takes the upload from the session before the import. Then a second click
     * imports nothing.
     */
    public function testTakeRemovesTheUploadFromTheSession(): void
    {
        $upload = $this->store()->save($this->uploadedFile('a.xlsx'), self::range(), 'default');

        self::assertEquals($upload, $this->store()->take($upload->token));
        self::assertNull($this->store()->take($upload->token));
        self::assertNull($this->store()->find($upload->token));
        self::assertFileExists($upload->path);
    }

    public function testDeleteRemovesTheFileAndTheUpload(): void
    {
        $upload = $this->store()->save($this->uploadedFile('a.xlsx'), self::range(), 'default');

        $this->store()->delete($upload);

        self::assertFileDoesNotExist($upload->path);
        self::assertNull($this->store()->find($upload->token));
        // A second delete is not an error.
        $this->store()->delete($upload);
    }

    /**
     * A file is old when it is more than one hour old. Only the upload files are deleted.
     */
    public function testPurgeDeletesTheOldFiles(): void
    {
        $old = $this->store()->save($this->uploadedFile('old.xlsx'), self::range(), 'default');
        $edge = $this->store()->save($this->uploadedFile('edge.xlsx'), self::range(), 'default');
        $new = $this->store()->save($this->uploadedFile('new.xlsx'), self::range(), 'default');
        $now = $this->clock->now()->getTimestamp();
        touch($old->path, $now - 3601);
        touch($edge->path, $now - 3600);
        file_put_contents($this->directory.'/notes.txt', 'x');
        touch($this->directory.'/notes.txt', $now - 7200);

        self::assertSame(1, $this->store()->purge());

        self::assertFileDoesNotExist($old->path);
        self::assertFileExists($edge->path);
        self::assertFileExists($new->path);
        self::assertFileExists($this->directory.'/notes.txt');
    }

    /**
     * Each upload deletes the old files first.
     */
    public function testSaveDeletesTheOldFiles(): void
    {
        $old = $this->store()->save($this->uploadedFile('old.xlsx'), self::range(), 'default');
        touch($old->path, $this->clock->now()->getTimestamp() - 3601);

        $this->store()->save($this->uploadedFile('new.xlsx'), self::range(), 'default');

        self::assertFileDoesNotExist($old->path);
    }

    public function testPurgeOfAMissingDirectoryDeletesNothing(): void
    {
        self::assertSame(0, $this->store()->purge());
        self::assertDirectoryDoesNotExist($this->directory);
    }

    public function testPendingUploadIsAValueObject(): void
    {
        $upload = new PendingUploadDto('token', '/tmp/a.xlsx', 'a.xlsx', self::range(), 'default', new \DateTimeImmutable('2026-01-20 10:00'));

        self::assertSame('token', $upload->token);
    }

    private function store(): UploadStore
    {
        return new UploadStore($this->requestStack, $this->clock, $this->directory);
    }

    private function uploadedFile(string $name): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'upload_');
        self::assertIsString($path);
        file_put_contents($path, 'content');

        return new UploadedFile($path, $name, null, null, true);
    }

    private static function range(): ImportRangeDto
    {
        return ImportRangeDto::create(new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2026-01-31'), 31);
    }
}
