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
}
