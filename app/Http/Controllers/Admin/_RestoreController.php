<?php

namespace App\Http\Controllers\Admin;

use App\Facades\Setting;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Spatie\DbDumper\Databases\MySql as MySqlDumper;
use ZipArchive;

/**
 * کنترل کاملِ فرایند ریستور بکاپ در محیط‌هایی که دسترسی شِل محدود است.
 *
 * ─ مراحل:
 *   1. start             → ورود به حالت تعمیر و ایجاد tmp
 *   2. unzip_backup      → استخراج Zip
 *   3. backup_files      → بکاپ پابلیک فایل‌ها (rollback)
 *   4. backup_database   → Dump دیتابیس فعلی (rollback) با Spatie/DbDumper
 *   5. restore_files     → جایگزینی / ادغام فایل‌ها
 *   6. restore_database  → ایمپورت dump در بکاپ از طریق استریم PHP
 *   7. clean             → حذف tmp
 *   8. finished          → خروج از حالت تعمیر
 */
class _RestoreController
{
    /** @var array<string> */
    public array $steps = [
        'start', 'unzip_backup', 'backup_files', 'backup_database',
        'restore_files', 'restore_database', 'clean', 'finished',
    ];

    /* ------------------------------------------------------------------
     |  صفحه اولیه (Progress Bar)
     |------------------------------------------------------------------*/
    public function runRestore(): \Inertia\Response
    {
        $zipPath = request('filePath');
        Cache::put($this->cacheKey('backup_path'), $zipPath, 3600);
        return Inertia::render('backup/restore');
    }

