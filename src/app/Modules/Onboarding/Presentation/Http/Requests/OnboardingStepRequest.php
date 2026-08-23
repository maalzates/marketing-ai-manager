<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Presentation\Http\Requests;

use App\Modules\Core\Application\Context\AccountContext;
use App\Modules\Core\Presentation\Http\Requests\RequestHelperTrait;
use App\Modules\Integrations\Domain\Enums\IntegrationProvider;
use App\Modules\Onboarding\Application\DTO\OnboardingStepDTO;
use App\Modules\Onboarding\Domain\Enums\OnboardingStep;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Serves both /steps/{step}/complete and /steps/{step}/skip. An unknown step fails here as
 * a 422 rather than reaching the service and blowing up on an enum cast.
 */
class OnboardingStepRequest extends FormRequest
{
    use RequestHelperTrait;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'step' => ['required', Rule::enum(OnboardingStep::class)],
            'provider' => ['nullable', Rule::enum(IntegrationProvider::class)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['step' => $this->route('step')]);
    }

    public function toDTO(): OnboardingStepDTO
    {
        $context = $this->container->make(AccountContext::class);

        return new OnboardingStepDTO(
            $context->accountId,
            $context->userId,
            $this->getEnumValue('step', OnboardingStep::class),
            $this->getEnumValue('provider', IntegrationProvider::class),
        );
    }
}
