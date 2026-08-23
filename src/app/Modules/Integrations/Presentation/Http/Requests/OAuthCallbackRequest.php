<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Presentation\Http\Requests;

use App\Modules\Core\Presentation\Http\Requests\RequestHelperTrait;
use App\Modules\Integrations\Application\DTO\OAuthCallbackDTO;
use App\Modules\Integrations\Domain\Enums\IntegrationProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OAuthCallbackRequest extends FormRequest
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
            'code' => ['nullable', 'string', 'max:2048'],
            'state' => ['nullable', 'string', 'max:2048'],
            'error' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['provider' => $this->route('provider')]);
    }

    public function toDTO(): OAuthCallbackDTO
    {
        return new OAuthCallbackDTO(
            $this->getEnumValue('provider', IntegrationProvider::class),
            $this->getStringValue('code'),
            $this->getStringValue('state'),
            $this->getStringValue('error'),
        );
    }
}
