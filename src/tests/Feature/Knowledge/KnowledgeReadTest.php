<?php

declare(strict_types=1);

namespace Tests\Feature\Knowledge;

use App\Models\User;
use App\Modules\Knowledge\Domain\Enums\KnowledgeType;
use App\Modules\Knowledge\Infrastructure\Persistence\KnowledgeEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class KnowledgeReadTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_the_latest_published_version_of_each_key(): void
    {
        $this->actingAsReader();

        KnowledgeEntry::factory()->create(['key' => 'learning-phase', 'version' => 1, 'title' => 'Vieja']);
        KnowledgeEntry::factory()->create(['key' => 'learning-phase', 'version' => 2, 'title' => 'Nueva']);
        KnowledgeEntry::factory()->create(['key' => 'minimum-budget', 'version' => 1, 'title' => 'Presupuesto']);

        $response = $this->getJson('/api/v1/knowledge/domain_rule')->assertOk();

        $this->assertSame(
            ['learning-phase' => 'Nueva', 'minimum-budget' => 'Presupuesto'],
            collect($response->json('result'))->pluck('title', 'key')->all(),
        );
    }

    public function test_omits_a_superseded_version_from_the_type_listing(): void
    {
        $this->actingAsReader();

        KnowledgeEntry::factory()->create(['key' => 'learning-phase', 'version' => 1]);
        KnowledgeEntry::factory()->create(['key' => 'learning-phase', 'version' => 2]);

        $response = $this->getJson('/api/v1/knowledge/domain_rule')->assertOk();

        $this->assertSame([2], collect($response->json('result'))->pluck('version')->all());
    }

    public function test_omits_an_unpublished_version_from_the_type_listing(): void
    {
        $this->actingAsReader();

        KnowledgeEntry::factory()->create(['key' => 'learning-phase', 'version' => 1]);
        KnowledgeEntry::factory()->unpublished()->create(['key' => 'learning-phase', 'version' => 2]);

        $response = $this->getJson('/api/v1/knowledge/domain_rule')->assertOk();

        $this->assertSame([1], collect($response->json('result'))->pluck('version')->all());
    }

    public function test_lists_only_the_entries_of_the_requested_type(): void
    {
        $this->actingAsReader();

        KnowledgeEntry::factory()->create(['key' => 'learning-phase']);
        KnowledgeEntry::factory()->ofType(KnowledgeType::GlossaryTerm)->create(['key' => 'cpa']);

        $response = $this->getJson('/api/v1/knowledge/glossary_term')->assertOk();

        $this->assertSame(['cpa'], collect($response->json('result'))->pluck('key')->all());
    }

    public function test_returns_the_highest_published_version_of_a_key(): void
    {
        $this->actingAsReader();

        KnowledgeEntry::factory()->create(['key' => 'learning-phase', 'version' => 1, 'title' => 'v1']);
        KnowledgeEntry::factory()->create(['key' => 'learning-phase', 'version' => 3, 'title' => 'v3']);
        KnowledgeEntry::factory()->create(['key' => 'learning-phase', 'version' => 2, 'title' => 'v2']);

        $this->getJson('/api/v1/knowledge/domain_rule/learning-phase')
            ->assertOk()
            ->assertJsonPath('result.version', 3)
            ->assertJsonPath('result.title', 'v3');
    }

    public function test_ignores_an_unpublished_newer_version_of_a_key(): void
    {
        $this->actingAsReader();

        KnowledgeEntry::factory()->create(['key' => 'learning-phase', 'version' => 1, 'title' => 'v1']);
        KnowledgeEntry::factory()->unpublished()->create(['key' => 'learning-phase', 'version' => 2, 'title' => 'v2']);

        $this->getJson('/api/v1/knowledge/domain_rule/learning-phase')
            ->assertOk()
            ->assertJsonPath('result.version', 1);
    }

    public function test_reports_a_missing_key_as_not_found(): void
    {
        $this->actingAsReader();

        $this->getJson('/api/v1/knowledge/domain_rule/does-not-exist')
            ->assertNotFound()
            ->assertJsonPath('errors.message', 'Knowledge entry not found.');
    }

    public function test_rejects_an_unknown_type_on_the_listing_route_as_a_validation_error(): void
    {
        $this->actingAsReader();

        $this->getJson('/api/v1/knowledge/not_a_type')
            ->assertStatus(422)
            ->assertJsonPath('errors.fields.type.0', 'The selected type is invalid.');
    }

    public function test_rejects_an_unknown_type_on_the_single_entry_route_as_a_validation_error(): void
    {
        $this->actingAsReader();

        $this->getJson('/api/v1/knowledge/not_a_type/learning-phase')
            ->assertStatus(422)
            ->assertJsonPath('errors.fields.type.0', 'The selected type is invalid.');
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/v1/knowledge/domain_rule')->assertUnauthorized();
    }

    private function actingAsReader(): void
    {
        Sanctum::actingAs(User::factory()->create());
    }
}
