<?php

declare(strict_types=1);

namespace App\Modules\Content\Presentation\Http\Requests;

use App\Modules\Content\Application\DTO\GenerateScriptsDTO;
use App\Modules\Content\Domain\Enums\ContentFormat;
use App\Modules\Core\Application\Context\AccountContext;
use App\Modules\Core\Presentation\Http\Requests\RequestHelperTrait;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class GenerateContentScriptsRequest extends FormRequest
{
    use RequestHelperTrait;

    private const int DEFAULT_COUNT = 3;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'strategy_id' => ['required', 'integer', 'min:1'],
            'count' => ['nullable', 'integer', 'min:1', 'max:10'],
            'formats' => ['nullable', 'array'],
            'formats.*' => [new Enum(ContentFormat::class)],
            'brief' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['count' => $this->input('count', self::DEFAULT_COUNT)]);
    }

    public function toDTO(): GenerateScriptsDTO
    {
        return new GenerateScriptsDTO(
            $this->container->make(AccountContext::class)->accountId,
            $this->getIntegerValue('strategy_id'),
            $this->getIntegerValue('count'),
            array_map(ContentFormat::from(...), $this->getArrayValue('formats')),
            $this->getStringValue('brief'),
            $this->container->make(AccountContext::class)->userId,
        );
    }
}
