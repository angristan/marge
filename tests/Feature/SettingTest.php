<?php

declare(strict_types=1);

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Setting::flushCache();
});

it('loads all settings in a single query on first access', function (): void {
    Setting::setValue('key1', 'value1');
    Setting::setValue('key2', 'value2');
    Setting::setValue('key3', 'value3');
    Setting::flushCache();

    DB::enableQueryLog();

    $value1 = Setting::getValue('key1');
    $value2 = Setting::getValue('key2');
    $value3 = Setting::getValue('key3');

    $queries = DB::getQueryLog();

    expect($value1)->toBe('value1')
        ->and($value2)->toBe('value2')
        ->and($value3)->toBe('value3')
        ->and($queries)->toHaveCount(1)
        ->and($queries[0]['query'])->toContain('select * from "settings"');
});

it('returns default value for missing keys', function (): void {
    expect(Setting::getValue('missing', 'default'))->toBe('default')
        ->and(Setting::getValue('missing'))->toBeNull();
});

it('updates cache when setting a value', function (): void {
    Setting::setValue('key', 'initial');
    expect(Setting::getValue('key'))->toBe('initial');

    Setting::setValue('key', 'updated');
    expect(Setting::getValue('key'))->toBe('updated');
});

it('removes from cache when setting null', function (): void {
    Setting::setValue('key', 'value');
    expect(Setting::getValue('key'))->toBe('value');

    Setting::setValue('key', null);
    expect(Setting::getValue('key'))->toBeNull();
});

it('flushes cache and reloads on next access', function (): void {
    Setting::setValue('key', 'value');
    Setting::flushCache();

    DB::enableQueryLog();
    DB::flushQueryLog();

    Setting::getValue('key');
    expect(DB::getQueryLog())->toHaveCount(1);

    DB::flushQueryLog();
    Setting::getValue('key');
    expect(DB::getQueryLog())->toHaveCount(0);
});
