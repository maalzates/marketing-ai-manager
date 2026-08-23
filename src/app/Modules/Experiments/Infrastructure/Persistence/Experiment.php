<?php

declare(strict_types=1);

namespace App\Modules\Experiments\Infrastructure\Persistence;

use App\Modules\Experiments\Domain\Enums\ExperimentPlatform;
use App\Modules\Experiments\Domain\Enums\ExperimentStatus;
use App\Modules\Experiments\Domain\Enums\ExperimentType;
use App\Modules\Experiments\Domain\Enums\ProductionStatus;
use App\Modules\Experiments\Domain\Enums\Verdict;
use Database\Factories\ExperimentFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Experiment extends Model
{
    /** @use HasFactory<ExperimentFactory> */
    use HasFactory;

    protected $fillable = [
        'account_id',
        'strategy_id',
        'code',
        'type',
        'platform',
        'title',
        'hypothesis',
        'expected_result',
        'starts_at',
        'ends_at',
        'max_budget',
        'configuration',
        'status',
        'production_status',
        'spend_total',
        'learning_phase_ends_at',
        'verdict',
        'verdict_reason',
        'verdict_confirmed_at',
        'closed_early',
    ];

    public function warnings(): HasMany
    {
        return $this->hasMany(ExperimentWarning::class);
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(ExperimentMetric::class);
    }

    protected static function newFactory(): Factory
    {
        return ExperimentFactory::new();
    }

    protected function casts(): array
    {
        return [
            'type' => ExperimentType::class,
            'platform' => ExperimentPlatform::class,
            'status' => ExperimentStatus::class,
            'production_status' => ProductionStatus::class,
            'verdict' => Verdict::class,
            'expected_result' => 'array',
            'configuration' => 'array',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'learning_phase_ends_at' => 'immutable_datetime',
            'verdict_confirmed_at' => 'immutable_datetime',
            'max_budget' => 'decimal:2',
            'spend_total' => 'decimal:2',
            'closed_early' => 'boolean',
        ];
    }
}
