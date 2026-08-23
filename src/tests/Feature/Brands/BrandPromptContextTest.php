<?php

declare(strict_types=1);

namespace Tests\Feature\Brands;

use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Brands\Application\Services\BrandContextService;
use App\Modules\Brands\Domain\Enums\BrandKind;
use App\Modules\Brands\Domain\Exceptions\BrandProfileNotFoundException;
use App\Modules\Brands\Infrastructure\Persistence\BrandProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `BrandContextService` has no HTTP entry point yet — nothing in `app/` calls it. Until a
 * prompt-building route reaches it, these run the real service against real MySQL through
 * the container, with nothing stubbed.
 */
class BrandPromptContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_the_compact_brand_block_every_prompt_carries(): void
    {
        $account = Account::factory()->create();
        $profile = BrandProfile::factory()->create([
            'account_id' => $account->id,
            'name' => 'Panadería Aurora',
            'kind' => BrandKind::Company,
            'niche' => 'panadería artesanal',
            'value_proposition' => 'Masa madre fermentada 24 horas.',
            'tone_of_voice' => 'Cercano y sin tecnicismos.',
            'values' => ['cercanía', 'oficio'],
            'banned_topics' => ['política'],
            'buyer_personas' => [['name' => 'Marta', 'pain' => 'No tiene tiempo de cocinar']],
        ]);

        $context = $this->app->make(BrandContextService::class)
            ->promptContext((int) $account->id, (int) $profile->id);

        $this->assertSame([
            'name',
            'kind',
            'description',
            'niche',
            'value_proposition',
            'tone_of_voice',
            'values',
            'banned_topics',
            'buyer_personas',
        ], array_keys($context));

        $this->assertSame('Panadería Aurora', $context['name']);
        $this->assertSame('company', $context['kind']);
        $this->assertSame(['cercanía', 'oficio'], $context['values']);
        $this->assertSame('Marta', $context['buyer_personas'][0]['name']);
    }

    public function test_leaves_the_reference_competitors_and_colors_out_of_the_prompt_block(): void
    {
        $account = Account::factory()->create();
        $profile = BrandProfile::factory()->create(['account_id' => $account->id]);

        $context = $this->app->make(BrandContextService::class)
            ->promptContext((int) $account->id, (int) $profile->id);

        $this->assertArrayNotHasKey('reference_competitors', $context);
        $this->assertArrayNotHasKey('brand_colors', $context);
    }

    public function test_refuses_to_build_the_block_from_another_accounts_brand_profile(): void
    {
        $account = Account::factory()->create();
        $foreign = BrandProfile::factory()->create();

        $this->expectException(BrandProfileNotFoundException::class);

        $this->app->make(BrandContextService::class)->promptContext((int) $account->id, (int) $foreign->id);
    }
}
