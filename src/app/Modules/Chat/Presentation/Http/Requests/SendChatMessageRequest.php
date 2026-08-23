<?php

declare(strict_types=1);

namespace App\Modules\Chat\Presentation\Http\Requests;

use App\Modules\Chat\Application\DTO\SendChatMessageDTO;
use App\Modules\Core\Application\Context\AccountContext;
use App\Modules\Core\Presentation\Http\Requests\RequestHelperTrait;
use Illuminate\Foundation\Http\FormRequest;

class SendChatMessageRequest extends FormRequest
{
    use RequestHelperTrait;

    private const int MAX_MESSAGE_LENGTH = 8000;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'conversation_id' => ['nullable', 'integer', 'min:1'],
            'message' => ['required', 'string', 'min:1', 'max:'.self::MAX_MESSAGE_LENGTH],
        ];
    }

    public function toDTO(): SendChatMessageDTO
    {
        return new SendChatMessageDTO(
            $this->container->make(AccountContext::class)->accountId,
            $this->container->make(AccountContext::class)->userId,
            $this->getIntegerValue('conversation_id'),
            $this->getStringValue('message'),
        );
    }
}
