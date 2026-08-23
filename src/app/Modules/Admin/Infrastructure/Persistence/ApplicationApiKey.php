<?php

declare(strict_types=1);

namespace App\Modules\Admin\Infrastructure\Persistence;

use App\Models\User;
use App\Modules\Accounts\Infrastructure\Persistence\Account;
use Database\Factories\ApplicationApiKeyFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * There is no column holding the issued token and no accessor that could rebuild it: the
 * row keeps a SHA-256 hash and the visible prefix, and that is the whole of what the
 * system can ever know about a key after the creation response.
 */
class ApplicationApiKey extends Model
{
    /** @use HasFactory<ApplicationApiKeyFactory> */
    use HasFactory;

    protected $fillable = [
        'account_id',
        'name',
        'prefix',
        'token_hash',
        'abilities',
        'last_used_at',
        'revoked_at',
        'created_by_user_id',
    ];

    /** The hash is useless to an attacker but still a credential derivative; nothing serialises it. */
    protected $hidden = ['token_hash'];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }

    protected function casts(): array
    {
        return [
            'abilities' => 'array',
            'last_used_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): Factory
    {
        return ApplicationApiKeyFactory::new();
    }
}
