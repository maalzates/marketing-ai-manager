<?php

declare(strict_types=1);

namespace App\Modules\Content\Presentation\Http\Requests;

use App\Modules\Content\Application\DTO\UpdateScheduleDTO;
use App\Modules\Content\Domain\Enums\ScheduleStatus;
use App\Modules\Core\Application\Context\AccountContext;
use App\Modules\Core\Presentation\Http\Requests\RequestHelperTrait;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateScheduleRequest extends FormRequest
{
    use RequestHelperTrait;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'asset_id' => ['nullable', 'integer', 'min:1'],
            'scheduled_at' => ['nullable', 'date'],
            'status' => ['nullable', new Enum(ScheduleStatus::class)],
            'external_post_id' => ['nullable', 'string', 'max:191'],
        ];
    }

    public function toDTO(): UpdateScheduleDTO
    {
        return new UpdateScheduleDTO(
            $this->container->make(AccountContext::class)->accountId,
            (int) $this->route('id'),
            $this->getIntegerValue('asset_id'),
            $this->getStringValue('scheduled_at') === null
                ? null
                : CarbonImmutable::parse($this->getStringValue('scheduled_at')),
            $this->getEnumValue('status', ScheduleStatus::class),
            $this->getStringValue('external_post_id'),
        );
    }
}
