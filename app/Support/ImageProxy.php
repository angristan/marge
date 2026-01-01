<?php

declare(strict_types=1);

namespace App\Support;

use Onliner\ImgProxy\Options\Dpr;
use Onliner\ImgProxy\Options\Height;
use Onliner\ImgProxy\Options\Width;
use Onliner\ImgProxy\UrlBuilder;

class ImageProxy
{
    /**
     * Check if image proxy is enabled.
     */
    public static function isEnabled(): bool
    {
        return config('services.imgproxy.url') !== null
            && config('services.imgproxy.key') !== null
            && config('services.imgproxy.salt') !== null;
    }

    /**
     * Build a proxified URL for an image, with resize and webp conversion.
     * Returns the original URL if image proxy is not configured.
     */
    public static function url(string $url, int $width = 80, int $height = 80): string
    {
        if (! self::isEnabled()) {
            return $url;
        }

        $uri = app(UrlBuilder::class)
            ->with(
                new Width($width),
                new Height($height),
                new Dpr(2),
            )
            ->url($url, 'webp');

        return config('services.imgproxy.url').$uri;
    }
}
