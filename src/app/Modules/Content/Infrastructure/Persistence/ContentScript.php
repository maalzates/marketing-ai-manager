<?php

declare(strict_types=1);

namespace App\Modules\Content\Infrastructure\Persistence;

use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Content\Domain\Enums\ContentFormat;
use App\Modules\Content\Domain\Enums\ScriptStatus;
use App\Modules\Experiments\Infrastructure\Persistence\Experiment;
use App\Modules\Strategies\Infrastructure\Persistence\Strategy;
use Database\Factories\ContentScriptFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentScript extends Model
{
    /** @use HasFactory<ContentScriptFactory> */
    use HasFactory;

    protected $fillable = [
        'account_id',
        'strategy_id',
        'experiment_id',
        'title',
        'hook',
        'structure',
        'cta',
        'format',
        'required_assets',
        'source_insight_ids',
        'status',
    ];

    /** The json columns are not nullable, so every insert needs a shape even when empty. */
    protected $attributes = [
        'structure' => '[]',
        'required_assets' => '[]',
        'source_insight_ids' => '[]',
        'status' => ScriptStatus::Draft->value,
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function strategy(): BelongsTo
    {
        return $this->belongsTo(Strategy::class);
    }

    public function experiment(): BelongsTo
    {
        return $this->belongsTo(Experiment::class);
    }

    protected static function newFactory(): Factory
    {
        return ContentScriptFactory::new();
    }

    protected function casts(): array
    {
        return [
            'format' => ContentFormat::class,
            'status' => ScriptStatus::class,
            'structure' => 'array',
            'required_assets' => 'array',
            'source_insight_ids' => 'array',
        ];
    }
}
