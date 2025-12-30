<?php

declare(strict_types=1);

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;

/**
 * Cast for binary blob columns that works correctly with PostgreSQL.
 *
 * PostgreSQL PDO has issues with binary data containing null bytes - they get
 * truncated. This cast uses PostgreSQL's decode() function to work around this.
 *
 * @implements CastsAttributes<string|null, string|null|Expression>
 */
class BinaryBlob implements CastsAttributes
{
    /**
     * Cast the given value (from database).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        // PostgreSQL returns bytea as stream resources
        if (is_resource($value)) {
            rewind($value);
            $value = stream_get_contents($value);

            if ($value === false || $value === '') {
                return null;
            }
        }

        // Handle PostgreSQL hex-encoded bytea format (e.g., \x00ff...)
        if (is_string($value) && str_starts_with($value, '\\x')) {
            $decoded = hex2bin(substr($value, 2));

            return $decoded === false ? null : $decoded;
        }

        return $value;
    }

    /**
     * Prepare the given value for storage (to database).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): Expression|string|null
    {
        if ($value === null) {
            return null;
        }

        // For PostgreSQL, use decode() function to avoid null byte truncation
        if (DB::connection()->getDriverName() === 'pgsql') {
            $hex = bin2hex($value);

            return DB::raw("decode('{$hex}', 'hex')");
        }

        return $value;
    }
}
