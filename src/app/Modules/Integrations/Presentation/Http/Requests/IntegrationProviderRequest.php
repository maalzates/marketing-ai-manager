<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Presentation\Http\Requests;

use App\Modules\Core\Presentation\Http\Requests\RequestHelperTrait;
use App\Modules\Integrations\Domain\Enums\IntegrationProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IntegrationProviderRequest extends FormRequest
{
    use RequestHelperTrait;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'provider' => ['required', Rule::enum(IntegrationProvider::class)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['provider' => $this->route('provider')]);
    }

    public function provider(): IntegrationProvider
    {
        return $this->getEnumValue('provider', IntegrationProvider::class);
    }
}
