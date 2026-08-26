<?php

declare(strict_types=1);

namespace App\Modules\Ai\Domain\Enums;

/**
 * Every feature that reaches the LLM declares its task here. The case value is also the
 * settings key suffix under `ai.models.per_task.*`, so adding a task without adding its
 * default in config/settings.php makes the routing fail loudly instead of silently
 * picking a model the user never chose.
 */
enum AiTask: string
{
    case Chat = 'chat';
    case ContentScript = 'content_script';
    case CampaignProposal = 'campaign_proposal';
    case Verdict = 'verdict';
    case Guardian = 'guardian';
    case CommentSentiment = 'comment_sentiment';
    case CommentMining = 'comment_mining';
    case InsightExtraction = 'insight_extraction';
    case FieldSuggestion = 'field_suggestion';

    public function settingKey(): string
    {
        return 'ai.models.per_task.'.$this->value;
    }

    /**
     * Which tier the task belongs to. Judgement tasks write, decide or argue, so they ride
     * the capable model; the rest classify or extract from text that is already there, and a
     * cheap model does it as well for a fraction of the invoice.
     */
    public function prefersCapableModel(): bool
    {
        return match ($this) {
            self::Chat, self::ContentScript, self::CampaignProposal, self::Verdict, self::Guardian => true,
            self::CommentSentiment, self::CommentMining, self::InsightExtraction, self::FieldSuggestion => false,
        };
    }
}
