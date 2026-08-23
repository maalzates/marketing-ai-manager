<?php

declare(strict_types=1);

namespace App\Modules\Content\Presentation\Http\Requests;

use App\Modules\Content\Application\DTO\CalendarQueryDTO;
use App\Modules\Core\Application\Context\AccountContext;
use App\Modules\Core\Presentation\Http\Requests\RequestHelperTrait;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

class CalendarRequest extends FormRequest
{
    use RequestHelperTrait;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'strategy_id' => ['nullable', 'integer', 'min:1'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'suggest' => ['nullable', 'integer', 'min:1', 'max:20'],
        ];
    }

    /** The calendar always spans whole days, whatever precision the caller sent. */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'from' => $this->date('from')?->startOfDay()->toIso8601String(),
            'to' => $this->date('to')?->endOfDay()->toIso8601String(),
        ]);
    }

    public function toDTO(): CalendarQueryDTO
    {
        return new CalendarQueryDTO(
            $this->container->make(AccountContext::class)->accountId,
            $this->getIntegerValue('strategy_id'),
            CarbonImmutable::parse($this->getStringValue('from')),
            CarbonImmutable::parse($this->getStringValue('to')),
        );
    }
}
