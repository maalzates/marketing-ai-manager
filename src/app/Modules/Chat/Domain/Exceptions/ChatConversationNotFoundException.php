<?php

declare(strict_types=1);

namespace App\Modules\Chat\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use Symfony\Component\HttpFoundation\Response;

class ChatConversationNotFoundException extends ClientException
{
    public static function withId(int $id): self
    {
        $exception = new self('Conversation not found.', Response::HTTP_NOT_FOUND);
        $exception->context = ['chat_conversation_id' => $id];

        return $exception;
    }
}
