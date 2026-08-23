<?php

declare(strict_types=1);

namespace App\Modules\Competitors\Presentation\Http\Requests;

use App\Modules\Competitors\Application\DTO\InsightFilterDTO;
use App\Modules\Competitors\Domain\Enums\InsightKind;
use App\Modules\Competitors\Domain\Enums\InsightStatus;
use App\Modules\Core\Application\Context\AccountContext;
use App\Modules\Core\Presentation\Http\Requests\RequestHelperTrait;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class IndexInsightRequest extends FormRequest
{
    use RequestHelperTrait;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kind' => ['nullable', new Enum(InsightKind::class)],
            'status' => ['nullable', new Enum(InsightStatus::class)],
            'strategy_id' => ['nullable', 'integer', 'min:1'],
            'competitor_id' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function toDTO(): InsightFilterDTO
    {
        return new InsightFilterDTO(
            $this->container->make(AccountContext::class)->accountId,
            $this->getEnumValue('kind', InsightKind::class),
            $this->getEnumValue('status', InsightStatus::class),
            $this->getIntegerValue('strategy_id'),
            $this->getIntegerValue('competitor_id'),
            $this->getIntegerValue('per_page', 0),
            $this->getIntegerValue('page', 1),
        );
    }
}
