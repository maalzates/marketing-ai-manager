<?php

declare(strict_types=1);

namespace App\Modules\Admin\Presentation\Http\Requests;

use App\Modules\Admin\Application\DTO\AdminUserFilterDTO;
use App\Modules\Core\Presentation\Http\Requests\RequestHelperTrait;
use Illuminate\Foundation\Http\FormRequest;

class IndexAdminUserRequest extends FormRequest
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
            'search' => ['nullable', 'string', 'max:191'],
            'is_active' => ['nullable', 'boolean'],
            'role' => ['nullable', 'string', 'exists:roles,name'],
            'per_page' => ['required', 'integer', 'min:1', 'max:100'],
            'page' => ['required', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'per_page' => $this->input('per_page', self::DEFAULT_PER_PAGE),
            'page' => $this->input('page', 1),
        ]);

        $this->whenHas('is_active', fn () => $this->merge(['is_active' => $this->boolean('is_active')]));
    }

    public function toDTO(): AdminUserFilterDTO
    {
        return new AdminUserFilterDTO(
            $this->getStringValue('search'),
            $this->getBooleanValue('is_active'),
            $this->getStringValue('role'),
            $this->getIntegerValue('per_page', self::DEFAULT_PER_PAGE),
            $this->getIntegerValue('page', 1),
        );
    }
}
