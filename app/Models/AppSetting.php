<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Simple key-value settings store for platform-wide admin configuration.
 *
 * Usage:
 *   AppSetting::get('nsfw_checks_enabled', '0')
 *   AppSetting::set('nudity_threshold', '0.7')
 */
class AppSetting extends Model
{
    protected $table = 'app_settings';
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = ['key', 'value'];

    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::find($key);
        return $setting ? $setting->value : $default;
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => (string) $value]);
    }
}
