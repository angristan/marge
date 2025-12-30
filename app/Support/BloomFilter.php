<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Simple Bloom filter for tracking upvotes.
 *
 * Uses hex-encoded string storage for database compatibility.
 * Prevents duplicate votes while maintaining voter privacy.
 */
class BloomFilter
{
    /**
     * Default filter size in bytes.
     * 128 bytes = 1024 bits, suitable for ~100 items with ~1% false positive rate.
     */
    private const DEFAULT_SIZE = 128;

    /**
     * Number of hash functions to use.
     */
    private const NUM_HASHES = 3;

    private string $filter;

    private int $size;

    public function __construct(?string $filter = null, int $size = self::DEFAULT_SIZE)
    {
        $this->size = $size;
        $this->filter = $filter ?? str_repeat("\0", $size);
    }

    /**
     * Create from a hex-encoded string (as stored in database).
     */
    public static function fromHex(?string $hex): self
    {
        if ($hex === null || $hex === '') {
            return new self;
        }

        // Validate hex string before decoding
        if (! ctype_xdigit($hex)) {
            return new self;
        }

        $binary = hex2bin($hex);

        if ($binary === false) {
            return new self;
        }

        return new self($binary, strlen($binary));
    }

    /**
     * Get hex-encoded string for database storage.
     */
    public function toHex(): string
    {
        return bin2hex($this->filter);
    }

    /**
     * Add an item to the filter.
     */
    public function add(string $item): void
    {
        foreach ($this->getHashPositions($item) as $position) {
            $byteIndex = intdiv($position, 8);
            $bitIndex = $position % 8;
            $this->filter[$byteIndex] = chr(ord($this->filter[$byteIndex]) | (1 << $bitIndex));
        }
    }

    /**
     * Check if an item might be in the filter.
     * Returns true if item might exist (could be false positive).
     * Returns false if item definitely does not exist.
     */
    public function mightContain(string $item): bool
    {
        foreach ($this->getHashPositions($item) as $position) {
            $byteIndex = intdiv($position, 8);
            $bitIndex = $position % 8;

            if ((ord($this->filter[$byteIndex]) & (1 << $bitIndex)) === 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Generate hash positions for an item.
     *
     * @return array<int>
     */
    private function getHashPositions(string $item): array
    {
        $positions = [];
        $totalBits = $this->size * 8;

        // Use different salts for each hash function
        for ($i = 0; $i < self::NUM_HASHES; $i++) {
            $hash = hash('xxh3', $item.$i, true);
            // Use first 4 bytes as unsigned 32-bit integer
            $value = unpack('N', $hash)[1];
            $positions[] = $value % $totalBits;
        }

        return $positions;
    }

    /**
     * Create a voter identifier from IP and user agent.
     */
    public static function createVoterId(?string $ip, ?string $userAgent): string
    {
        return hash('sha256', ($ip ?? '').':'.($userAgent ?? ''));
    }
}
