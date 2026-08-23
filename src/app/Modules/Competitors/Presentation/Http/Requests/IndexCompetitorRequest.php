<?php

declare(strict_types=1);

namespace App\Modules\Competitors\Presentation\Http\Requests;

use App\Modules\Competitors\Application\DTO\CompetitorFilterDTO;
use App\Modules\Competitors\Domain\Enums\CompetitorPlatform;
use App\Modules\Core\Application\Context\AccountContext;
use App\Modules\Core\Presentation\Http\Requests\RequestHelperTrait;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class IndexCompetitorRequest extends FormRequest
{
    use RequestHelperTrait;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'platform' => ['nullable', new Enum(CompetitorPlatform::class)],
            'strategy_id' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function toDTO(): CompetitorFilterDTO
    {
        return new CompetitorFilterDTO(
            $this->container->make(AccountContext::class)->accountId,
            $this->getEnumValue('platform', CompetitorPlatform::class),
            $this->getIntegerValue('strategy_id'),
            $this->getBooleanValue('is_active'),
            $this->getIntegerValue('per_page', 0),
            $this->getIntegerValue('page', 1),
        );
    }
}
