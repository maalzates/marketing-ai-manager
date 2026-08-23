<?php

declare(strict_types=1);

namespace App\Modules\Admin\Presentation\Http\Requests;

use App\Modules\Accounts\Application\DTO\CreateRoleDTO;
use App\Modules\Core\Presentation\Http\Requests\RequestHelperTrait;
use Illuminate\Foundation\Http\FormRequest;

class StoreRoleRequest extends FormRequest
{
    use RequestHelperTrait;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:64', 'unique:roles,name'],
            'label' => ['required', 'string', 'max:191'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['name' => str($this->input('name'))->slug()->value()]);
    }

    public function toDTO(): CreateRoleDTO
    {
        return new CreateRoleDTO($this->getStringValue('name'), $this->getStringValue('label'));
    }
}
