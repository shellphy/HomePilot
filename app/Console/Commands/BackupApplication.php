<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SQLite3;
use Throwable;
use ZipArchive;

#[Signature('app:backup {--path= : 指定备份目录}')]
#[Description('备份 SQLite 数据库和用户上传文件')]
class BackupApplication extends Command
{
    public function handle(): int
    {
        $sourceDatabase = (string) config('database.connections.sqlite.database');
        $backupRoot = (string) ($this->option('path') ?: config('backup.path'));
        $retentionDays = (int) config('backup.retention_days');

        if ($sourceDatabase === ':memory:' || ! is_file($sourceDatabase)) {
            $this->components->error('SQLite 数据库文件不存在，无法备份。');

            return self::FAILURE;
        }

        if (blank($backupRoot) || $backupRoot === '/' || $retentionDays < 1) {
            $this->components->error('备份目录或保留天数配置无效。');

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
            $deletedBackups = $this->pruneExpiredBackups($backupRoot, $retentionDays);
        } catch (Throwable $exception) {
            File::deleteDirectory($temporaryDirectory);
            Log::error('应用备份失败', [
                'backup_root' => $backupRoot,
                'exception' => $exception,
            ]);
            $this->components->error('备份失败：'.$exception->getMessage());

            return self::FAILURE;
        }

        Log::info('应用备份完成', [
            'directory' => $finalDirectory,
            'deleted_backups' => $deletedBackups,
        ]);
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

    private function pruneExpiredBackups(string $backupRoot, int $retentionDays): int
    {
        $cutoff = now()->subDays($retentionDays);
        $deletedBackups = 0;

        foreach (File::directories($backupRoot) as $directory) {
            $name = basename($directory);

            if (! Str::isMatch('/^homepilot-\d{8}-\d{6}$/', $name)) {
                continue;
            }

            $createdAt = \DateTimeImmutable::createFromFormat('!Ymd-His', Str::after($name, 'homepilot-'));

            if ($createdAt !== false && $createdAt < $cutoff->toDateTimeImmutable()) {
                File::deleteDirectory($directory);
                $deletedBackups++;
            }
        }

        return $deletedBackups;
    }
}
