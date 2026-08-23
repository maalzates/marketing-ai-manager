<?php

declare(strict_types=1);

namespace App\Modules\Content\Presentation\Http\Requests;

use App\Modules\Content\Application\DTO\CreateContentScriptDTO;
use App\Modules\Content\Domain\Enums\ContentFormat;
use App\Modules\Core\Application\Context\AccountContext;
use App\Modules\Core\Presentation\Http\Requests\RequestHelperTrait;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreContentScriptRequest extends FormRequest
{
    use RequestHelperTrait;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'strategy_id' => ['required', 'integer', 'min:1'],
            'title' => ['required', 'string', 'max:191'],
            'hook' => ['required', 'string'],
            'structure' => ['required', 'array', 'min:1'],
            'structure.*.beat' => ['required', 'string', 'max:191'],
            'structure.*.detail' => ['required', 'string'],
            'cta' => ['required', 'string'],
            'format' => ['required', new Enum(ContentFormat::class)],
            'required_assets' => ['nullable', 'array'],
            'required_assets.*.type' => ['required', 'string', 'max:64'],
            'required_assets.*.aspect_ratio' => ['nullable', 'string', 'max:16'],
            'required_assets.*.duration_seconds' => ['nullable', 'integer', 'min:1'],
            'required_assets.*.quantity' => ['nullable', 'integer', 'min:1'],
            'source_insight_ids' => ['nullable', 'array'],
            'source_insight_ids.*' => ['integer', 'min:1'],
        ];
    }

    public function toDTO(): CreateContentScriptDTO
    {
        return new CreateContentScriptDTO(
            $this->container->make(AccountContext::class)->accountId,
            $this->getIntegerValue('strategy_id'),
            $this->getStringValue('title'),
            $this->getStringValue('hook'),
            $this->getArrayValue('structure'),
            $this->getStringValue('cta'),
            $this->getEnumValue('format', ContentFormat::class),
            $this->getArrayValue('required_assets'),
            $this->getArrayValue('source_insight_ids'),
        );
    }
}
