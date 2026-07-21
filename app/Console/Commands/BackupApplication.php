<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SQLite3;
use Throwable;
use ZipArchive;

#[Signature('app:backup {--path= : Override the configured backup directory}')]
#[Description('Create a consistent SQLite snapshot and archive public uploads')]
class BackupApplication extends Command
{
    public function handle(): int
    {
        $sourceDatabase = config('database.connections.sqlite.database');
        $backupRoot = $this->option('path') ?: config('backup.path');

        if (! is_string($sourceDatabase) || $sourceDatabase === ':memory:' || ! is_file($sourceDatabase)) {
            $this->components->error('SQLite 数据库文件不存在，无法备份。');

            return self::FAILURE;
        }

        if (! is_string($backupRoot) || blank($backupRoot) || $backupRoot === '/') {
            $this->components->error('备份目录配置无效。');

            return self::FAILURE;
        }

        File::ensureDirectoryExists($backupRoot);

        $name = 'homepilot-'.now()->format('Ymd-His');
        $temporaryDirectory = $backupRoot.'/.'.$name.'-'.Str::uuid().'.partial';
        $finalDirectory = $backupRoot.'/'.$name;

        try {
            File::ensureDirectoryExists($temporaryDirectory);
            $this->backupDatabase($sourceDatabase, $temporaryDirectory.'/database.sqlite');
            $this->archiveUploads(storage_path('app/public'), $temporaryDirectory.'/uploads.zip');
            File::moveDirectory($temporaryDirectory, $finalDirectory);
            $this->pruneExpiredBackups($backupRoot);
        } catch (Throwable $exception) {
            File::deleteDirectory($temporaryDirectory);
            report($exception);
            $this->components->error('备份失败：'.$exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("备份已写入 {$finalDirectory}");

        return self::SUCCESS;
    }

    private function backupDatabase(string $sourcePath, string $destinationPath): void
    {
        $source = new SQLite3($sourcePath, SQLITE3_OPEN_READONLY);
        $destination = new SQLite3($destinationPath, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);

        try {
            if (! $source->backup($destination)) {
                throw new \RuntimeException('SQLite 在线快照失败');
            }
        } finally {
            $destination->close();
            $source->close();
        }
    }

    private function archiveUploads(string $sourcePath, string $destinationPath): void
    {
        $archive = new ZipArchive;

        if ($archive->open($destinationPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('无法创建上传文件压缩包');
        }

        try {
            if (! is_dir($sourcePath)) {
                return;
            }

            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($sourcePath, RecursiveDirectoryIterator::SKIP_DOTS),
            );

            foreach ($files as $file) {
                if ($file->isFile()) {
                    $relativePath = Str::after($file->getPathname(), rtrim($sourcePath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR);
                    $archive->addFile($file->getPathname(), $relativePath);
                }
            }
        } finally {
            $archive->close();
        }
    }

    private function pruneExpiredBackups(string $backupRoot): void
    {
        $cutoff = now()->subDays(max(1, (int) config('backup.retention_days')));

        foreach (File::directories($backupRoot) as $directory) {
            $name = basename($directory);

            if (! Str::isMatch('/^homepilot-\d{8}-\d{6}$/', $name)) {
                continue;
            }

            $createdAt = \DateTimeImmutable::createFromFormat('!Ymd-His', Str::after($name, 'homepilot-'));

            if ($createdAt !== false && $createdAt < $cutoff->toDateTimeImmutable()) {
                File::deleteDirectory($directory);
            }
        }
    }
}
