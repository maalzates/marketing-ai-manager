<?php

declare(strict_types=1);

namespace Tests\Feature\Competitors;

use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Competitors\Application\Jobs\MineCommentIdeasJob;
use App\Modules\Competitors\Domain\Enums\InsightKind;
use App\Modules\Competitors\Domain\Enums\InsightSource;
use App\Modules\Competitors\Infrastructure\Persistence\Competitor;
use App\Modules\Competitors\Infrastructure\Persistence\CompetitorComment;
use App\Modules\Competitors\Infrastructure\Persistence\CompetitorPost;
use App\Modules\Competitors\Infrastructure\Persistence\Insight;
use App\Modules\Strategies\Domain\Enums\StrategyStatus;
use App\Modules\Strategies\Infrastructure\Persistence\Strategy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeTransport;
use Tests\Support\RecordingLlmClientFactory;
use Tests\TestCase;

/**
 * Comment mining is the cheapest feature in the product only if its filters run in the right
 * order, so that is what these tests pin rather than the output.
 *
 * Recurrence and novelty are decided in SQL and in string work. By the time a token is spent
 * the model sees a dozen counted topics — and when nothing survives those two filters, it is
 * never called at all. Every "zero calls" assertion below is a euro that would otherwise be
 * charged to the user's own key.
 */
class CommentMiningTest extends TestCase
{
    use RefreshDatabase;

    private const string TOPIC_A = 'What editing software do you use for these reels?';

    private const string TOPIC_B = 'Which editing software makes these reels look so smooth?';

    private const string TOPIC_C = 'Please share the editing software you use on your reels';

    private Account $account;

    private Competitor $competitor;

    private CompetitorPost $post;

    private RecordingLlmClientFactory $llm;

    protected function setUp(): void
    {
        parent::setUp();

        FakeTransport::silent()->install($this->app);
        $this->llm = RecordingLlmClientFactory::replaying('anthropic-structured-comment-mining.json')
            ->install($this->app);

        $this->account = Account::factory()->create();
        $this->competitor = Competitor::factory()->create(['account_id' => $this->account->id]);
        $this->post = CompetitorPost::factory()->create([
            'account_id' => $this->account->id,
            'competitor_id' => $this->competitor->id,
        ]);

        Strategy::factory()->create([
            'account_id' => $this->account->id,
            'status' => StrategyStatus::Active,
        ]);
    }

    public function test_a_topic_three_different_people_raised_becomes_a_content_idea(): void
    {
        $this->comment('lucia_makes', self::TOPIC_A);
        $this->comment('dani_ruiz', self::TOPIC_B);
        $this->comment('the_marta', self::TOPIC_C);

        $this->mine();

        $this->assertDatabaseHas('insights', [
            'account_id' => $this->account->id,
            'competitor_id' => $this->competitor->id,
            'kind' => InsightKind::ContentIdea->value,
            'source' => InsightSource::CommentMining->value,
            'title' => 'Que software de edicion usamos para los reels',
        ]);
    }

    public function test_a_content_idea_carries_the_comments_that_justify_it(): void
    {
        $ids = [
            $this->comment('lucia_makes', self::TOPIC_A)->id,
            $this->comment('dani_ruiz', self::TOPIC_B)->id,
            $this->comment('the_marta', self::TOPIC_C)->id,
        ];

        $this->mine();

        $evidence = Insight::query()->sole()->evidence;

        $this->assertSame(3, $evidence['frequency']);
        $this->assertSame(3, $evidence['distinct_authors']);
        $this->assertEqualsCanonicalizing($ids, $evidence['comment_ids']);
    }

    /**
     * The whole cost design rests on this one: recurrence is counted before the model is
     * consulted, so one person asking five times never reaches it.
     */
    public function test_a_topic_only_one_person_raised_never_reaches_the_model(): void
    {
        $this->comment('lucia_makes', self::TOPIC_A);
        $this->comment('lucia_makes', self::TOPIC_B);
        $this->comment('lucia_makes', self::TOPIC_C);

        $this->mine();

        $this->assertSame(0, $this->llm->callCount());
        $this->assertDatabaseCount('insights', 0);
    }

