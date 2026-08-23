<?php

declare(strict_types=1);

namespace App\Modules\Competitors\Presentation\Http\Requests;

use App\Modules\Competitors\Application\DTO\CompetitorPostFilterDTO;
use App\Modules\Competitors\Domain\Enums\Sentiment;
use App\Modules\Core\Application\Context\AccountContext;
use App\Modules\Core\Presentation\Http\Requests\RequestHelperTrait;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class IndexCompetitorPostRequest extends FormRequest
{
    use RequestHelperTrait;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sentiment' => ['nullable', new Enum(Sentiment::class)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function toDTO(): CompetitorPostFilterDTO
    {
        return new CompetitorPostFilterDTO(
            $this->container->make(AccountContext::class)->accountId,
            (int) $this->route('id'),
            $this->getEnumValue('sentiment', Sentiment::class),
            $this->getIntegerValue('per_page', 0),
            $this->getIntegerValue('page', 1),
        );
    }
}
