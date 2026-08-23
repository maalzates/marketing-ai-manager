<?php

declare(strict_types=1);

namespace App\Modules\Content\Presentation\Http\Requests;

use App\Modules\Content\Application\DTO\ScheduleFilterDTO;
use App\Modules\Content\Domain\Enums\ScheduleStatus;
use App\Modules\Core\Application\Context\AccountContext;
use App\Modules\Core\Presentation\Http\Requests\RequestHelperTrait;
use App\Modules\Experiments\Domain\Enums\ExperimentPlatform;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class IndexScheduleRequest extends FormRequest
{
    use RequestHelperTrait;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'experiment_id' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', new Enum(ScheduleStatus::class)],
            'platform' => ['nullable', new Enum(ExperimentPlatform::class)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function toDTO(): ScheduleFilterDTO
    {
        return new ScheduleFilterDTO(
            $this->container->make(AccountContext::class)->accountId,
            $this->getIntegerValue('experiment_id'),
            $this->getEnumValue('status', ScheduleStatus::class),
            $this->getEnumValue('platform', ExperimentPlatform::class),
            $this->getStringValue('from') === null ? null : CarbonImmutable::parse($this->getStringValue('from')),
            $this->getStringValue('to') === null ? null : CarbonImmutable::parse($this->getStringValue('to')),
            $this->getIntegerValue('per_page', 0),
            $this->getIntegerValue('page', 1),
        );
    }
}
