<?php

declare(strict_types=1);

use App\Support\ImageProxy;

uses(Tests\TestCase::class);

describe('ImageProxy', function (): void {
    it('returns false when not configured', function (): void {
        config(['services.imgproxy.url' => null]);
        config(['services.imgproxy.key' => null]);
        config(['services.imgproxy.salt' => null]);

        expect(ImageProxy::isEnabled())->toBeFalse();
    });

    it('returns false when partially configured', function (): void {
        config(['services.imgproxy.url' => 'https://imgproxy.example.com']);
        config(['services.imgproxy.key' => null]);
        config(['services.imgproxy.salt' => null]);

        expect(ImageProxy::isEnabled())->toBeFalse();
    });

    it('returns true when fully configured', function (): void {
        config(['services.imgproxy.url' => 'https://imgproxy.example.com']);
        config(['services.imgproxy.key' => 'abc123']);
        config(['services.imgproxy.salt' => 'def456']);

        expect(ImageProxy::isEnabled())->toBeTrue();
    });

    it('returns original URL when not configured', function (): void {
        config(['services.imgproxy.url' => null]);
        config(['services.imgproxy.key' => null]);
        config(['services.imgproxy.salt' => null]);

        $url = 'https://gravatar.com/avatar/abc123';
        expect(ImageProxy::url($url))->toBe($url);
    });
});