    public function test_spam_is_dropped_before_it_can_count_towards_recurrence(): void
    {
        $this->comment('lucia_makes', self::TOPIC_A);
        $this->comment('dani_ruiz', self::TOPIC_B);
        $this->comment('promo_bot', self::TOPIC_C.' https://cheap-editing.example');

        $this->mine();

        $this->assertSame(0, $this->llm->callCount());
    }

    public function test_a_trivial_reaction_is_dropped_before_it_can_count_towards_recurrence(): void
    {
        $this->comment('lucia_makes', self::TOPIC_A);
        $this->comment('dani_ruiz', self::TOPIC_B);
        $this->comment('the_marta', 'editing reels 🔥🔥🔥🔥🔥🔥🔥');

        $this->mine();

        $this->assertSame(0, $this->llm->callCount());
    }

    public function test_the_same_person_repeating_a_topic_is_counted_once(): void
    {
        $this->comment('lucia_makes', self::TOPIC_A);
        $this->comment('lucia_makes', self::TOPIC_A);
        $this->comment('dani_ruiz', self::TOPIC_B);
        $this->comment('the_marta', self::TOPIC_C);

        $this->mine();

        $this->assertSame(3, Insight::query()->sole()->evidence['frequency']);
    }

    public function test_an_idea_the_account_already_captured_is_rejected_before_the_model_is_called(): void
    {
        Insight::factory()->contentIdea()->create([
            'account_id' => $this->account->id,
            'title' => 'Reels sobre el software de editing que usamos',
        ]);

        $this->comment('lucia_makes', self::TOPIC_A);
        $this->comment('dani_ruiz', self::TOPIC_B);
        $this->comment('the_marta', self::TOPIC_C);

        $this->mine();

        $this->assertSame(0, $this->llm->callCount());
        $this->assertDatabaseCount('insights', 1);
    }

    public function test_the_model_is_shown_counted_topics_and_not_the_comments_they_came_from(): void
    {
        $this->comment('lucia_makes', self::TOPIC_A);
        $this->comment('dani_ruiz', self::TOPIC_B);
        $this->comment('the_marta', self::TOPIC_C);
        $this->comment('promo_bot', 'Follow me for the cheapest editing preset pack for reels');

        $this->mine();

        $prompt = $this->llm->promptText();

        $this->assertStringContainsString('"distinct_authors":3', $prompt);
        $this->assertStringNotContainsString('cheapest editing preset pack', $prompt);
    }

    public function test_an_idea_pointing_at_a_strategy_the_account_does_not_own_is_stored_unlinked(): void
    {
        $this->comment('lucia_makes', self::TOPIC_A);
        $this->comment('dani_ruiz', self::TOPIC_B);
        $this->comment('the_marta', self::TOPIC_C);

        $this->mine();

        $this->assertNull(Insight::query()->sole()->strategy_id);
    }

    /** An unchanged batch is an answer already paid for; the ledger, not a TTL, says so. */
    public function test_mining_the_same_batch_twice_calls_the_model_once(): void
    {
        $this->comment('lucia_makes', self::TOPIC_A);
        $this->comment('dani_ruiz', self::TOPIC_B);
        $this->comment('the_marta', self::TOPIC_C);

        $this->mine();
        $this->mine();

        $this->assertSame(1, $this->llm->callCount());
        $this->assertDatabaseCount('llm_usage_logs', 1);
    }

    public function test_an_account_with_no_active_strategy_never_reaches_the_model(): void
    {
        Strategy::query()->update(['status' => StrategyStatus::Paused]);

        $this->comment('lucia_makes', self::TOPIC_A);
        $this->comment('dani_ruiz', self::TOPIC_B);
        $this->comment('the_marta', self::TOPIC_C);

        $this->mine();

        $this->assertSame(0, $this->llm->callCount());
    }

    private function mine(): void
    {
        MineCommentIdeasJob::dispatch((int) $this->account->id, (int) $this->competitor->id);
    }

    private function comment(string $author, string $text): CompetitorComment
    {
        return CompetitorComment::factory()->create([
            'account_id' => $this->account->id,
            'competitor_post_id' => $this->post->id,
            'author' => $author,
            'text' => $text,
        ]);
    }
}
