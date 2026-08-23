<?php

declare(strict_types=1);

namespace App\Modules\Admin\Presentation\Http\Requests;

use App\Modules\Admin\Application\DTO\CreateApiKeyDTO;
use App\Modules\Core\Presentation\Http\Requests\RequestHelperTrait;
use Illuminate\Foundation\Http\FormRequest;

class StoreApiKeyRequest extends FormRequest
{
    use RequestHelperTrait;

    /** @var list<string> */
    private const array DEFAULT_ABILITIES = ['*'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'name' => ['required', 'string', 'max:191'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['string', 'max:64'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['abilities' => $this->input('abilities', self::DEFAULT_ABILITIES)]);
    }

    public function toDTO(): CreateApiKeyDTO
    {
        return new CreateApiKeyDTO(
            $this->getIntegerValue('account_id'),
            $this->getStringValue('name'),
            array_values($this->getArrayValue('abilities', self::DEFAULT_ABILITIES)),
            (int) $this->user()->id,
        );
    }
}
