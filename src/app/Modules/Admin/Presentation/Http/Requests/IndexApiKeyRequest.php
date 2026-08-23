<?php

declare(strict_types=1);

namespace App\Modules\Admin\Presentation\Http\Requests;

use App\Modules\Admin\Application\DTO\ApiKeyFilterDTO;
use App\Modules\Core\Presentation\Http\Requests\RequestHelperTrait;
use Illuminate\Foundation\Http\FormRequest;

class IndexApiKeyRequest extends FormRequest
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
            'account_id' => ['nullable', 'integer', 'min:1'],
            'include_revoked' => ['nullable', 'boolean'],
            'per_page' => ['required', 'integer', 'min:1', 'max:100'],
            'page' => ['required', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'include_revoked' => $this->boolean('include_revoked'),
            'per_page' => $this->input('per_page', self::DEFAULT_PER_PAGE),
            'page' => $this->input('page', 1),
        ]);
    }

    public function toDTO(): ApiKeyFilterDTO
    {
        return new ApiKeyFilterDTO(
            $this->getIntegerValue('account_id'),
            $this->getBooleanValue('include_revoked', false),
            $this->getIntegerValue('per_page', self::DEFAULT_PER_PAGE),
            $this->getIntegerValue('page', 1),
        );
    }
}
