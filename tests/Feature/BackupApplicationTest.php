<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

test('the backup command creates a consistent database snapshot and upload archive', function () {
    $temporaryRoot = sys_get_temp_dir().'/homepilot-test-'.Str::uuid();
    $databasePath = $temporaryRoot.'/source.sqlite';
    $backupPath = $temporaryRoot.'/backups';
    $uploadPath = storage_path('app/public/backup-test.txt');
    File::ensureDirectoryExists($temporaryRoot);
    File::ensureDirectoryExists($backupPath.'/homepilot-20200101-030000');
    File::ensureDirectoryExists($backupPath.'/manual-files');
    File::ensureDirectoryExists(dirname($uploadPath));
    File::put($uploadPath, 'upload contents');

    $database = new SQLite3($databasePath);
    $database->exec('create table samples (value text not null)');
    $database->exec("insert into samples (value) values ('snapshot contents')");
    $database->close();

    config([
        'database.connections.sqlite.database' => $databasePath,
        'backup.path' => $backupPath,
        'backup.retention_days' => 7,
    ]);
    Log::spy();

    try {
        $this->artisan('app:backup')->assertSuccessful();

        $backupDirectory = collect(File::directories($backupPath))
            ->first(fn (string $directory): bool => File::isFile($directory.'/database.sqlite'));
        expect($backupDirectory)->toBeString();
        $snapshot = new SQLite3($backupDirectory.'/database.sqlite', SQLITE3_OPEN_READONLY);
        expect($snapshot->querySingle('select value from samples'))->toBe('snapshot contents');
        $snapshot->close();

        $archive = new ZipArchive;
        expect($archive->open($backupDirectory.'/uploads.zip'))->toBeTrue()
            ->and($archive->getFromName('backup-test.txt'))->toBe('upload contents');
        $archive->close();

        expect(File::isDirectory($backupPath.'/homepilot-20200101-030000'))->toBeFalse()
            ->and(File::isDirectory($backupPath.'/manual-files'))->toBeTrue();

        Log::shouldHaveReceived('info')->withArgs(
            fn (string $message, array $context): bool => $message === '应用备份完成'
                && $context['deleted_backups'] === 1,
        );

        config(['backup.retention_days' => 0]);
        $this->artisan('app:backup')->assertFailed();
    } finally {
        File::delete($uploadPath);
        File::deleteDirectory($temporaryRoot);
    }
});
