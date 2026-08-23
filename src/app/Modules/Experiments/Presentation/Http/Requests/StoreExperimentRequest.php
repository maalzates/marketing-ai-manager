<?php

declare(strict_types=1);

namespace App\Modules\Experiments\Presentation\Http\Requests;

use App\Modules\Core\Application\Context\AccountContext;
use App\Modules\Core\Presentation\Http\Requests\RequestHelperTrait;
use App\Modules\Experiments\Application\DTO\CreateExperimentDTO;
use App\Modules\Experiments\Domain\Enums\ExpectedResultOperator;
use App\Modules\Experiments\Domain\Enums\ExperimentPlatform;
use App\Modules\Experiments\Domain\Enums\ExperimentStatus;
use App\Modules\Experiments\Domain\Enums\ExperimentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExperimentRequest extends FormRequest
{
    use RequestHelperTrait;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * expected_result and ends_at are `nullable` on purpose: their absence is a domain
     * invariant the Service owns, so the chat and job doors get the same 422 the API does.
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(ExperimentType::class)],
            'platform' => ['required', Rule::enum(ExperimentPlatform::class)],
            'title' => ['required', 'string', 'max:191'],
            'hypothesis' => ['required', 'string'],
            'expected_result' => ['nullable', 'array'],
            'expected_result.metric' => ['nullable', 'string', 'max:64'],
            'expected_result.operator' => ['nullable', Rule::enum(ExpectedResultOperator::class)],
            'expected_result.value' => ['nullable', 'numeric'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'max_budget' => ['nullable', 'numeric', 'min:0'],
            'configuration' => ['nullable', 'array'],
            'status' => ['required', Rule::enum(ExperimentStatus::class)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'status' => $this->input('status', ExperimentStatus::Draft->value),
            'configuration' => $this->input('configuration', []),
        ]);
    }

    public function toDTO(): CreateExperimentDTO
    {
        return new CreateExperimentDTO(
            $this->container->make(AccountContext::class)->accountId,
            (int) $this->route('strategy'),
            $this->getEnumValue('type', ExperimentType::class),
            $this->getEnumValue('platform', ExperimentPlatform::class),
            $this->getStringValue('title'),
            $this->getStringValue('hypothesis'),
            $this->getArrayValue('expected_result') ?: null,
            $this->date('starts_at')->toImmutable(),
            $this->date('ends_at')?->toImmutable(),
            $this->getFloatValue('max_budget'),
            $this->getArrayValue('configuration'),
            $this->getEnumValue('status', ExperimentStatus::class, ExperimentStatus::Draft),
        );
    }
}
