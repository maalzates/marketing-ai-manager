<?php

declare(strict_types=1);

namespace App\Modules\Content\Infrastructure\Persistence;

use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Content\Domain\Enums\ScheduleStatus;
use App\Modules\Experiments\Domain\Enums\ExperimentPlatform;
use App\Modules\Experiments\Infrastructure\Persistence\Experiment;
use Database\Factories\ContentScheduleFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentSchedule extends Model
{
    /** @use HasFactory<ContentScheduleFactory> */
    use HasFactory;

    protected $fillable = [
        'account_id',
        'experiment_id',
        'asset_id',
        'platform',
        'scheduled_at',
        'published_at',
        'status',
        'attempts',
        'last_error',
        'external_post_id',
    ];

    protected $attributes = [
        'status' => ScheduleStatus::Pending->value,
        'attempts' => 0,
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function experiment(): BelongsTo
    {
        return $this->belongsTo(Experiment::class);
    }

    protected static function newFactory(): Factory
    {
        return ContentScheduleFactory::new();
    }

    protected function casts(): array
    {
        return [
            'platform' => ExperimentPlatform::class,
            'status' => ScheduleStatus::class,
            'scheduled_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
            'attempts' => 'integer',
        ];
    }
}
