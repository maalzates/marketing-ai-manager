<?php

declare(strict_types=1);

namespace App\Modules\Proposals\Infrastructure\Persistence;

use App\Modules\Proposals\Domain\Enums\ProposalOrigin;
use App\Modules\Proposals\Domain\Enums\ProposalStatus;
use App\Modules\Proposals\Domain\Enums\ProposalType;
use Database\Factories\ProposalFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Proposal extends Model
{
    /** @use HasFactory<ProposalFactory> */
    use HasFactory;

    protected $fillable = [
        'account_id',
        'user_id',
        'strategy_id',
        'experiment_id',
        'type',
        'title',
        'rationale',
        'payload',
        'status',
        'origin',
        'expires_at',
        'decided_at',
        'decided_by_user_id',
        'execution_result',
    ];

    public function isPending(): bool
    {
        return $this->status === ProposalStatus::Pending;
    }

    public function hasExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    protected static function newFactory(): Factory
    {
        return ProposalFactory::new();
    }

    protected function casts(): array
    {
        return [
            'type' => ProposalType::class,
            'status' => ProposalStatus::class,
            'origin' => ProposalOrigin::class,
            'payload' => 'array',
            'execution_result' => 'array',
            'expires_at' => 'immutable_datetime',
            'decided_at' => 'immutable_datetime',
        ];
    }
}
