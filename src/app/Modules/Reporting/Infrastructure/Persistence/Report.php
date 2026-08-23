<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Infrastructure\Persistence;

use App\Modules\Reporting\Domain\Enums\ReportType;
use Database\Factories\ReportFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    /** @use HasFactory<ReportFactory> */
    use HasFactory;

    protected $fillable = [
        'account_id',
        'strategy_id',
        'experiment_id',
        'type',
        'period_start',
        'period_end',
        'body',
        'data',
        'generated_at',
    ];

    protected static function newFactory(): Factory
    {
        return ReportFactory::new();
    }

    protected function casts(): array
    {
        return [
            'type' => ReportType::class,
            'data' => 'array',
            'period_start' => 'immutable_date',
            'period_end' => 'immutable_date',
            'generated_at' => 'immutable_datetime',
        ];
    }
}
