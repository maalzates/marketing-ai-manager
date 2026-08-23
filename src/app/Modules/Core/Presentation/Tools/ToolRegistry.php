<?php

declare(strict_types=1);

namespace App\Modules\Core\Presentation\Tools;

use App\Modules\Core\Domain\Exceptions\UnknownToolException;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;

/**
 * The catalogue every door onto the assistant reads: the chat loop and the future MCP
 * adapter both build their tool list from here. It holds classes, never account state —
 * a tool receives the account as an argument to handle().
 */
class ToolRegistry
{
    /** @var array<string, class-string<ToolAbstract>> */
    private array $tools = [];

    public function __construct(private readonly Container $container) {}

    /**
     * @param  class-string<ToolAbstract>  $toolClass
     */
    public function register(string $toolClass): void
    {
        $this->tools[$toolClass::name()] = $toolClass;
    }

    /**
     * @return Collection<int, array{name: string, description: string, schema: array}>
     */
    public function definitions(): Collection
    {
        return collect($this->tools)
            ->map(fn (string $toolClass): array => [
                'name' => $toolClass::name(),
                'description' => $toolClass::description(),
                'schema' => $toolClass::schema(),
            ])
            ->values();
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->tools);
    }

    public function resolve(string $name): ToolAbstract
    {
        return $this->container->make($this->tools[$name] ?? throw UnknownToolException::withName($name));
    }
}
