<?php

declare(strict_types=1);

namespace App\Modules\Proposals\Presentation\Http\Requests;

use App\Modules\Core\Application\Context\AccountContext;
use App\Modules\Core\Presentation\Http\Requests\RequestHelperTrait;
use App\Modules\Proposals\Application\DTO\ProposalFilterDTO;
use App\Modules\Proposals\Domain\Enums\ProposalOrigin;
use App\Modules\Proposals\Domain\Enums\ProposalStatus;
use App\Modules\Proposals\Domain\Enums\ProposalType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexProposalRequest extends FormRequest
{
    use RequestHelperTrait;

    private const int DEFAULT_PER_PAGE = 25;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(ProposalStatus::class)],
            'type' => ['nullable', Rule::enum(ProposalType::class)],
            'origin' => ['nullable', Rule::enum(ProposalOrigin::class)],
            'strategy_id' => ['nullable', 'integer'],
            'experiment_id' => ['nullable', 'integer'],
            'per_page' => ['required', 'integer', 'min:1', 'max:100'],
            'page' => ['required', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'per_page' => $this->input('per_page', self::DEFAULT_PER_PAGE),
            'page' => $this->input('page', 1),
        ]);
    }

    public function toDTO(): ProposalFilterDTO
    {
        return new ProposalFilterDTO(
            $this->container->make(AccountContext::class)->accountId,
            $this->getEnumValue('status', ProposalStatus::class),
            $this->getEnumValue('type', ProposalType::class),
            $this->getEnumValue('origin', ProposalOrigin::class),
            $this->getIntegerValue('strategy_id'),
            $this->getIntegerValue('experiment_id'),
            $this->getIntegerValue('per_page', self::DEFAULT_PER_PAGE),
            $this->getIntegerValue('page', 1),
        );
    }
}
