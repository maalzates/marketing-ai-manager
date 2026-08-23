<?php

declare(strict_types=1);

namespace App\Modules\Knowledge\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use App\Modules\Knowledge\Domain\Enums\KnowledgeType;
use Symfony\Component\HttpFoundation\Response;

class KnowledgeEntryNotFoundException extends ClientException
{
    public static function withId(int $id): self
    {
        $exception = new self('Knowledge entry not found.', Response::HTTP_NOT_FOUND);
        $exception->context = ['knowledge_entry_id' => $id];

        return $exception;
    }

    public static function withKey(KnowledgeType $type, string $key, string $locale): self
    {
        $exception = new self('Knowledge entry not found.', Response::HTTP_NOT_FOUND);
        $exception->context = ['type' => $type->value, 'key' => $key, 'locale' => $locale];

        return $exception;
    }
}
