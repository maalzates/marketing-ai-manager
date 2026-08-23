<?php

declare(strict_types=1);

namespace App\Modules\Knowledge\Presentation\Http\Requests;

use App\Modules\Core\Presentation\Http\Requests\RequestHelperTrait;
use App\Modules\Knowledge\Application\Services\KnowledgeService;
use App\Modules\Knowledge\Domain\Enums\KnowledgeType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class IndexKnowledgeRequest extends FormRequest
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
            'locale' => ['required', 'string', 'max:5'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'type' => $this->route('type'),
            'locale' => $this->input('locale', KnowledgeService::DEFAULT_LOCALE),
        ]);
    }

    public function type(): KnowledgeType
    {
        return $this->getEnumValue('type', KnowledgeType::class);
    }

    public function locale(): string
    {
        return $this->getStringValue('locale');
    }
}
