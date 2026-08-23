<?php

declare(strict_types=1);

namespace App\Modules\Competitors\Infrastructure\Persistence;

use App\Modules\Competitors\Domain\Enums\CompetitorPlatform;
use Database\Factories\CompetitorFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Competitor extends Model
{
    /** @use HasFactory<CompetitorFactory> */
    use HasFactory;

    protected $fillable = [
        'account_id',
        'strategy_id',
        'platform',
        'handle',
        'external_id',
        'display_name',
        'is_active',
        'last_synced_at',
    ];

    /** @return HasMany<CompetitorPost, $this> */
    public function posts(): HasMany
    {
        return $this->hasMany(CompetitorPost::class);
    }

    protected static function newFactory(): Factory
    {
        return CompetitorFactory::new();
    }

    protected function casts(): array
    {
        return [
            'platform' => CompetitorPlatform::class,
            'is_active' => 'boolean',
            'last_synced_at' => 'immutable_datetime',
        ];
    }
}
