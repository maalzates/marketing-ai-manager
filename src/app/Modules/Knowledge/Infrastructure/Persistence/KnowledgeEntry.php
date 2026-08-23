<?php

declare(strict_types=1);

namespace App\Modules\Knowledge\Infrastructure\Persistence;

use App\Modules\Knowledge\Domain\Enums\KnowledgeType;
use Database\Factories\KnowledgeEntryFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Platform content, not tenant data: there is no `account_id` on purpose. The admin owns
 * these entries and every account reads the same ones.
 */
class KnowledgeEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'key',
        'locale',
        'title',
        'body',
        'metadata',
        'version',
        'is_published',
    ];

    protected static function newFactory(): Factory
    {
        return KnowledgeEntryFactory::new();
    }

    protected function casts(): array
    {
        return [
            'type' => KnowledgeType::class,
            'metadata' => 'array',
            'version' => 'integer',
            'is_published' => 'boolean',
        ];
    }
}
