<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Modules\Core\Application\Context\AccountContext;
use App\Modules\Core\Domain\Exceptions\UnknownToolException;
use App\Modules\Core\Presentation\Tools\ToolAbstract;
use App\Modules\Core\Presentation\Tools\ToolRegistry;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * The chat loop is the registry's real door and does not exist yet, so the registry is
 * driven through the container here. Move these onto the chat route once it lands.
 */
class ToolRegistryTest extends TestCase
{
    public function test_resolving_a_tool_that_was_never_registered_raises_the_domain_exception(): void
    {
        $registry = app(ToolRegistry::class);

        try {
            $registry->resolve('list_unicorns');
            $this->fail('Expected an UnknownToolException.');
        } catch (UnknownToolException $exception) {
            $this->assertSame(Response::HTTP_NOT_FOUND, $exception->getHttpStatusCode());
            $this->assertSame(['tool' => 'list_unicorns'], $exception->getContext());
        }
    }

    public function test_a_registered_tool_resolves_out_of_the_container(): void
    {
        $registry = app(ToolRegistry::class);
        $registry->register(ListStrategiesTestTool::class);

        $this->assertInstanceOf(ListStrategiesTestTool::class, $registry->resolve('list_strategies'));
    }

    public function test_definitions_describe_every_registered_tool_for_the_model(): void
    {
        $registry = app(ToolRegistry::class);
        $registry->register(ListStrategiesTestTool::class);

        // The Chat module registers its own tools at boot, so this asserts on the one under
        // test rather than on the registry being empty apart from it.
        $definition = $registry->definitions()->sole(fn (array $definition): bool => $definition['name'] === 'list_strategies');

        $this->assertSame('list_strategies', $definition['name']);
        $this->assertSame('Lists the strategies of the current account.', $definition['description']);
        $this->assertSame(['status'], array_keys($definition['schema']['properties']));
    }

    public function test_an_unregistered_name_is_reported_as_absent(): void
    {
        $this->assertFalse(app(ToolRegistry::class)->has('list_strategies'));
    }
}

readonly class ListStrategiesTestTool extends ToolAbstract
{
    public static function name(): string
    {
        return 'list_strategies';
    }

    public static function description(): string
    {
        return 'Lists the strategies of the current account.';
    }

    public static function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => ['status' => ['type' => 'string', 'enum' => ['active', 'paused']]],
            'required' => ['status'],
        ];
    }

    public function handle(array $input, AccountContext $context): array
    {
        return ['strategies' => []];
    }
}
