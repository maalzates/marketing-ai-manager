<?php

declare(strict_types=1);

namespace App\Modules\Ai\Infrastructure\Persistence;

use Database\Factories\AiAnalysisCacheEntryFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiAnalysisCacheEntry extends Model
{
    /** @use HasFactory<AiAnalysisCacheEntryFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'ai_analysis_cache';

    protected $fillable = [
        'account_id',
        'kind',
        'input_hash',
        'result',
    ];

    protected function casts(): array
    {
        return [
            'result' => 'array',
        ];
    }

    protected static function newFactory(): Factory
    {
        return AiAnalysisCacheEntryFactory::new();
    }
}
