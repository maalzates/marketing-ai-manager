<?php

declare(strict_types=1);

namespace App\Modules\Assets\Application\Services;

use App\Modules\Assets\Application\DTO\DriveFileDTO;
use App\Modules\Assets\Domain\Enums\AssetType;
use App\Modules\Assets\Domain\Enums\SpecWarningCode;

/**
 * Placement requirements are recorded as warnings, never enforced: a piece that is out of spec
 * still uploads, because the user is the one who decides whether to publish it anyway.
 */
readonly class AssetSpecValidator
{
    private const int MAX_IMAGE_BYTES = 8 * 1024 * 1024;

    private const int MAX_REEL_BYTES = 300 * 1024 * 1024;

    private const int MAX_REEL_SECONDS = 15 * 60;

    private const string VERTICAL_RATIO = '9:16';

    private const array FEED_RATIOS = ['1:1', '4:5'];

    /**
     * @return list<array{code: string, message: string}>
     */
    public function warningsFor(AssetType $type, DriveFileDTO $file): array
    {
        return array_values(array_map(
            fn (SpecWarningCode $code): array => ['code' => $code->value, 'message' => $code->message()],
            array_filter([
                $this->ratioWarning($type, $file),
                $this->weightWarning($type, $file),
                $this->durationWarning($type, $file),
            ]),
        ));
    }

    private function ratioWarning(AssetType $type, DriveFileDTO $file): ?SpecWarningCode
    {
        if ($file->aspectRatio === null) {
            return null;
        }

        return match (true) {
            $type->isVerticalPlacement() && ! $file->aspectRatio->matches(self::VERTICAL_RATIO) => SpecWarningCode::AspectRatioNotVertical,
            $type->isFeedPlacement() && ! $file->aspectRatio->matchesAny(self::FEED_RATIOS) => SpecWarningCode::AspectRatioNotFeed,
            default => null,
        };
    }

    private function weightWarning(AssetType $type, DriveFileDTO $file): ?SpecWarningCode
    {
        return match (true) {
            $file->sizeBytes === null => null,
            $type->isVideo() && $file->sizeBytes > self::MAX_REEL_BYTES => SpecWarningCode::VideoTooHeavy,
            ! $type->isVideo() && $file->sizeBytes > self::MAX_IMAGE_BYTES => SpecWarningCode::ImageTooHeavy,
            default => null,
        };
    }

    private function durationWarning(AssetType $type, DriveFileDTO $file): ?SpecWarningCode
    {
        return $type->isVideo() && $file->durationSeconds > self::MAX_REEL_SECONDS
            ? SpecWarningCode::VideoTooLong
            : null;
    }
}
