<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Experiments\Domain\Enums\Verdict;
use App\Modules\Proposals\Domain\Enums\ProposalOrigin;
use App\Modules\Proposals\Domain\Enums\ProposalStatus;
use App\Modules\Proposals\Domain\Enums\ProposalType;
use App\Modules\Proposals\Infrastructure\Persistence\Proposal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Proposal>
 */
class ProposalFactory extends Factory
{
    protected $model = Proposal::class;

    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'user_id' => null,
            'strategy_id' => null,
            'experiment_id' => null,
            'type' => ProposalType::CloseExperiment,
            'title' => fake()->sentence(5),
            'rationale' => fake()->sentence(15),
            'payload' => ['verdict' => Verdict::Inconclusive->value, 'reason' => fake()->sentence(8)],
            'status' => ProposalStatus::Pending,
            'origin' => ProposalOrigin::Guardian,
            'expires_at' => null,
            'decided_at' => null,
            'decided_by_user_id' => null,
            'execution_result' => null,
        ];
    }

    public function pending(): self
    {
        return $this->state(fn (): array => ['status' => ProposalStatus::Pending, 'decided_at' => null]);
    }

    public function accepted(): self
    {
        return $this->state(fn (): array => [
            'status' => ProposalStatus::Accepted,
            'decided_at' => CarbonImmutable::now(),
            'decided_by_user_id' => User::factory(),
        ]);
    }

    public function rejected(): self
    {
        return $this->state(fn (): array => [
            'status' => ProposalStatus::Rejected,
            'decided_at' => CarbonImmutable::now(),
            'decided_by_user_id' => User::factory(),
        ]);
    }

    public function executed(): self
    {
        return $this->state(fn (): array => [
            'status' => ProposalStatus::Executed,
            'decided_at' => CarbonImmutable::now(),
            'execution_result' => ['ok' => true],
        ]);
    }

    public function expired(): self
    {
        return $this->state(fn (): array => ['expires_at' => CarbonImmutable::now()->subDay()]);
    }

    public function ofType(ProposalType $type): self
    {
        return $this->state(fn (): array => ['type' => $type]);
    }
}
