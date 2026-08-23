<?php

declare(strict_types=1);

namespace App\Modules\Competitors\Infrastructure\Persistence;

use App\Modules\Competitors\Domain\Enums\Sentiment;
use Database\Factories\CompetitorPostFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompetitorPost extends Model
{
    /** @use HasFactory<CompetitorPostFactory> */
    use HasFactory;

    protected $fillable = [
        'account_id',
        'competitor_id',
        'external_id',
        'url',
        'type',
        'caption',
        'posted_at',
        'likes',
        'comments_count',
        'views',
        'engagement_rate',
        'sentiment',
        'sentiment_summary',
        'raw',
    ];

    protected $attributes = [
        'raw' => '[]',
    ];

    /** @return BelongsTo<Competitor, $this> */
    public function competitor(): BelongsTo
    {
        return $this->belongsTo(Competitor::class);
    }

    /** @return HasMany<CompetitorComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(CompetitorComment::class);
    }

    protected static function newFactory(): Factory
    {
        return CompetitorPostFactory::new();
    }

    protected function casts(): array
    {
        return [
            'posted_at' => 'immutable_datetime',
            'likes' => 'integer',
            'comments_count' => 'integer',
            'views' => 'integer',
            'engagement_rate' => 'decimal:4',
            'sentiment' => Sentiment::class,
            'sentiment_summary' => 'array',
            'raw' => 'array',
        ];
    }
}
