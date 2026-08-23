<?php

declare(strict_types=1);

namespace App\Modules\Strategies\Infrastructure\Persistence;

use App\Modules\Strategies\Domain\Enums\StrategyStatus;
use Database\Factories\StrategyFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Strategy extends Model
{
    /** @use HasFactory<StrategyFactory> */
    use HasFactory;

    protected $fillable = [
        'account_id',
        'brand_profile_id',
        'name',
        'objective',
        'north_star_metric',
        'monthly_budget',
        'constraints',
        'guardian_config',
        'organic_cadence',
        'status',
    ];

    /** The json columns are not nullable, so every insert needs a shape even when empty. */
    protected $attributes = [
        'constraints' => '[]',
        'guardian_config' => '{"enabled":true,"frequency_days":1,"reports_enabled":true,"anomaly_multiplier":3}',
        'organic_cadence' => '{"posts_per_week":3,"preferred_hours":[]}',
        'status' => StrategyStatus::Active->value,
    ];

    protected static function newFactory(): Factory
    {
        return StrategyFactory::new();
    }

    protected function casts(): array
    {
        return [
            'status' => StrategyStatus::class,
            'monthly_budget' => 'decimal:2',
            'constraints' => 'array',
            'guardian_config' => 'array',
            'organic_cadence' => 'array',
        ];
    }
}
