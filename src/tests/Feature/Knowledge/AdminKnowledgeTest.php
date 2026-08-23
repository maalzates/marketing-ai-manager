<?php

declare(strict_types=1);

namespace Tests\Feature\Knowledge;

use App\Models\User;
use App\Modules\Accounts\Infrastructure\Persistence\Role;
use App\Modules\Knowledge\Domain\Enums\KnowledgeType;
use App\Modules\Knowledge\Infrastructure\Persistence\KnowledgeEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminKnowledgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_denies_the_listing_route_to_a_non_admin(): void
    {
        $this->actingAsPlainUser();

        $this->getJson('/api/v1/admin/knowledge')
            ->assertForbidden()
            ->assertJsonPath('errors.message', 'You are not allowed to perform this action.');
    }

    public function test_denies_the_create_route_to_a_non_admin(): void
    {
        $this->actingAsPlainUser();

        $this->postJson('/api/v1/admin/knowledge', $this->payload())->assertForbidden();
    }

    public function test_denies_the_update_route_to_a_non_admin(): void
    {
        $this->actingAsPlainUser();
        $entry = KnowledgeEntry::factory()->create();

        $this->putJson("/api/v1/admin/knowledge/{$entry->id}", ['title' => 'Otra'])->assertForbidden();
    }

    public function test_denies_the_delete_route_to_a_non_admin(): void
    {
        $this->actingAsPlainUser();
        $entry = KnowledgeEntry::factory()->create();

        $this->deleteJson("/api/v1/admin/knowledge/{$entry->id}")->assertForbidden();

        $this->assertDatabaseHas('knowledge_entries', ['id' => $entry->id]);
    }

    public function test_lists_every_entry_for_an_admin_including_unpublished_ones(): void
    {
        $this->actingAsAdmin();

        KnowledgeEntry::factory()->create(['key' => 'published-one']);
        KnowledgeEntry::factory()->unpublished()->create(['key' => 'draft-one']);

        $response = $this->getJson('/api/v1/admin/knowledge')->assertOk();

        $this->assertEqualsCanonicalizing(
            ['published-one', 'draft-one'],
            collect($response->json('result.data'))->pluck('key')->all(),
        );
    }

    public function test_filters_the_admin_listing_by_publication_state(): void
    {
        $this->actingAsAdmin();

        KnowledgeEntry::factory()->create(['key' => 'published-one']);
        KnowledgeEntry::factory()->unpublished()->create(['key' => 'draft-one']);

        $response = $this->getJson('/api/v1/admin/knowledge?is_published=0')->assertOk();

        $this->assertSame(['draft-one'], collect($response->json('result.data'))->pluck('key')->all());
    }

    public function test_creates_the_first_version_of_a_new_entry(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/admin/knowledge', $this->payload())
            ->assertCreated()
            ->assertJsonPath('result.version', 1)
            ->assertJsonPath('result.key', 'learning-phase');

        $this->assertDatabaseHas('knowledge_entries', [
            'type' => KnowledgeType::DomainRule->value,
            'key' => 'learning-phase',
            'locale' => 'es',
            'version' => 1,
        ]);
    }

    public function test_creating_the_same_key_twice_stores_the_next_version(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/admin/knowledge', $this->payload())->assertCreated();

        $this->postJson('/api/v1/admin/knowledge', $this->payload())
            ->assertCreated()
            ->assertJsonPath('result.version', 2);
    }

    public function test_updating_an_entry_inserts_the_next_version(): void
    {
        $this->actingAsAdmin();
        $entry = KnowledgeEntry::factory()->create(['key' => 'learning-phase', 'version' => 1]);

        $this->putJson("/api/v1/admin/knowledge/{$entry->id}", ['title' => 'Título corregido'])
            ->assertOk()
            ->assertJsonPath('result.version', 2)
            ->assertJsonPath('result.title', 'Título corregido');

        $this->assertDatabaseHas('knowledge_entries', [
            'key' => 'learning-phase',
            'version' => 2,
            'title' => 'Título corregido',
        ]);
    }

    public function test_updating_an_entry_leaves_the_previous_version_intact(): void
    {
        $this->actingAsAdmin();
        $entry = KnowledgeEntry::factory()->create([
            'key' => 'learning-phase',
            'version' => 1,
            'title' => 'Título original',
        ]);

        $this->putJson("/api/v1/admin/knowledge/{$entry->id}", ['title' => 'Título corregido'])->assertOk();

        $this->assertDatabaseHas('knowledge_entries', [
            'id' => $entry->id,
            'version' => 1,
            'title' => 'Título original',
        ]);
        $this->assertSame(2, KnowledgeEntry::query()->where('key', 'learning-phase')->count());
    }

    public function test_updating_an_entry_carries_over_the_fields_left_out_of_the_payload(): void
    {
        $this->actingAsAdmin();
        $entry = KnowledgeEntry::factory()->create([
            'key' => 'learning-phase',
            'version' => 1,
            'body' => 'Cuerpo original',
            'metadata' => ['events_needed' => 50],
        ]);

        $this->putJson("/api/v1/admin/knowledge/{$entry->id}", ['title' => 'Título corregido'])
            ->assertOk()
            ->assertJsonPath('result.body', 'Cuerpo original')
            ->assertJsonPath('result.metadata.events_needed', 50);
    }

    public function test_reports_an_update_of_a_missing_entry_as_not_found(): void
    {
        $this->actingAsAdmin();

        $this->putJson('/api/v1/admin/knowledge/9999', ['title' => 'Otra'])
            ->assertNotFound()
            ->assertJsonPath('errors.message', 'Knowledge entry not found.');
    }

    public function test_deletes_a_single_version_of_an_entry(): void
    {
        $this->actingAsAdmin();
        $entry = KnowledgeEntry::factory()->create();

        $this->deleteJson("/api/v1/admin/knowledge/{$entry->id}")->assertNoContent();

        $this->assertDatabaseMissing('knowledge_entries', ['id' => $entry->id]);
    }

    public function test_rejects_a_new_entry_without_a_body(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/admin/knowledge', ['type' => 'domain_rule', 'key' => 'k', 'title' => 'T'])
            ->assertStatus(422)
            ->assertJsonPath('errors.fields.body.0', 'The body field is required.');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'type' => KnowledgeType::DomainRule->value,
            'key' => 'learning-phase',
            'title' => 'Fase de aprendizaje',
            'body' => 'Meta necesita 50 eventos en 7 días.',
            'metadata' => ['events_needed' => 50],
        ];
    }

    private function actingAsAdmin(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::factory()->admin()->create());

        Sanctum::actingAs($user);
    }

    private function actingAsPlainUser(): void
    {
        Sanctum::actingAs(User::factory()->create());
    }
}
