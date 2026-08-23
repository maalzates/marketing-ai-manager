<?php

declare(strict_types=1);

namespace Tests\Feature\Knowledge;

use App\Models\User;
use App\Modules\Knowledge\Application\Services\KnowledgeService;
use App\Modules\Knowledge\Domain\Enums\KnowledgeType;
use App\Modules\Knowledge\Infrastructure\Persistence\KnowledgeEntry;
use Database\Seeders\DomainKnowledgeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The system prompt is the cacheable prefix of every LLM call. A prefix that changes
 * between two identical calls costs full price twice, so byte stability is the behaviour
 * under test, not an implementation detail.
 */
class KnowledgeSystemPromptTest extends TestCase
{
    use RefreshDatabase;

    public function test_builds_the_same_system_prompt_byte_for_byte_on_every_call(): void
    {
        $this->seed(DomainKnowledgeSeeder::class);

        $first = $this->app->make(KnowledgeService::class)->systemPrompt();
        $second = $this->app->make(KnowledgeService::class)->systemPrompt();

        $this->assertSame($first, $second);
        $this->assertNotSame('', $first);
    }

    public function test_orders_the_system_prompt_by_key_regardless_of_insertion_order(): void
    {
        KnowledgeEntry::factory()->create(['key' => 'rule-c', 'title' => 'C']);
        KnowledgeEntry::factory()->create(['key' => 'rule-a', 'title' => 'A']);
        KnowledgeEntry::factory()->create(['key' => 'rule-b', 'title' => 'B']);

        $prompt = $this->app->make(KnowledgeService::class)->systemPrompt();

        $this->assertSame(['A', 'B', 'C'], $this->headings($prompt));
    }

    public function test_leaves_unpublished_rules_out_of_the_system_prompt(): void
    {
        KnowledgeEntry::factory()->create(['key' => 'rule-a', 'title' => 'A']);
        KnowledgeEntry::factory()->unpublished()->create(['key' => 'rule-b', 'title' => 'B']);

        $prompt = $this->app->make(KnowledgeService::class)->systemPrompt();

        $this->assertSame(['A'], $this->headings($prompt));
    }

    public function test_uses_only_the_highest_published_version_of_each_rule(): void
    {
        KnowledgeEntry::factory()->create(['key' => 'rule-a', 'version' => 1, 'title' => 'A vieja']);
        KnowledgeEntry::factory()->create(['key' => 'rule-a', 'version' => 2, 'title' => 'A nueva']);

        $prompt = $this->app->make(KnowledgeService::class)->systemPrompt();

        $this->assertSame(['A nueva'], $this->headings($prompt));
    }

    public function test_leaves_other_knowledge_types_out_of_the_system_prompt(): void
    {
        KnowledgeEntry::factory()->create(['key' => 'rule-a', 'title' => 'A']);
        KnowledgeEntry::factory()->ofType(KnowledgeType::GlossaryTerm)->create(['key' => 'cpa', 'title' => 'CPA']);

        $prompt = $this->app->make(KnowledgeService::class)->systemPrompt();

        $this->assertSame(['A'], $this->headings($prompt));
    }

    public function test_the_domain_rule_listing_endpoint_returns_the_same_body_on_every_call(): void
    {
        $this->seed(DomainKnowledgeSeeder::class);
        Sanctum::actingAs(User::factory()->create());

        $first = $this->getJson('/api/v1/knowledge/domain_rule')->assertOk()->getContent();
        $second = $this->getJson('/api/v1/knowledge/domain_rule')->assertOk()->getContent();

        $this->assertSame($first, $second);
    }

    /**
     * @return list<string>
     */
    private function headings(string $prompt): array
    {
        preg_match_all('/^## (.+)$/m', $prompt, $matches);

        return $matches[1];
    }
}
