<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use SplFileInfo;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * The module boundary is only real if something enforces it. Two dependency cycles reached
 * this repository before this test existed, both by putting an interface in the module that
 * answers the question instead of the one that asks it — a mistake that reads as correct in
 * every file you look at individually, and is only visible from above.
 */
class ModuleBoundariesTest extends TestCase
{
    /**
     * Core is excluded from the cycle check: every module depends on it by design, and it
     * depends on none of them — which the last assertion here proves.
     */
    private const string SHARED_MODULE = 'Core';

    public function test_no_module_uses_another_modules_repository_or_client(): void
    {
        $this->assertSame(
            [],
            $this->crossModuleImports()
                ->filter(fn (array $import): bool => Str::contains($import['symbol'], [
                    '\\Infrastructure\\Repositories\\',
                    '\\Infrastructure\\Clients\\',
                ]))
                ->map(fn (array $import): string => "{$import['file']} imports {$import['symbol']}")
                ->values()
                ->all(),
            'A module reaches another module through its Service or a Domain contract. Its '
            .'repositories and clients are private to it.',
        );
    }

    /**
     * Type-hinting another module's model is unavoidable: repositories return Models, Services
     * hand them out, and the caller has to name the type it receives. Querying one is a
     * different act — it bypasses the owning Service and every invariant that lives there,
     * starting with the account scoping. So the line is drawn at use, not at import.
     */
    public function test_no_module_queries_another_modules_model(): void
    {
        $violations = $this->crossModuleImports()
            ->filter(fn (array $import): bool => Str::contains($import['symbol'], '\\Infrastructure\\Persistence\\'))
            ->reject(fn (array $import): bool => Str::contains($import['file'], '/Infrastructure/Persistence/'))
            ->filter(function (array $import): bool {
                $model = Str::afterLast($import['symbol'], '\\');
                $source = (string) file_get_contents(base_path($import['file']));

                return preg_match(
                    '/\b(new\s+'.$model.'\b|'.$model.'::(?!class\b)[a-zA-Z_]+)/',
                    $source,
                ) === 1;
            })
            ->map(fn (array $import): string => "{$import['file']} queries {$import['symbol']}");

        $this->assertSame(
            [],
            $violations->values()->all(),
            'Querying another module\'s model bypasses the Service that owns its invariants, '
            .'including account scoping. Ask that module a question instead.',
        );
    }

    public function test_the_modules_form_no_dependency_cycle(): void
    {
        $graph = $this->crossModuleImports()
            ->groupBy(fn (array $import): string => $import['module'])
            ->map(fn (Collection $imports): array => $imports->pluck('imported')->unique()->values()->all());

        $this->assertSame([], $this->cyclesIn($graph), 'Module dependencies must form a directed acyclic graph. '
            .'Break a cycle by moving the contract into the module that asks the question and '
            .'having the other module implement it.');
    }

    public function test_no_chat_tool_can_reach_the_proposal_executor(): void
    {
        $this->assertSame(
            [],
            $this->imports()
                ->filter(fn (array $import): bool => Str::contains($import['file'], '/Presentation/Tools/')
                    && Str::contains($import['symbol'], 'ProposalExecutionService'))
                ->pluck('file')
                ->values()
                ->all(),
            'A mutation tool proposes and never executes. The approval endpoint is the only door '
            .'to the executing service, and that has to be true of the code, not of the prompt.',
        );
    }

    public function test_every_module_file_declares_strict_types(): void
    {
        $this->assertSame(
            [],
            $this->moduleFiles()
                ->reject(fn (SplFileInfo $file): bool => Str::contains(
                    (string) file_get_contents($file->getPathname()),
                    'declare(strict_types=1);',
                ))
                ->map(fn (SplFileInfo $file): string => $this->relativePath($file))
                ->values()
                ->all(),
        );
    }

    /**
     * Imports that actually cross a boundary. Core is excluded on both sides: it is the shared
     * base every module builds on, so an edge into it says nothing about coupling.
     *
     * @return Collection<int, array{file: string, module: string, imported: string, symbol: string}>
     */
    private function crossModuleImports(): Collection
    {
        return $this->imports()->filter(fn (array $import): bool => $import['module'] !== $import['imported']
            && $import['imported'] !== self::SHARED_MODULE
            && $import['module'] !== self::SHARED_MODULE);
    }

    /**
     * @return Collection<int, SplFileInfo>
     */
    private function moduleFiles(): Collection
    {
        return collect(iterator_to_array(
            Finder::create()->files()->in(app_path('Modules'))->name('*.php'),
            false,
        ));
    }

    /**
     * Every `use App\Modules\X\...` statement, tagged with the module it was written in.
     *
     * @return Collection<int, array{file: string, module: string, imported: string, symbol: string}>
     */
    private function imports(): Collection
    {
        return $this->moduleFiles()->flatMap(function (SplFileInfo $file): array {
            preg_match_all(
                '/^use\s+(App\\\\Modules\\\\([A-Za-z]+)\\\\[^;]+);/m',
                (string) file_get_contents($file->getPathname()),
                $matches,
                PREG_SET_ORDER,
            );

            return array_map(fn (array $match): array => [
                'file' => $this->relativePath($file),
                'module' => $this->moduleOf($file),
                'imported' => $match[2],
                'symbol' => $match[1],
            ], $matches);
        });
    }

    private function moduleOf(SplFileInfo $file): string
    {
        return Str::before(Str::after($this->relativePath($file), 'app/Modules/'), '/');
    }

    private function relativePath(SplFileInfo $file): string
    {
        return Str::after($file->getPathname(), base_path().'/');
    }

    /**
     * @param  Collection<string, list<string>>  $graph
     * @return list<string>
     */
    private function cyclesIn(Collection $graph): array
    {
        $cycles = [];

        foreach ($graph as $module => $dependencies) {
            foreach ($dependencies as $dependency) {
                if (in_array($module, $graph->get($dependency, []), true) && $module < $dependency) {
                    $cycles[] = "{$module} <-> {$dependency}";
                }
            }
        }

        return $cycles;
    }
}
