<?php

declare(strict_types=1);

namespace App\Modules\Content\Presentation\Http\Requests;

use App\Modules\Content\Application\DTO\CreateScheduleDTO;
use App\Modules\Core\Application\Context\AccountContext;
use App\Modules\Core\Presentation\Http\Requests\RequestHelperTrait;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

class StoreScheduleRequest extends FormRequest
{
    use RequestHelperTrait;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'experiment_id' => ['required', 'integer', 'min:1'],
            'asset_id' => ['nullable', 'integer', 'min:1'],
            'scheduled_at' => ['required', 'date'],
        ];
    }

    public function toDTO(): CreateScheduleDTO
    {
        return new CreateScheduleDTO(
            $this->container->make(AccountContext::class)->accountId,
            $this->getIntegerValue('experiment_id'),
            $this->getIntegerValue('asset_id'),
            CarbonImmutable::parse($this->getStringValue('scheduled_at')),
        );
    }
}
