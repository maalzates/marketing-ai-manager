<?php

declare(strict_types=1);

namespace App\Modules\Chat\Application\Services;

use App\Modules\Core\Application\Context\AccountContext;
use App\Modules\Core\Domain\Exceptions\ClientException;
use App\Modules\Core\Presentation\Tools\ToolRegistry;
use Illuminate\Validation\ValidationException;

/**
 * The only place in the chat path that catches, and the reason the rule allows it: a tool
 * the model called wrongly must come back as a result the model can read and correct, not
 * as a 4xx that kills the turn. A Tool itself never catches.
 */
readonly class ToolInvoker
{
    /** The key the transcript reads to mark a result as `is_error` for the model. */
    public const string ERROR_KEY = 'error';

    public function __construct(private ToolRegistry $registry) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function invoke(string $name, array $input, AccountContext $context): array
    {
        try {
            $tool = $this->registry->resolve($name);

            return $tool->handle($tool->validate($input), $context);
        } catch (ValidationException $exception) {
            return [self::ERROR_KEY => 'Invalid tool input.', 'fields' => $exception->errors()];
        } catch (ClientException $exception) {
            return [self::ERROR_KEY => $exception->getClientMessage(), 'extras' => $exception->getExtras()];
        }
    }
}
