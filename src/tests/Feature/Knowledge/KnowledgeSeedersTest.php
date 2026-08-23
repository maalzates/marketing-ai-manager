<?php

declare(strict_types=1);

namespace Tests\Feature\Knowledge;

use App\Modules\Knowledge\Domain\Enums\KnowledgeType;
use App\Modules\Knowledge\Infrastructure\Persistence\KnowledgeEntry;
use Database\Seeders\DomainKnowledgeSeeder;
use Database\Seeders\MetricGlossarySeeder;
use Database\Seeders\OnboardingGuideSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class KnowledgeSeedersTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every metric §11 of the spec names as part of the glossary. A metric that reaches the
     * UI without an entry here renders a `<Term>` tooltip pointing at nothing.
     */
    private const array GLOSSARY_KEYS = [
        'impressions',
        'reach',
        'cpm',
        'ctr',
        'hook-rate',
        'retention',
        'engagement-rate',
        'cpc',
        'cpa',
        'cpl',
        'roas',
        'frequency',
        'conversions',
        'cost-per-follower',
    ];

    /**
     * The numeric rule parameters the Experiments module computes its warnings from. A
     * missing key is a crash there, not a cosmetic gap here.
     */
    private const array RULE_NUMBERS = [
        'events_needed',
        'window_days',
        'significant_budget_change_percent',
        'minimum_duration_days',
    ];

    public function test_the_domain_knowledge_seeder_is_idempotent(): void
    {
        $this->seed(DomainKnowledgeSeeder::class);
        $afterFirstRun = $this->entriesOfType(KnowledgeType::DomainRule)->count();

        $this->seed(DomainKnowledgeSeeder::class);

        $this->assertGreaterThan(0, $afterFirstRun);
        $this->assertSame($afterFirstRun, $this->entriesOfType(KnowledgeType::DomainRule)->count());
    }

    public function test_the_metric_glossary_seeder_is_idempotent(): void
    {
        $this->seed(MetricGlossarySeeder::class);
        $afterFirstRun = $this->entriesOfType(KnowledgeType::GlossaryTerm)->count();

        $this->seed(MetricGlossarySeeder::class);

        $this->assertGreaterThan(0, $afterFirstRun);
        $this->assertSame($afterFirstRun, $this->entriesOfType(KnowledgeType::GlossaryTerm)->count());
    }

    public function test_the_onboarding_guide_seeder_is_idempotent(): void
    {
        $this->seed(OnboardingGuideSeeder::class);
        $afterFirstRun = $this->entriesOfType(KnowledgeType::OnboardingGuide)->count();

        $this->seed(OnboardingGuideSeeder::class);

        $this->assertGreaterThan(0, $afterFirstRun);
        $this->assertSame($afterFirstRun, $this->entriesOfType(KnowledgeType::OnboardingGuide)->count());
    }

    public function test_the_seeded_domain_rules_carry_the_numeric_parameters_the_experiments_module_reads(): void
    {
        $this->seed(DomainKnowledgeSeeder::class);

        $metadata = $this->entriesOfType(KnowledgeType::DomainRule)
            ->reduce(fn (array $carry, KnowledgeEntry $entry): array => $carry + $entry->metadata, []);

        foreach (self::RULE_NUMBERS as $key) {
            $this->assertArrayHasKey($key, $metadata, "The seeded domain rules do not declare `{$key}`.");
            $this->assertIsNumeric($metadata[$key], "`{$key}` is not a number the experiments module can use.");
        }
    }

    public function test_the_seeded_domain_rules_carry_the_minimum_budget_formula(): void
    {
        $this->seed(DomainKnowledgeSeeder::class);

        $metadata = $this->entriesOfType(KnowledgeType::DomainRule)
            ->reduce(fn (array $carry, KnowledgeEntry $entry): array => $carry + $entry->metadata, []);

        $this->assertArrayHasKey('budget_formula', $metadata);
        $this->assertSame('(cpa * 50) / 7', $metadata['budget_formula']);
    }

    public function test_the_seeded_glossary_covers_every_metric_the_spec_names(): void
    {
        $this->seed(MetricGlossarySeeder::class);

        $this->assertEmpty(
            array_diff(self::GLOSSARY_KEYS, $this->entriesOfType(KnowledgeType::GlossaryTerm)->pluck('key')->all()),
            'The glossary is missing a metric named in core.md §11.',
        );
    }

    public function test_every_seeded_glossary_entry_declares_a_formula_a_unit_and_a_direction(): void
    {
        $this->seed(MetricGlossarySeeder::class);

        $this->entriesOfType(KnowledgeType::GlossaryTerm)->each(function (KnowledgeEntry $entry): void {
            foreach (['formula', 'unit', 'good_when'] as $key) {
                $this->assertArrayHasKey($key, $entry->metadata, "`{$entry->key}` has no `{$key}`.");
                $this->assertNotEmpty($entry->metadata[$key], "`{$entry->key}` has an empty `{$key}`.");
            }
        });
    }

    public function test_every_seeded_entry_is_published_at_version_one(): void
    {
        $this->seed(DomainKnowledgeSeeder::class);
        $this->seed(MetricGlossarySeeder::class);
        $this->seed(OnboardingGuideSeeder::class);

        $this->assertSame(
            0,
            KnowledgeEntry::query()->where(fn ($query) => $query->where('is_published', false)->orWhere('version', '!=', 1))->count(),
        );
    }

    /**
     * @return Collection<int, KnowledgeEntry>
     */
    private function entriesOfType(KnowledgeType $type): Collection
    {
        return KnowledgeEntry::query()->where('type', $type)->get();
    }
}
