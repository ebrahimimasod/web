<?php

// app/helpers.php  (یا هر جایی که فایل‌های هِلپری وارد می‌کنید)
if (! function_exists('humanSize')) {
    /**
     * تبدیل بایت به رشتهٔ خوانا (KB / MB / GB).
     */
    function humanSize(int $bytes, int $decimals = 2): string
    {
        // 1 GB = 1 024 MB = 1 073 741 824 بایت
        if ($bytes >= 1_073_741_824) {
            return number_format($bytes / 1_073_741_824, $decimals) . ' GB';
        }

        // 1 MB = 1 024 KB = 1 048 576 بایت
        if ($bytes >= 1_048_576) {
            return number_format($bytes / 1_048_576, $decimals) . ' MB';
        }

        // 1 KB = 1 024 بایت
        if ($bytes >= 1_024) {
            return number_format($bytes / 1_024, $decimals) . ' KB';
        }

        // کمتر از ۱ KB
        return $bytes . ' B';
    }
}
