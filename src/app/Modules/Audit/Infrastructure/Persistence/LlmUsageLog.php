<?php

declare(strict_types=1);

namespace App\Modules\Audit\Infrastructure\Persistence;

use Database\Factories\LlmUsageLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LlmUsageLog extends Model
{
    /** @use HasFactory<LlmUsageLogFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'account_id',
        'user_id',
        'feature',
        'provider',
        'model',
        'input_tokens',
        'output_tokens',
        'reasoning_tokens',
        'cached_input_tokens',
        'estimated_cost_usd',
    ];

    protected static function newFactory(): LlmUsageLogFactory
    {
        return LlmUsageLogFactory::new();
    }

    protected function casts(): array
    {
        return [
            'account_id' => 'integer',
            'user_id' => 'integer',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'reasoning_tokens' => 'integer',
            'cached_input_tokens' => 'integer',
            'estimated_cost_usd' => 'decimal:6',
            'created_at' => 'datetime',
        ];
    }
}
