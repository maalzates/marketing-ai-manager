<?php

declare(strict_types=1);

namespace App\Modules\Experiments\Infrastructure\Persistence;

use App\Modules\Experiments\Domain\Enums\WarningSeverity;
use Database\Factories\ExperimentWarningFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExperimentWarning extends Model
{
    /** @use HasFactory<ExperimentWarningFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'account_id',
        'experiment_id',
        'code',
        'message',
        'severity',
        'applies_from',
        'applies_to',
    ];

    protected static function newFactory(): Factory
    {
        return ExperimentWarningFactory::new();
    }

    protected function casts(): array
    {
        return [
            'severity' => WarningSeverity::class,
            'applies_from' => 'immutable_datetime',
            'applies_to' => 'immutable_datetime',
        ];
    }
}
