<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class SettingService
{
    protected string $cacheKey = 'app_settings';

    public function all():array
    {
        return Cache::rememberForever($this->cacheKey, function () {
            return Setting::all()->pluck(Setting::COL_VALUE, Setting::COL_NAME)->toArray();
        });
    }

    public function get($name, $default = null)
    {
        $settings = $this->all();
        return $settings[$name] ?? $default;
    }

    public function set($name, $value): void
    {
        Setting::query()->updateOrCreate([Setting::COL_NAME => $name], [Setting::COL_VALUE => $value]);
        Cache::forget($this->cacheKey);
    }
}
