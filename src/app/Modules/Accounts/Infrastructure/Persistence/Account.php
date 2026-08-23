<?php

declare(strict_types=1);

namespace App\Modules\Accounts\Infrastructure\Persistence;

use App\Models\User;
use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Account extends Model
{
    /** @use HasFactory<AccountFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'owner_user_id',
        'currency',
        'timezone',
        'sandbox_mode',
        'is_active',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    protected function casts(): array
    {
        return [
            'sandbox_mode' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): Factory
    {
        return AccountFactory::new();
    }
}
