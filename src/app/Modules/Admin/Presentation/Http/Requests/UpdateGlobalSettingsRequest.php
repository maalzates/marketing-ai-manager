<?php

declare(strict_types=1);

namespace App\Modules\Admin\Presentation\Http\Requests;

use App\Modules\Admin\Application\DTO\WriteGlobalSettingsDTO;
use App\Modules\Core\Presentation\Http\Requests\RequestHelperTrait;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Without account_id the write lands on the global default; with it, on that account's
 * override — the per-user rate limit the admin panel offers.
 */
class UpdateGlobalSettingsRequest extends FormRequest
{
    use RequestHelperTrait;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'values' => ['required', 'array', 'min:1'],
        ];
    }

    public function toDTO(): WriteGlobalSettingsDTO
    {
        return new WriteGlobalSettingsDTO(
            $this->getIntegerValue('account_id'),
            $this->getArrayValue('values'),
            (int) $this->user()->id,
        );
    }
}
