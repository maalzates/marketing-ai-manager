<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Assets\Domain\Enums\AssetStatus;
use App\Modules\Assets\Domain\Enums\AssetType;
use App\Modules\Assets\Domain\Enums\MetaAssetType;
use App\Modules\Assets\Infrastructure\Persistence\Asset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
{
    protected $model = Asset::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'strategy_id' => null,
            'experiment_id' => null,
            'parent_asset_id' => null,
            'position' => null,
            'drive_file_id' => fake()->regexify('[A-Za-z0-9_-]{33}'),
            'drive_folder_id' => fake()->regexify('[A-Za-z0-9_-]{33}'),
            'name' => 'EXP-001_2026-08-23_reel_'.fake()->slug(3).'_v1.mp4',
            'type' => AssetType::Reel,
            'aspect_ratio' => '9:16',
            'duration_seconds' => fake()->numberBetween(10, 90),
            'size_bytes' => fake()->numberBetween(1_000_000, 80_000_000),
            'mime_type' => 'video/mp4',
            'status' => AssetStatus::Draft,
            'meta_asset_id' => null,
            'meta_asset_type' => null,
            'spec_warnings' => [],
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => AssetStatus::Draft]);
    }

    public function ready(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => AssetStatus::Ready]);
    }

    public function used(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AssetStatus::Used,
            'meta_asset_id' => fake()->regexify('[0-9]{15}'),
            'meta_asset_type' => MetaAssetType::VideoId,
        ]);
    }

    public function broken(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => AssetStatus::Broken]);
    }

    /** A carousel parent holds no Drive file of its own; its slides do. */
    public function carouselParent(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => AssetType::Carousel,
            'drive_file_id' => null,
            'aspect_ratio' => null,
            'duration_seconds' => null,
            'size_bytes' => null,
            'mime_type' => null,
            'name' => fake()->slug(3),
        ]);
    }
}
