<?php

declare(strict_types=1);

namespace App\Modules\Chat\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The loop stop. It logs at warning rather than info because a model that never stops
 * asking for tools is spending the account's own key on every round trip.
 */
class ChatToolLoopLimitExceededException extends ClientException
{
    public static function afterRoundTrips(int $conversationId, int $limit): self
    {
        $exception = new self(
            'The assistant could not finish within its tool-use limit. Try a narrower question.',
            Response::HTTP_CONFLICT,
        );
        $exception->context = ['chat_conversation_id' => $conversationId, 'max_tool_round_trips' => $limit];

        return $exception;
    }
}
