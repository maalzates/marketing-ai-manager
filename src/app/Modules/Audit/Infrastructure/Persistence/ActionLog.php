<?php

declare(strict_types=1);

namespace App\Modules\Audit\Infrastructure\Persistence;

use App\Modules\Audit\Domain\Enums\ActionOrigin;
use Database\Factories\ActionLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActionLog extends Model
{
    /** @use HasFactory<ActionLogFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'account_id',
        'user_id',
        'action',
        'entity_type',
        'entity_id',
        'payload',
        'origin',
        'ip',
    ];

    protected static function newFactory(): ActionLogFactory
    {
        return ActionLogFactory::new();
    }

    protected function casts(): array
    {
        return [
            'account_id' => 'integer',
            'user_id' => 'integer',
            'entity_id' => 'integer',
            'payload' => 'array',
            'origin' => ActionOrigin::class,
            'created_at' => 'datetime',
        ];
    }
}
