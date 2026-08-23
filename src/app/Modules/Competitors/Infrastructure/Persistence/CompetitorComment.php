<?php

declare(strict_types=1);

namespace App\Modules\Competitors\Infrastructure\Persistence;

use Database\Factories\CompetitorCommentFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompetitorComment extends Model
{
    /** @use HasFactory<CompetitorCommentFactory> */
    use HasFactory;

    protected $fillable = [
        'account_id',
        'competitor_post_id',
        'external_id',
        'author',
        'text',
        'likes',
        'posted_at',
    ];

    /** @return BelongsTo<CompetitorPost, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(CompetitorPost::class, 'competitor_post_id');
    }

    protected static function newFactory(): Factory
    {
        return CompetitorCommentFactory::new();
    }

    protected function casts(): array
    {
        return [
            'likes' => 'integer',
            'posted_at' => 'immutable_datetime',
        ];
    }
}
