<?php

declare(strict_types=1);

namespace App\Modules\Chat\Presentation\Http\Requests;

use App\Modules\Chat\Application\DTO\ConversationFilterDTO;
use App\Modules\Core\Application\Context\AccountContext;
use App\Modules\Core\Presentation\Http\Requests\RequestHelperTrait;
use Illuminate\Foundation\Http\FormRequest;

class IndexConversationRequest extends FormRequest
{
    use RequestHelperTrait;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function toDTO(): ConversationFilterDTO
    {
        return new ConversationFilterDTO(
            $this->container->make(AccountContext::class)->accountId,
            $this->container->make(AccountContext::class)->userId,
            $this->getIntegerValue('per_page', 0),
            $this->getIntegerValue('page', 1),
        );
    }
}
