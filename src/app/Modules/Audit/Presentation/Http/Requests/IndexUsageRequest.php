<?php

declare(strict_types=1);

namespace App\Modules\Audit\Presentation\Http\Requests;

use App\Modules\Audit\Application\DTO\UsageFilterDTO;
use App\Modules\Audit\Domain\Enums\UsageGrouping;
use App\Modules\Core\Application\Context\AccountContext;
use App\Modules\Core\Presentation\Http\Requests\RequestHelperTrait;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexUsageRequest extends FormRequest
{
    use RequestHelperTrait;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'group_by' => [
                'required',
                Rule::enum(UsageGrouping::class)->only([UsageGrouping::DAY, UsageGrouping::FEATURE]),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['group_by' => $this->input('group_by', UsageGrouping::DAY->value)]);
    }

    public function toDTO(): UsageFilterDTO
    {
        return UsageFilterDTO::forAccount(
            $this->container->make(AccountContext::class)->accountId,
            CarbonImmutable::parse($this->getStringValue('from'))->startOfDay(),
            CarbonImmutable::parse($this->getStringValue('to'))->endOfDay(),
            $this->getEnumValue('group_by', UsageGrouping::class, UsageGrouping::DAY),
        );
    }
}
