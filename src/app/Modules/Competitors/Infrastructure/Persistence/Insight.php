<?php

declare(strict_types=1);

namespace App\Modules\Competitors\Infrastructure\Persistence;

use App\Modules\Competitors\Domain\Enums\InsightKind;
use App\Modules\Competitors\Domain\Enums\InsightSource;
use App\Modules\Competitors\Domain\Enums\InsightStatus;
use Database\Factories\InsightFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Insight extends Model
{
    /** @use HasFactory<InsightFactory> */
    use HasFactory;

    protected $fillable = [
        'account_id',
        'strategy_id',
        'competitor_id',
        'kind',
        'title',
        'body',
        'evidence',
        'score',
        'source',
        'status',
    ];

    protected $attributes = [
        'evidence' => '[]',
        'status' => InsightStatus::New->value,
    ];

    protected static function newFactory(): Factory
    {
        return InsightFactory::new();
    }

    protected function casts(): array
    {
        return [
            'kind' => InsightKind::class,
            'source' => InsightSource::class,
            'status' => InsightStatus::class,
            'evidence' => 'array',
            'score' => 'decimal:3',
        ];
    }
}
