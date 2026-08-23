<?php

declare(strict_types=1);

namespace App\Modules\Ai\Domain\Contracts;

/**
 * A turn in a conversation, in this application's own vocabulary. Callers never speak a
 * provider's dialect: each adapter translates these into `tool_use` blocks, `tool_calls`
 * arrays or `functionCall` parts, and an adapter that meets a turn it cannot translate
 * faithfully throws rather than approximating it.
 */
interface LlmMessageInterface {}
