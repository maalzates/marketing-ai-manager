<?php

declare(strict_types=1);

namespace App\Modules\Ai\Presentation\Http\Requests;

use App\Modules\Ai\Application\DTO\SuggestFieldDTO;
use App\Modules\Ai\Domain\Enums\AiTask;
use App\Modules\Core\Application\Context\AccountContext;
use App\Modules\Core\Presentation\Http\Requests\RequestHelperTrait;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SuggestFieldRequest extends FormRequest
{
    use RequestHelperTrait;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'task' => ['required', 'string', Rule::enum(AiTask::class)],
            'target' => ['required', 'string', 'max:120'],
            'context' => ['nullable', 'array'],
            'strategy_id' => ['nullable', 'integer'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['task' => $this->input('task', AiTask::FieldSuggestion->value)]);
    }

    public function toDTO(): SuggestFieldDTO
    {
        return new SuggestFieldDTO(
            $this->accountContext()->accountId,
            $this->accountContext()->userId,
            $this->getEnumValue('task', AiTask::class),
            $this->getStringValue('target'),
            $this->getArrayValue('context'),
            $this->getIntegerValue('strategy_id'),
        );
    }

    private function accountContext(): AccountContext
    {
        return $this->container->make(AccountContext::class);
    }
}
