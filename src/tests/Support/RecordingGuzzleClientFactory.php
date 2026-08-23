<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Core\Infrastructure\Clients\GuzzleClientFactory;
use ArrayObject;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use Psr\Http\Message\RequestInterface;

/**
 * Bound over GuzzleClientFactory so every client the application builds during a test —
 * verification, OAuth and LLM alike — gets a replayed transport instead of the network,
 * and every request it sends is kept.
 *
 * Keeping the requests is the point: the credential a client carries lives in its headers,
 * so the only way to prove that two accounts did not share one is to read what left.
 */
readonly class RecordingGuzzleClientFactory extends GuzzleClientFactory
{
    /** @param  ArrayObject<int, RequestInterface>  $sent */
    public function __construct(private MockHandler $handler, private ArrayObject $sent) {}

    public function create(array $config = []): Client
    {
        return parent::create(array_replace($config, ['handler' => $this->stack()]));
    }

    private function stack(): HandlerStack
    {
        $stack = HandlerStack::create($this->handler);
        $sent = $this->sent;

        $stack->push(static fn (callable $next): callable => static function (RequestInterface $request, array $options) use ($next, $sent) {
            $sent[] = $request;

            return $next($request, $options);
        });

        return $stack;
    }
}
