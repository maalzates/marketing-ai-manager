<?php

declare(strict_types=1);

namespace App\Modules\Experiments\Presentation\Http\Requests;

use App\Modules\Core\Application\Context\AccountContext;
use App\Modules\Core\Presentation\Http\Requests\RequestHelperTrait;
use App\Modules\Experiments\Application\DTO\UpdateExperimentDTO;
use App\Modules\Experiments\Domain\Enums\ExpectedResultOperator;
use App\Modules\Experiments\Domain\Enums\ExperimentPlatform;
use App\Modules\Experiments\Domain\Enums\ExperimentStatus;
use App\Modules\Experiments\Domain\Enums\ProductionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExperimentRequest extends FormRequest
{
    use RequestHelperTrait;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'platform' => ['nullable', Rule::enum(ExperimentPlatform::class)],
            'title' => ['nullable', 'string', 'max:191'],
            'hypothesis' => ['nullable', 'string'],
            'expected_result' => ['nullable', 'array'],
            'expected_result.metric' => ['nullable', 'string', 'max:64'],
            'expected_result.operator' => ['nullable', Rule::enum(ExpectedResultOperator::class)],
            'expected_result.value' => ['nullable', 'numeric'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'max_budget' => ['nullable', 'numeric', 'min:0'],
            'configuration' => ['nullable', 'array'],
            'status' => ['nullable', Rule::enum(ExperimentStatus::class)],
            'production_status' => ['nullable', Rule::enum(ProductionStatus::class)],
        ];
    }

    public function toDTO(): UpdateExperimentDTO
    {
        return new UpdateExperimentDTO(
            $this->container->make(AccountContext::class)->accountId,
            (int) $this->route('id'),
            $this->getEnumValue('platform', ExperimentPlatform::class),
            $this->getStringValue('title'),
            $this->getStringValue('hypothesis'),
            $this->getArrayValue('expected_result') ?: null,
            $this->date('starts_at')?->toImmutable(),
            $this->date('ends_at')?->toImmutable(),
            $this->getFloatValue('max_budget'),
            $this->getArrayValue('configuration') ?: null,
            $this->getEnumValue('status', ExperimentStatus::class),
            $this->getEnumValue('production_status', ProductionStatus::class),
        );
    }
}
