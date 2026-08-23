<?php

declare(strict_types=1);

namespace App\Modules\Content\Presentation\Http\Requests;

use App\Modules\Content\Application\DTO\ApproveScriptDTO;
use App\Modules\Core\Application\Context\AccountContext;
use App\Modules\Core\Presentation\Http\Requests\RequestHelperTrait;
use App\Modules\Experiments\Domain\Enums\ExperimentPlatform;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class ApproveContentScriptRequest extends FormRequest
{
    use RequestHelperTrait;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'platform' => ['required', new Enum(ExperimentPlatform::class)],
            'hypothesis' => ['required', 'string'],
            'expected_result' => ['required', 'array'],
            'expected_result.metric' => ['required', 'string', 'max:64'],
            'expected_result.operator' => ['required', 'string', 'max:16'],
            'expected_result.value' => ['required', 'numeric'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
        ];
    }

    public function toDTO(): ApproveScriptDTO
    {
        return new ApproveScriptDTO(
            $this->container->make(AccountContext::class)->accountId,
            (int) $this->route('id'),
            $this->getEnumValue('platform', ExperimentPlatform::class),
            $this->getStringValue('hypothesis'),
            $this->getArrayValue('expected_result'),
            CarbonImmutable::parse($this->getStringValue('starts_at')),
            CarbonImmutable::parse($this->getStringValue('ends_at')),
            $this->container->make(AccountContext::class)->userId,
        );
    }
}
