<?php

declare(strict_types=1);

namespace App\Modules\Admin\Presentation\Http\Requests;

use App\Modules\Accounts\Application\DTO\UpdateRoleDTO;
use App\Modules\Core\Presentation\Http\Requests\RequestHelperTrait;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    use RequestHelperTrait;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:64', Rule::unique('roles', 'name')->ignore($this->route('id'))],
            'label' => ['nullable', 'string', 'max:191'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->whenHas('name', fn () => $this->merge(['name' => str($this->input('name'))->slug()->value()]));
    }

    public function toDTO(): UpdateRoleDTO
    {
        return new UpdateRoleDTO(
            (int) $this->route('id'),
            $this->getStringValue('name'),
            $this->getStringValue('label'),
        );
    }
}
