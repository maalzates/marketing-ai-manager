<?php

declare(strict_types=1);

namespace App\Modules\Knowledge\Presentation\Http\Requests;

use App\Modules\Core\Presentation\Http\Requests\RequestHelperTrait;
use App\Modules\Knowledge\Application\DTO\CreateKnowledgeEntryDTO;
use App\Modules\Knowledge\Application\Services\KnowledgeService;
use App\Modules\Knowledge\Domain\Enums\KnowledgeType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreKnowledgeEntryRequest extends FormRequest
{
    use RequestHelperTrait;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', new Enum(KnowledgeType::class)],
            'key' => ['required', 'string', 'max:191'],
            'locale' => ['required', 'string', 'max:5'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'metadata' => ['nullable', 'array'],
            'is_published' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'locale' => $this->input('locale', KnowledgeService::DEFAULT_LOCALE),
            'is_published' => $this->boolean('is_published', true),
        ]);
    }

    public function toDTO(): CreateKnowledgeEntryDTO
    {
        return new CreateKnowledgeEntryDTO(
            $this->getEnumValue('type', KnowledgeType::class),
            $this->getStringValue('key'),
            $this->getStringValue('locale'),
            $this->getStringValue('title'),
            $this->getStringValue('body'),
            $this->getArrayValue('metadata'),
            $this->getBooleanValue('is_published'),
        );
    }
}
