<?php

declare(strict_types=1);

namespace App\Modules\Admin\Presentation\Http\Requests;

use App\Modules\Admin\Application\DTO\CreateAdminUserDTO;
use App\Modules\Core\Presentation\Http\Requests\RequestHelperTrait;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Pre-provisioning, not registration: there are no passwords in this system, so the row
 * only becomes usable when that email signs in through Google.
 */
class StoreAdminUserRequest extends FormRequest
{
    use RequestHelperTrait;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191'],
            'email' => ['required', 'email', 'max:191', 'unique:users,email'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ];
    }

    public function toDTO(): CreateAdminUserDTO
    {
        return new CreateAdminUserDTO(
            $this->getStringValue('name'),
            $this->getStringValue('email'),
            array_values($this->getArrayValue('roles')),
            (int) $this->user()->id,
        );
    }
}
