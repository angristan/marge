<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * @property string $key
 * @property string|null $value
 * @property bool $encrypted
 */
class Setting extends Model
{
    /**
     * Request-scoped cache for all settings values.
     *
     * @var array<string, string|null>
     */
    protected static array $cache = [];

    /**
     * Whether all settings have been loaded into cache.
     */
    protected static bool $cacheLoaded = false;

    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'value',
        'encrypted',
    ];

    protected function casts(): array
    {
        return [
            'encrypted' => 'boolean',
        ];
    }

    /**
     * Get the decrypted value.
     */
    public function getDecryptedValue(): ?string
    {
        if ($this->value === null) {
            return null;
        }

        if ($this->encrypted) {
            return Crypt::decryptString($this->value);
        }

        return $this->value;
    }

    /**
     * Set the value, optionally encrypting it.
     */
    public function setEncryptedValue(string $value, bool $encrypt = false): void
    {
        $this->encrypted = $encrypt;
        $this->value = $encrypt ? Crypt::encryptString($value) : $value;
    }

    /**
     * Load all settings into cache with a single query.
     */
    protected static function loadCache(): void
    {
        if (static::$cacheLoaded) {
            return;
        }

        static::$cache = [];
        foreach (static::all() as $setting) {
            static::$cache[$setting->key] = $setting->getDecryptedValue();
        }
        static::$cacheLoaded = true;
    }

    /**
     * Get a setting value by key (cached per request).
     */
    public static function getValue(string $key, ?string $default = null): ?string
    {
        static::loadCache();

        return static::$cache[$key] ?? $default;
    }

    /**
     * Set a setting value.
     */
    public static function setValue(string $key, ?string $value, bool $encrypted = false): void
    {
        $setting = static::find($key) ?? new self(['key' => $key]);

        if ($value === null) {
            $setting->delete();
            unset(static::$cache[$key]);

            return;
        }

        $setting->setEncryptedValue($value, $encrypted);
        $setting->save();

        static::$cache[$key] = $value;
    }

    /**
     * Flush the settings cache (useful for testing and Octane).
     */
    public static function flushCache(): void
    {
        static::$cache = [];
        static::$cacheLoaded = false;
    }
}
