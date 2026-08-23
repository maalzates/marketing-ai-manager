<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Presentation\Http\Requests;

use App\Modules\Core\Presentation\Http\Requests\RequestHelperTrait;
use App\Modules\Integrations\Application\DTO\ConnectApiKeyDTO;
use App\Modules\Integrations\Domain\Enums\IntegrationProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConnectIntegrationRequest extends FormRequest
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
            'api_key' => ['required', 'string', 'min:8', 'max:512'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'provider' => $this->route('provider'),
            'api_key' => trim((string) $this->input('api_key')),
        ]);
    }

    public function toDTO(int $accountId): ConnectApiKeyDTO
    {
        return new ConnectApiKeyDTO(
            $accountId,
            $this->getEnumValue('provider', IntegrationProvider::class),
            $this->getStringValue('api_key'),
        );
    }
}
