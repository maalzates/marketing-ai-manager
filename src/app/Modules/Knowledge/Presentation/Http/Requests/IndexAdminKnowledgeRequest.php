<?php

declare(strict_types=1);

namespace App\Modules\Knowledge\Presentation\Http\Requests;

use App\Modules\Core\Presentation\Http\Requests\RequestHelperTrait;
use App\Modules\Knowledge\Application\DTO\KnowledgeFilterDTO;
use App\Modules\Knowledge\Domain\Enums\KnowledgeType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class IndexAdminKnowledgeRequest extends FormRequest
{
    use RequestHelperTrait;

    private const int DEFAULT_PER_PAGE = 25;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['nullable', new Enum(KnowledgeType::class)],
            'key' => ['nullable', 'string', 'max:191'],
            'locale' => ['nullable', 'string', 'max:5'],
            'is_published' => ['nullable', 'boolean'],
            'per_page' => ['required', 'integer', 'min:1', 'max:100'],
            'page' => ['required', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'per_page' => $this->input('per_page', self::DEFAULT_PER_PAGE),
            'page' => $this->input('page', 1),
        ]);
    }

    public function toDTO(): KnowledgeFilterDTO
    {
        return new KnowledgeFilterDTO(
            $this->getEnumValue('type', KnowledgeType::class),
            $this->getStringValue('key'),
            $this->getStringValue('locale'),
            $this->getBooleanValue('is_published'),
            $this->getIntegerValue('per_page'),
            $this->getIntegerValue('page'),
        );
    }
}
