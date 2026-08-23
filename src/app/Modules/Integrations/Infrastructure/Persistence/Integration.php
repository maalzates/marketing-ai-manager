<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Infrastructure\Persistence;

use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Integrations\Domain\Enums\IntegrationKind;
use App\Modules\Integrations\Domain\Enums\IntegrationProvider;
use App\Modules\Integrations\Domain\Enums\IntegrationStatus;
use Database\Factories\IntegrationFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Integration extends Model
{
    /** @use HasFactory<IntegrationFactory> */
    use HasFactory;

    protected $fillable = [
        'account_id',
        'provider',
        'kind',
        'credentials',
        'status',
        'external_account_id',
        'scopes',
        'expires_at',
        'last_checked_at',
        'last_error',
        'failure_count',
    ];

    /**
     * Hidden rather than merely absent from the resource: nothing that serialises a model
     * — a queued job payload, a dump, a future endpoint — may carry the decrypted key.
     */
    protected $hidden = ['credentials'];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    protected function casts(): array
    {
        return [
            'provider' => IntegrationProvider::class,
            'kind' => IntegrationKind::class,
            'status' => IntegrationStatus::class,
            'credentials' => 'encrypted:array',
            'scopes' => 'array',
            'expires_at' => 'immutable_datetime',
            'last_checked_at' => 'immutable_datetime',
            'failure_count' => 'integer',
        ];
    }

    protected static function newFactory(): Factory
    {
        return IntegrationFactory::new();
    }
}
