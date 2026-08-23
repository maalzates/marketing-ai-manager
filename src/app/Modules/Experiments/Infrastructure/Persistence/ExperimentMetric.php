<?php

declare(strict_types=1);

namespace App\Modules\Experiments\Infrastructure\Persistence;

use Database\Factories\ExperimentMetricFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExperimentMetric extends Model
{
    /** @use HasFactory<ExperimentMetricFactory> */
    use HasFactory;

    protected $fillable = [
        'account_id',
        'experiment_id',
        'date',
        'spend',
        'impressions',
        'reach',
        'clicks',
        'ctr',
        'cpm',
        'cpc',
        'conversions',
        'cpa',
        'frequency',
        'video_views',
        'engagement',
        'raw',
    ];

    protected static function newFactory(): Factory
    {
        return ExperimentMetricFactory::new();
    }

    protected function casts(): array
    {
        return [
            'date' => 'immutable_date',
            'raw' => 'array',
            'spend' => 'decimal:2',
            'ctr' => 'decimal:4',
            'cpm' => 'decimal:4',
            'cpc' => 'decimal:4',
            'cpa' => 'decimal:4',
            'frequency' => 'decimal:4',
            'impressions' => 'integer',
            'reach' => 'integer',
            'clicks' => 'integer',
            'conversions' => 'integer',
            'video_views' => 'integer',
            'engagement' => 'integer',
        ];
    }
}
