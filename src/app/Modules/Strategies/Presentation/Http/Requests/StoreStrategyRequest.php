<?php

declare(strict_types=1);

namespace App\Modules\Strategies\Presentation\Http\Requests;

use App\Modules\Core\Application\Context\AccountContext;
use App\Modules\Core\Presentation\Http\Requests\RequestHelperTrait;
use App\Modules\Strategies\Application\DTO\CreateStrategyDTO;
use App\Modules\Strategies\Domain\Enums\NorthStarMetric;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStrategyRequest extends FormRequest
{
    use RequestHelperTrait;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'brand_profile_id' => ['required', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:255'],
            'objective' => ['required', 'string'],
            'north_star_metric' => ['required', Rule::enum(NorthStarMetric::class)],
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

    public function toDTO(): CreateStrategyDTO
    {
        return new CreateStrategyDTO(
            $this->container->make(AccountContext::class)->accountId,
            $this->getIntegerValue('brand_profile_id'),
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
