<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Infrastructure\Persistence;

use App\Modules\Accounts\Infrastructure\Persistence\Account;
use Database\Factories\OnboardingStateFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnboardingState extends Model
{
    /** @use HasFactory<OnboardingStateFactory> */
    use HasFactory;

    protected $fillable = ['account_id', 'steps', 'completed_at'];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    protected function casts(): array
    {
        return [
            'steps' => 'array',
            'completed_at' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): Factory
    {
        return OnboardingStateFactory::new();
    }
}
