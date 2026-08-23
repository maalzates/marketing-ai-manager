<?php

declare(strict_types=1);

namespace App\Modules\Strategies\Presentation\Http\Requests;

use App\Modules\Core\Application\Context\AccountContext;
use App\Modules\Core\Presentation\Http\Requests\RequestHelperTrait;
use App\Modules\Strategies\Application\DTO\UpdateStrategyDTO;
use Illuminate\Foundation\Http\FormRequest;

class UpdateStrategyRequest extends FormRequest
{
    use RequestHelperTrait;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => ['required', 'integer', 'min:1'],
            'name' => ['nullable', 'string', 'max:255'],
            'objective' => ['nullable', 'string'],
            'north_star_metric' => ['nullable', 'string', 'max:255'],
            'monthly_budget' => ['nullable', 'numeric', 'min:0'],
            'constraints' => ['nullable', 'array'],
            'guardian_config' => ['nullable', 'array'],
            'guardian_config.enabled' => ['nullable', 'boolean'],
            'guardian_config.frequency_days' => ['nullable', 'integer', 'min:1'],
            'guardian_config.reports_enabled' => ['nullable', 'boolean'],
            'guardian_config.anomaly_multiplier' => ['nullable', 'numeric', 'min:1'],
            'organic_cadence' => ['nullable', 'array'],
            'organic_cadence.posts_per_week' => ['nullable', 'integer', 'min:0'],
            'organic_cadence.preferred_hours' => ['nullable', 'array'],
            'organic_cadence.preferred_hours.*' => ['integer', 'min:0', 'max:23'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['id' => $this->route('id')]);
    }

    public function toDTO(): UpdateStrategyDTO
    {
        return new UpdateStrategyDTO(
            $this->container->make(AccountContext::class)->accountId,
            $this->getIntegerValue('id'),
            $this->getStringValue('name'),
            $this->getStringValue('objective'),
            $this->getStringValue('north_star_metric'),
            $this->getFloatValue('monthly_budget'),
            $this->validated('constraints'),
            $this->validated('guardian_config'),
            $this->validated('organic_cadence'),
        );
    }
}
