<?php

declare(strict_types=1);

namespace App\Modules\Audit\Infrastructure\Persistence;

use Database\Factories\ApifyUsageLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApifyUsageLog extends Model
{
    /** @use HasFactory<ApifyUsageLogFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'account_id',
        'actor_id',
        'run_id',
        'results_count',
        'estimated_cost_usd',
    ];

    protected static function newFactory(): ApifyUsageLogFactory
    {
        return ApifyUsageLogFactory::new();
    }

    protected function casts(): array
    {
        return [
            'account_id' => 'integer',
            'results_count' => 'integer',
            'estimated_cost_usd' => 'decimal:6',
            'created_at' => 'datetime',
        ];
    }
}
