<?php

declare(strict_types=1);

namespace App\Modules\Experiments\Presentation\Http\Requests;

use App\Modules\Core\Application\Context\AccountContext;
use App\Modules\Core\Presentation\Http\Requests\RequestHelperTrait;
use App\Modules\Experiments\Application\DTO\ExperimentFilterDTO;
use App\Modules\Experiments\Domain\Enums\ExperimentStatus;
use App\Modules\Experiments\Domain\Enums\ExperimentType;
use App\Modules\Experiments\Domain\Enums\Verdict;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexExperimentRequest extends FormRequest
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
            'status' => ['nullable', Rule::enum(ExperimentStatus::class)],
            'type' => ['nullable', Rule::enum(ExperimentType::class)],
            'verdict' => ['nullable', Rule::enum(Verdict::class)],
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

    public function toDTO(): ExperimentFilterDTO
    {
        return new ExperimentFilterDTO(
            $this->container->make(AccountContext::class)->accountId,
            (int) $this->route('strategy'),
            $this->getEnumValue('status', ExperimentStatus::class),
            $this->getEnumValue('type', ExperimentType::class),
            $this->getEnumValue('verdict', Verdict::class),
            $this->getIntegerValue('per_page', self::DEFAULT_PER_PAGE),
            $this->getIntegerValue('page', 1),
        );
    }
}
