<?php

declare(strict_types=1);

namespace App\Modules\Knowledge\Presentation\Http\Requests;

use App\Modules\Core\Presentation\Http\Requests\RequestHelperTrait;
use App\Modules\Knowledge\Application\DTO\UpdateKnowledgeEntryDTO;
use Illuminate\Foundation\Http\FormRequest;

class UpdateKnowledgeEntryRequest extends FormRequest
{
    use RequestHelperTrait;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => ['required', 'integer'],
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
            'is_published' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['id' => $this->route('id')]);
    }

    public function toDTO(): UpdateKnowledgeEntryDTO
    {
        return new UpdateKnowledgeEntryDTO(
            $this->getIntegerValue('id'),
            $this->getStringValue('title'),
            $this->getStringValue('body'),
            $this->validated('metadata'),
            $this->getBooleanValue('is_published'),
        );
    }
}
