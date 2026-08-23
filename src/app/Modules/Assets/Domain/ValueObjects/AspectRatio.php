<?php

declare(strict_types=1);

namespace App\Modules\Assets\Domain\ValueObjects;

readonly class AspectRatio
{
    /** Reduced fractions of real encoder output are unreadable (1078x1920 is "539:960"), so a
     *  piece is reported as the placement ratio it actually satisfies. */
    private const array PLACEMENT_RATIOS = ['9:16', '16:9', '1:1', '4:5', '5:4', '2:3', '3:2'];

    public function __construct(public int $width, public int $height) {}

    public static function fromDimensions(?int $width, ?int $height): ?self
    {
        return $width > 0 && $height > 0 ? new self($width, $height) : null;
    }

    public function label(): string
    {
        return array_find(self::PLACEMENT_RATIOS, fn (string $ratio): bool => $this->matches($ratio))
            ?? sprintf('%d:%d', intdiv($this->width, $this->divisor()), intdiv($this->height, $this->divisor()));
    }

    /**
     * Encoders round dimensions, so 1078x1920 is a 9:16 piece in every way that matters to a
     * placement check. Comparing the decimal ratio within a tolerance is the only test that
     * survives real files.
     */
    public function matches(string $ratio, float $tolerance = 0.02): bool
    {
        [$width, $height] = array_map('intval', explode(':', $ratio));

        return abs(($this->width / $this->height) - ($width / $height)) <= $tolerance;
    }

    /**
     * @param  list<string>  $ratios
     */
    public function matchesAny(array $ratios): bool
    {
        return array_any($ratios, fn (string $ratio): bool => $this->matches($ratio));
    }

    private function divisor(): int
    {
        $divisor = $this->width;
        $remainder = $this->height;

        while ($remainder !== 0) {
            [$divisor, $remainder] = [$remainder, $divisor % $remainder];
        }

        return max(1, abs($divisor));
    }
}
