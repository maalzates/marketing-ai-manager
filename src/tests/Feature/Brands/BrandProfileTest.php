<?php

declare(strict_types=1);

namespace Tests\Feature\Brands;

use App\Models\User;
use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Brands\Domain\Enums\BrandKind;
use App\Modules\Brands\Infrastructure\Persistence\BrandProfile;
use App\Modules\Strategies\Infrastructure\Persistence\Strategy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BrandProfileTest extends TestCase
{
    use RefreshDatabase;

    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = $this->actAsMemberOfANewAccount();
    }

    public function test_creates_a_brand_profile_for_the_current_account(): void
    {
        $this->postJson('/api/v1/brand-profiles', $this->payload())
            ->assertCreated()
            ->assertJsonPath('result.name', 'Panadería Aurora')
            ->assertJsonPath('result.account_id', $this->account->id);

        $this->assertDatabaseHas('brand_profiles', [
            'account_id' => $this->account->id,
            'name' => 'Panadería Aurora',
            'kind' => BrandKind::Company->value,
        ]);
    }

    /**
     * The exact payload the brand profile screen sends. It used to send `one_liner`, `tone`
     * and a `buyer_persona` string while omitting `kind` and `description` altogether, so
     * every save from the screen answered 422 and no profile could be created.
     */
    public function test_the_brand_profile_screen_sends_every_field_the_api_requires(): void
    {
        $this->postJson('/api/v1/brand-profiles', [
            'name' => 'mazzedev.co',
            'kind' => BrandKind::PersonalBrand->value,
            'description' => 'Automatización con IA para trabajo profesional no técnico.',
            'value_proposition' => 'Muestro cómo automatizar trabajo real con IA. Sin código, sin humo.',
            'niche' => 'Automatización con IA para trabajo profesional no técnico',
            'tone_of_voice' => 'directo, concreto, sin jerga.',
            'buyer_personas' => ['Profesionales independientes de 28 a 45 años, no técnicos.'],
        ])->assertCreated();

        $this->assertDatabaseHas('brand_profiles', [
            'account_id' => $this->account->id,
            'name' => 'mazzedev.co',
            'kind' => BrandKind::PersonalBrand->value,
            'tone_of_voice' => 'directo, concreto, sin jerga.',
        ]);
    }

    public function test_returns_the_json_columns_as_arrays_rather_than_strings(): void
    {
        $response = $this->postJson('/api/v1/brand-profiles', $this->payload())->assertCreated();

        $this->assertSame(['cercanía', 'oficio'], $response->json('result.values'));
        $this->assertSame(['política'], $response->json('result.banned_topics'));
        $this->assertSame('Marta', $response->json('result.buyer_personas.0.name'));
        $this->assertSame(['#2b1b0e'], $response->json('result.brand_colors'));
    }

    public function test_defaults_the_json_columns_to_empty_arrays_when_they_are_omitted(): void
    {
        $response = $this->postJson('/api/v1/brand-profiles', [
            'name' => 'Marca mínima',
            'kind' => BrandKind::Project->value,
            'description' => 'Sin más datos.',
        ])->assertCreated();

        $this->assertSame([], $response->json('result.values'));
        $this->assertSame([], $response->json('result.buyer_personas'));
    }

    public function test_rejects_a_brand_profile_without_a_name(): void
    {
        $this->postJson('/api/v1/brand-profiles', ['kind' => BrandKind::Company->value, 'description' => 'x'])
            ->assertStatus(422)
            ->assertJsonPath('errors.fields.name.0', 'The name field is required.');
    }

    public function test_rejects_an_unknown_brand_kind(): void
    {
        $this->postJson('/api/v1/brand-profiles', $this->payload(['kind' => 'conglomerate']))
            ->assertStatus(422)
            ->assertJsonPath('errors.fields.kind.0', 'The selected kind is invalid.');
    }

    public function test_rejects_a_values_list_that_is_not_a_list_of_strings(): void
    {
        $response = $this->postJson('/api/v1/brand-profiles', $this->payload(['values' => [['nested' => true]]]))
            ->assertStatus(422);

        $this->assertSame(
            ['The values.0 field must be a string.'],
            $response->json('errors.fields')['values.0'],
        );
    }

    public function test_lists_only_the_brand_profiles_of_the_current_account(): void
    {
        BrandProfile::factory()->create(['account_id' => $this->account->id, 'name' => 'Propia']);
        BrandProfile::factory()->create(['name' => 'Ajena']);

        $response = $this->getJson('/api/v1/brand-profiles')->assertOk();

        $this->assertSame(['Propia'], collect($response->json('result'))->pluck('name')->all());
    }

    public function test_reads_a_brand_profile_of_the_current_account(): void
    {
        $profile = BrandProfile::factory()->create(['account_id' => $this->account->id]);

        $this->getJson("/api/v1/brand-profiles/{$profile->id}")
            ->assertOk()
            ->assertJsonPath('result.id', $profile->id);
    }

    public function test_reports_another_accounts_brand_profile_as_not_found(): void
    {
        $foreign = BrandProfile::factory()->create();

        $this->getJson("/api/v1/brand-profiles/{$foreign->id}")
            ->assertNotFound()
            ->assertJsonPath('errors.message', 'Brand profile not found.');
    }

    public function test_updates_a_brand_profile_of_the_current_account(): void
    {
        $profile = BrandProfile::factory()->create(['account_id' => $this->account->id, 'niche' => 'panadería']);

        $this->putJson("/api/v1/brand-profiles/{$profile->id}", ['niche' => 'repostería'])
            ->assertOk()
            ->assertJsonPath('result.niche', 'repostería');

        $this->assertDatabaseHas('brand_profiles', ['id' => $profile->id, 'niche' => 'repostería']);
    }

    public function test_refuses_to_update_another_accounts_brand_profile(): void
    {
        $foreign = BrandProfile::factory()->create(['name' => 'Ajena']);

        $this->putJson("/api/v1/brand-profiles/{$foreign->id}", ['name' => 'Secuestrada'])->assertNotFound();

        $this->assertDatabaseHas('brand_profiles', ['id' => $foreign->id, 'name' => 'Ajena']);
    }

    public function test_deletes_a_brand_profile_with_no_strategies_attached(): void
    {
        $profile = BrandProfile::factory()->create(['account_id' => $this->account->id]);

        $this->deleteJson("/api/v1/brand-profiles/{$profile->id}")->assertNoContent();

        $this->assertDatabaseMissing('brand_profiles', ['id' => $profile->id]);
    }

    public function test_refuses_to_delete_a_brand_profile_that_still_has_strategies(): void
    {
        $profile = BrandProfile::factory()->create(['account_id' => $this->account->id]);
        Strategy::factory()->create(['account_id' => $this->account->id, 'brand_profile_id' => $profile->id]);

        $this->deleteJson("/api/v1/brand-profiles/{$profile->id}")
            ->assertStatus(409)
            ->assertJsonPath(
                'errors.message',
                'This brand profile still has strategies attached and cannot be deleted.',
            );

        $this->assertDatabaseHas('brand_profiles', ['id' => $profile->id]);
    }

    public function test_refuses_to_delete_another_accounts_brand_profile(): void
    {
        $foreign = BrandProfile::factory()->create();

        $this->deleteJson("/api/v1/brand-profiles/{$foreign->id}")->assertNotFound();

        $this->assertDatabaseHas('brand_profiles', ['id' => $foreign->id]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Panadería Aurora',
            'kind' => BrandKind::Company->value,
            'description' => 'Panadería de barrio con obrador propio.',
            'niche' => 'panadería artesanal',
            'value_proposition' => 'Masa madre fermentada 24 horas.',
            'tone_of_voice' => 'Cercano y sin tecnicismos.',
            'values' => ['cercanía', 'oficio'],
            'banned_topics' => ['política'],
            'buyer_personas' => [['name' => 'Marta', 'pain' => 'No tiene tiempo de cocinar']],
            'reference_competitors' => ['@obrador_vecino'],
            'brand_colors' => ['#2b1b0e'],
            ...$overrides,
        ];
    }

    private function actAsMemberOfANewAccount(): Account
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['owner_user_id' => $user->id]);
        $user->accounts()->attach($account);

        Sanctum::actingAs($user);

        return $account;
    }
}
