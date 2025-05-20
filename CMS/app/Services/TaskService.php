<?php

namespace App\Services;

use App\Models\Task;
use Ifsnop\Mysqldump as Mysqldump;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Exception;
use RuntimeException;
use ZipArchive;


class TaskService
{

    public ?Task $task = null;

    public function __construct(Task|string $task, array $attributes = [])
    {
        $this->task = is_string($task)
            ? Task::query()->firstOrCreate([Task::COL_TRACK_ID => $task], $attributes)
            : $task;
    }

    public function updateTaskStep($step): void
    {
        $this->task->update([
            Task::COL_STEP => $step
        ]);
    }

    public function updateTask($data): void
    {
        $this->task->update($data);
    }

    public function dbDumper($path): void
    {
        $db = config('database.connections.mysql');
        try {
            $dsn = "mysql:host={$db['host']};port={$db['port']};dbname={$db['database']}";
            $d = new Mysqldump\Mysqldump($dsn, $db['username'], $db['password'], [
                'single-transaction' => true,
                'add-drop-table' => true,
                'lock-tables' => false,
                'hex-blob' => true,
            ]);
            $d->start($path);
        } catch (\Throwable $e) {
            report($e);
            throw  $e;
        }
    }

    public function zippingRootFiles(array $config): void
    {
        $filename = data_get($config, 'filename');
        $databaseDumpPath = data_get($config, 'database_dump_path', false);
        $includeFiles = data_get($config, 'include_files', true);
        $zipChunkSize = data_get($config, 'zip_chunk_size', 50);

        $projectRoot = realpath(base_path('..')) ?: base_path();
        $projectRoot = rtrim(str_replace('\\', '/', $projectRoot), '/') . '/';
        $rootLen = strlen($projectRoot);

        $files = [];

        /* ---------------------------------------------------------------------
     | ۱) افزودن database
     |--------------------------------------------------------------------*/
        if ($databaseDumpPath) {
            if (is_file($databaseDumpPath)) {
                $files[] = ['path' => $databaseDumpPath, 'dest' => 'database/dump.sql'];
            }
        }

        /* ---------------------------------------------------------------------
     | ۲) جمع‌آوری همهٔ فایل‌های پروژه  (CMS، build، index.php، …)
     |--------------------------------------------------------------------*/
        if ($includeFiles) {

            /** مسیرهایی که نباید در بکاپ بیایند */
            $exclude = array_map(fn($rel) => rtrim(
                str_replace('\\', '/', $projectRoot . ltrim($rel, '/')),
                '/'), [
                '.git',
                '.github',
                '.idea',
                '.vscode',
                'node_modules',
                'CMS/node_modules',
                'CMS/tests',
                'CMS/storage/logs',
                'CMS/storage/framework/cache',
                'CMS/storage/app/private/backups',
                'CMS/storage/app/updates',
                'CMS/storage/app/tmp',
                'CMS/storage/framework/sessions',
                'CMS/storage/framework/views',
                'logs',
                'access-logs',
            ]);

            /** بازگشتِ بازگشتی با در نظر گرفتن open_basedir و symlink */
            $collect = function (string $dir) use (&$collect, &$files, $exclude, $rootLen) {

                $items = @scandir($dir);
                if ($items === false) {
                    return; // احتمالاً محدودیت open_basedir
                }

                foreach ($items as $item) {
                    if ($item === '.' || $item === '..') {
                        continue;
                    }

                    $path = $dir . '/' . $item;
                    $n = str_replace('\\', '/', $path);

                    // کنار گذاشتن مسیرهای exclude
                    foreach ($exclude as $ex) {
                        if (strpos($n, $ex) === 0) {
                            continue 2;
                        }
                    }

                    // دنبال نکردن symlink
                    if (@is_link($n)) {
                        continue;
                    }

                    if (is_dir($n)) {
                        $collect($n);
                    } elseif (is_file($n)) {
                        // مسیر مقصد را از ریشهٔ پروژه بُرش می‌دهیم
                        $relative = ltrim(substr($n, $rootLen), '/');
                        $files[] = [
                            'path' => $n,
                            'dest' => 'files/' . $relative,
                        ];
                    }
                }
            };

            $collect(rtrim($projectRoot, '/'));  // ← حالا از «ریشهٔ واقعی» اسکن می‌کند
        }

        /* ---------------------------------------------------------------------
     | ۳) ایجاد ZIPهای تکه‌ای
     |--------------------------------------------------------------------*/
        $partIdx = 1;
        $currentSize = 0;
        $partPaths = [];
        $zip = new ZipArchive();

        $openPart = function () use (&$zip, &$partIdx, &$partPaths, $filename) {
            $zipName = "{$filename}-part{$partIdx}.zip";
            $zipPath = $this->getTempPath($zipName);

            @unlink($zipPath);
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException("Cannot create {$zipName}");
            }
            $partPaths[] = $zipPath;
        };

        $openPart();  // part 1

        foreach ($files as $f) {
            $size = filesize($f['path']) ?: 0;

            if ($currentSize > 0 && $currentSize + $size > $zipChunkSize) {
                $zip->close();
                ++$partIdx;
                $currentSize = 0;
                $openPart();
            }

            $zip->addFile($f['path'], $f['dest']);
            $currentSize += $size;
        }
        $zip->close();

        /* ---------------------------------------------------------------------
     | ۴) ذخیرهٔ مسیر پارت‌ها
     |--------------------------------------------------------------------*/
        $payload = array_merge($this->task->payload ?? [], array(
            'part_paths' => $partPaths,
            'file_name' => $filename
        ));

        $this->updateTask([Task::COL_PAYLOAD => $payload]);
    }

    public function packagingZipParts(): void
    {
        $taskPayload = $this->task->payload ?? [];
        $partPaths = data_get($taskPayload, 'part_paths', []);
        $filename = data_get($taskPayload, 'file_name', []);

        if (!$partPaths || !$filename) {
            throw new Exception("فایل زیپ پشتیبان پیدا نشد");
        }
        $containerName = "$filename.zip";
        $containerPath = $this->getTempPath($containerName);

        $zip = new ZipArchive();
        if ($zip->open($containerPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \Exception('Cannot create container');
        }
        foreach ($partPaths as $pp) $zip->addFile($pp, basename($pp));
        $zip->close();
        foreach ($partPaths as $pp) @unlink($pp); // حذف پارت‌های موقت


        $payload = array_merge($taskPayload ?? [], [
            "zip_path" => $containerPath,
            "zip_name" => $containerName,
        ]);
        $this->updateTask([Task::COL_PAYLOAD => $payload]);

    }

    public function saveFileOnStorages($localFolder, $remoteConfig): void
    {
        $taskPayload = $this->task->payload ?? [];
        $zipPath = data_get($taskPayload, 'zip_path');
        $zipName = data_get($taskPayload, 'zip_name');

        if (!$zipPath || !file_exists($zipPath)) {
            throw new Exception("فایل زیپ پشتیبان پیدا نشد");
        }

        /* ---------- 1) همیشه یک کپی لوکال بگیر ---------- */
        Storage::disk('local')->putFileAs($localFolder, $zipPath, $zipName);

        /* ---------- 2) مقصدهایی که کاربر تعریف کرده ---------- */
        $stream = fopen($zipPath, 'r');
        Storage::build($remoteConfig)->put("{$localFolder}/{$zipName}", $stream);
        fclose($stream);

    }

    private function getTempPath(string $rel = ''): string
    {
        return storage_path('app/tmp' . ($rel ? DIRECTORY_SEPARATOR . $rel : ''));
    }

}
