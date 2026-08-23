<?php

declare(strict_types=1);

namespace App\Modules\Admin\Presentation\Http\Requests;

use App\Modules\Admin\Application\DTO\GlobalUsageFilterDTO;
use App\Modules\Audit\Domain\Enums\UsageGrouping;
use App\Modules\Core\Presentation\Http\Requests\RequestHelperTrait;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexGlobalUsageRequest extends FormRequest
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
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'group_by' => ['required', Rule::enum(UsageGrouping::class)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['group_by' => $this->input('group_by', UsageGrouping::ACCOUNT->value)]);
    }

    public function toDTO(): GlobalUsageFilterDTO
    {
        return new GlobalUsageFilterDTO(
            $this->getIntegerValue('account_id'),
            CarbonImmutable::parse($this->getStringValue('from'))->startOfDay(),
            CarbonImmutable::parse($this->getStringValue('to'))->endOfDay(),
            $this->getEnumValue('group_by', UsageGrouping::class, UsageGrouping::ACCOUNT),
        );
    }
}
