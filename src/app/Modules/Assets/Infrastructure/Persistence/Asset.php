<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Persistence;

use App\Modules\Assets\Domain\Enums\AssetStatus;
use App\Modules\Assets\Domain\Enums\AssetType;
use App\Modules\Assets\Domain\Enums\MetaAssetType;
use Database\Factories\AssetFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Asset extends Model
{
    /** @use HasFactory<AssetFactory> */
    use HasFactory;

    protected $fillable = [
        'account_id',
        'strategy_id',
        'experiment_id',
        'parent_asset_id',
        'position',
        'drive_file_id',
        'drive_folder_id',
        'name',
        'type',
        'aspect_ratio',
        'duration_seconds',
        'size_bytes',
        'mime_type',
        'status',
        'meta_asset_id',
        'meta_asset_type',
        'spec_warnings',
    ];

    /** The json column is not nullable, so every insert needs a shape even when empty. */
    protected $attributes = [
        'spec_warnings' => '[]',
        'status' => AssetStatus::Draft->value,
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_asset_id');
    }

    /** The carousel order lives here, never in the Drive filename. */
    public function slides(): HasMany
    {
        return $this->hasMany(self::class, 'parent_asset_id')->orderBy('position');
    }

    protected static function newFactory(): Factory
    {
        return AssetFactory::new();
    }

    protected function casts(): array
    {
        return [
            'type' => AssetType::class,
            'status' => AssetStatus::class,
            'meta_asset_type' => MetaAssetType::class,
            'spec_warnings' => 'array',
            'position' => 'integer',
            'duration_seconds' => 'integer',
            'size_bytes' => 'integer',
        ];
    }
}