    /* ------------------------------------------------------------------
     |  فراخوانی از طرف polling فرانت
     |------------------------------------------------------------------*/
    public function performRestoreStep(): RedirectResponse
    {
        $current = $this->current();
        try {
            $result = $this->{"step".Str::studly($current)}();
        } catch (Exception $e) {
            Log::error('[Restore] '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
            $result = [
                'success' => false,
                'message' => 'خطای غیر منتظره: '.$e->getMessage(),
                'step'    => $current,
                'next_step'=> null,
            ];
            // خارج کردن سایت از حالت تعمیر در صورت خرابی
            Setting::set('maintenance_mode', false);
        }

        $result['percentage'] = $this->progress($result['step'] ?? $current);
        return back()->with('back_response', $result);
    }

    /* ==================================================================
     |  Helpers
     |==================================================================*/
    private function cacheKey(string $suffix = ''): string
    {
        return 'restore_'.auth()->id().'_'.$suffix;
    }

    private function current(): string
    {
        return Cache::get($this->cacheKey('step'), $this->steps[0]);
    }

    private function step(string $next): void
    {
        Cache::put($this->cacheKey('step'), $next, 3600);
    }

    private function progress(string $step): float
    {
        return round(array_search($step, $this->steps, true) / (count($this->steps) - 1) * 100, 2);
    }

    private function tmp(string $path = ''): string
    {
        return storage_path('app/tmp'.($path ? DIRECTORY_SEPARATOR.$path : ''));
    }

    private function backupPath(): string
    {
        return Cache::get($this->cacheKey('backup_path'));
    }

    /* ==================================================================
     |  STEP 1  ─ start
     |==================================================================*/
    private function stepStart(): array
    {
        if (Setting::get('backup_running') || Setting::get('maintenance_mode')) {
            return [
                'success'=>false,
                'message'=>'سیستم در حال پشتیبان‌گیری/بروزرسانی است.',
                'step'   =>'start',
            ];
        }
        if (!$this->backupPath() || !file_exists($this->backupPath())) {
            return ['success'=>false,'message'=>'فایل پشتیبان پیدا نشد.','step'=>'start'];
        }
        Setting::set('maintenance_mode', true);
        File::deleteDirectory($this->tmp());
        File::makeDirectory($this->tmp(), 0755, true);
        $this->step('unzip_backup');
        return ['success'=>true,'message'=>'در حال استخراج فایل پشتیبان…','step'=>'start','next_step'=>'unzip_backup'];
    }

    /* ==================================================================
     |  STEP 2  ─ unzip_backup
     |==================================================================*/
    private function stepUnzipBackup(): array
    {
        $zip = new ZipArchive();
        if ($zip->open($this->backupPath()) !== true) {
            throw new Exception('ناتوان در باز کردن فایل zip');
        }
        $unzippedDir = $this->tmp(Str::random(8));
        File::makeDirectory($unzippedDir, 0755, true);
        $zip->extractTo($unzippedDir);
        $zip->close();

        Cache::put($this->cacheKey('unzipped_dir'), $unzippedDir, 3600);
        $hasFiles    = File::isDirectory($unzippedDir.'/files');
        $hasDatabase = File::isDirectory($unzippedDir.'/database');
        Cache::put($this->cacheKey('has_files'), $hasFiles, 3600);
        Cache::put($this->cacheKey('has_db'),    $hasDatabase, 3600);

        $next = $hasFiles ? 'backup_files' : 'backup_database';
        $this->step($next);
        return ['success'=>true,'message'=>'استخراج کامل شد.','step'=>'unzip_backup','next_step'=>$next];
    }

    /* ==================================================================
     |  STEP 3  ─ backup_files (rollback)
     |==================================================================*/
    private function stepBackupFiles(): array
    {
        if (!Cache::get($this->cacheKey('has_files'))) {
            $this->step('backup_database');
            return ['success'=>true,'message'=>'نیازی به بکاپ فایل نیست…','step'=>'backup_files','next_step'=>'backup_database'];
        }
        $rollbackDir   = $this->tmp('rollback_files');
        File::deleteDirectory($rollbackDir);
        File::makeDirectory($rollbackDir, 0755, true);

        /**
         * مسیر فایل‌های اصلی پروژه (به دلخواه شما)
         * TODO: در صورت نیاز ویرایش کنید.
         */
        $projectFiles = storage_path('app/public');
        File::copyDirectory($projectFiles, $rollbackDir);

        $this->step('backup_database');
        return ['success'=>true,'message'=>'بکاپ فایل‌ها ذخیره شد.','step'=>'backup_files','next_step'=>'backup_database'];
    }

    /* ==================================================================
     |  STEP 4  ─ backup_database (rollback) با Spatie/DbDumper
     |==================================================================*/
    private function stepBackupDatabase(): array
    {
        if (!Cache::get($this->cacheKey('has_db'))) {
            $this->step('restore_files');
            return ['success'=>true,'message'=>'نیازی به بکاپ DB نیست…','step'=>'backup_database','next_step'=>'restore_files'];
        }
        $dumpPath = $this->tmp('rollback_db.sql');
        MySqlDumper::create()
            ->setDbName(env('DB_DATABASE'))
            ->setUserName(env('DB_USERNAME'))
            ->setPassword(env('DB_PASSWORD'))
            ->setHost(env('DB_HOST', '127.0.0.1'))
            ->setPort(env('DB_PORT', 3306))
            ->dumpToFile($dumpPath);

        $this->step('restore_files');
        return ['success'=>true,'message'=>'دیتابیس فعلی dump شد.','step'=>'backup_database','next_step'=>'restore_files'];
    }

    /* ==================================================================
     |  STEP 5  ─ restore_files
     |==================================================================*/
    private function stepRestoreFiles(): array
    {
        if (!Cache::get($this->cacheKey('has_files'))) {
            $this->step('restore_database');
            return ['success'=>true,'message'=>'مرحله فایل رد شد.','step'=>'restore_files','next_step'=>'restore_database'];
        }
        $unzippedDir = Cache::get($this->cacheKey('unzipped_dir'));
        $src         = $unzippedDir.'/files';
        $dest        = storage_path('app/public'); // TODO: مسیر دقیق شما
        File::copyDirectory($src, $dest);

        $this->step('restore_database');
        return ['success'=>true,'message'=>'فایل‌ها جایگزین شدند.','step'=>'restore_files','next_step'=>'restore_database'];
    }

    /* ==================================================================
     |  STEP 6  ─ restore_database با استریم PHP (بدون exec)
     |==================================================================*/
    private function stepRestoreDatabase(): array
    {
        if (!Cache::get($this->cacheKey('has_db'))) {
            $this->step('clean');
            return ['success'=>true,'message'=>'مرحله دیتابیس رد شد.','step'=>'restore_database','next_step'=>'clean'];
        }
        set_time_limit(0);
        ini_set('max_execution_time', 0);
        $unzippedDir = Cache::get($this->cacheKey('unzipped_dir'));
        $dumpFile    = collect(File::files($unzippedDir.'/database'))->first(fn($f)=>Str::endsWith($f->getFilename(),'.sql'));
        if (!$dumpFile) throw new Exception('dump.sql یافت نشد.');

        // Disable FK checks
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        $handle = fopen($dumpFile->getPathname(), 'r');
        $buffer = '';
        while (!feof($handle)) {
            $line = fgets($handle);
            if ($line === false) break;
            // عبور از کامنت‌ها و خطوط خالی
            $trim = trim($line);
            if ($trim === '' || str_starts_with($trim, '--')) continue;
            $buffer .= $line;
            if (str_ends_with(trim($line), ';')) {
                // اجراى هر Query به محض رسیدن به سمی‌کالن
                DB::unprepared($buffer);
                $buffer = '';
            }
        }
        fclose($handle);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->step('clean');
        return ['success'=>true,'message'=>'دیتابیس بازگردانی شد.','step'=>'restore_database','next_step'=>'clean'];
    }

    /* ==================================================================
     |  STEP 7  ─ clean
     |==================================================================*/
    private function stepClean(): array
    {
        File::deleteDirectory($this->tmp());
        $this->step('finished');
        return ['success'=>true,'message'=>'فایل‌های موقت حذف شدند.','step'=>'clean','next_step'=>'finished'];
    }

    /* ==================================================================
     |  STEP 8  ─ finished
     |==================================================================*/
    private function stepFinished(): array
    {
        Setting::set('maintenance_mode', false);
        Cache::forget($this->cacheKey('step'));
        return ['success'=>true,'message'=>'ریستور با موفقیت انجام شد.','step'=>'finished','next_step'=>null];
    }
}
