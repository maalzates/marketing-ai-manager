<?php

declare(strict_types=1);

namespace App\Modules\Content\Presentation\Http\Requests;

use App\Modules\Content\Application\DTO\UpdateContentScriptDTO;
use App\Modules\Content\Domain\Enums\ContentFormat;
use App\Modules\Content\Domain\Enums\ScriptStatus;
use App\Modules\Core\Application\Context\AccountContext;
use App\Modules\Core\Presentation\Http\Requests\RequestHelperTrait;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateContentScriptRequest extends FormRequest
{
    use RequestHelperTrait;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:191'],
            'hook' => ['nullable', 'string'],
            'structure' => ['nullable', 'array', 'min:1'],
            'structure.*.beat' => ['required', 'string', 'max:191'],
            'structure.*.detail' => ['required', 'string'],
            'cta' => ['nullable', 'string'],
            'format' => ['nullable', new Enum(ContentFormat::class)],
            'required_assets' => ['nullable', 'array'],
            // Approval has its own endpoint: it creates an experiment, an edit does not.
            'status' => ['nullable', Rule::enum(ScriptStatus::class)->except(ScriptStatus::Approved)],
        ];
    }

    public function toDTO(): UpdateContentScriptDTO
    {
        return new UpdateContentScriptDTO(
            $this->container->make(AccountContext::class)->accountId,
            (int) $this->route('id'),
            $this->getStringValue('title'),
            $this->getStringValue('hook'),
            $this->validated('structure'),
            $this->getStringValue('cta'),
            $this->getEnumValue('format', ContentFormat::class),
            $this->validated('required_assets'),
            $this->getEnumValue('status', ScriptStatus::class),
        );
    }
}
