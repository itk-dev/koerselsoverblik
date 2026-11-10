<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\DrivingDataPurgeUploadsCommand;
use App\Import\Upload\UploadStore;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Checks that the command deletes the upload files that are older than one hour. A cron job on
 * the server runs it.
 */
final class DrivingDataPurgeUploadsCommandTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/'.uniqid('uploads_', true);
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->directory);
    }

    public function testOldFilesAreDeleted(): void
    {
        $clock = new MockClock('2026-01-20 10:00:00');
        file_put_contents($this->directory.'/old.xlsx', 'x');
        touch($this->directory.'/old.xlsx', $clock->now()->getTimestamp() - 3601);
        file_put_contents($this->directory.'/new.xlsx', 'x');
        touch($this->directory.'/new.xlsx', $clock->now()->getTimestamp());

        $application = new Application();
        $application->addCommand(new DrivingDataPurgeUploadsCommand(new UploadStore(new RequestStack(), $clock, $this->directory)));
        $tester = new CommandTester($application->find('app:driving-data:purge-uploads'));
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Deleted 1 upload files that are older than one hour.', $tester->getDisplay());
        self::assertFileDoesNotExist($this->directory.'/old.xlsx');
        self::assertFileExists($this->directory.'/new.xlsx');
    }
}
