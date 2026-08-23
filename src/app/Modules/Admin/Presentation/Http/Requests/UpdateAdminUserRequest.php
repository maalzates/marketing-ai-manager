<?php

declare(strict_types=1);

namespace App\Modules\Admin\Presentation\Http\Requests;

use App\Modules\Admin\Application\DTO\UpdateAdminUserDTO;
use App\Modules\Core\Presentation\Http\Requests\RequestHelperTrait;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAdminUserRequest extends FormRequest
{
    use RequestHelperTrait;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:191'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->whenHas('is_active', fn () => $this->merge(['is_active' => $this->boolean('is_active')]));
    }

    public function toDTO(): UpdateAdminUserDTO
    {
        return new UpdateAdminUserDTO(
            (int) $this->route('id'),
            $this->getStringValue('name'),
            $this->getBooleanValue('is_active'),
            (int) $this->user()->id,
        );
    }
}
