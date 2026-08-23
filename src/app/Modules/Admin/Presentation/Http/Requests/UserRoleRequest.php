<?php

declare(strict_types=1);

namespace App\Modules\Admin\Presentation\Http\Requests;

use App\Modules\Admin\Application\DTO\UserRoleDTO;
use App\Modules\Core\Presentation\Http\Requests\RequestHelperTrait;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Serves both the assignment (role in the body) and the removal (role in the path), so
 * the two share one validation of the role's existence.
 */
class UserRoleRequest extends FormRequest
{
    use RequestHelperTrait;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role' => ['required', 'string', 'exists:roles,name'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['role' => $this->route('role') ?? $this->input('role')]);
    }

    public function toDTO(): UserRoleDTO
    {
        return new UserRoleDTO(
            (int) $this->route('id'),
            $this->getStringValue('role'),
            (int) $this->user()->id,
        );
    }
}
