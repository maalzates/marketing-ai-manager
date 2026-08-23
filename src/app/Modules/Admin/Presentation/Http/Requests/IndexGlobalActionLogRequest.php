<?php

declare(strict_types=1);

namespace App\Modules\Admin\Presentation\Http\Requests;

use App\Modules\Admin\Application\DTO\GlobalActionLogFilterDTO;
use App\Modules\Audit\Domain\Enums\ActionOrigin;
use App\Modules\Core\Presentation\Http\Requests\RequestHelperTrait;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexGlobalActionLogRequest extends FormRequest
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
            'account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'action' => ['nullable', 'string', 'max:191'],
            'origin' => ['nullable', Rule::enum(ActionOrigin::class)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
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
    }

    public function toDTO(): GlobalActionLogFilterDTO
    {
        return new GlobalActionLogFilterDTO(
            $this->getIntegerValue('account_id'),
            $this->getStringValue('action'),
            $this->getEnumValue('origin', ActionOrigin::class),
            $this->date('from')?->toImmutable(),
            $this->date('to')?->toImmutable(),
            $this->getIntegerValue('per_page', self::DEFAULT_PER_PAGE),
            $this->getIntegerValue('page', 1),
        );
    }
}
