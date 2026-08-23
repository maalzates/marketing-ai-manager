<?php

declare(strict_types=1);

namespace App\Modules\Knowledge\Domain\Enums;

enum KnowledgeType: string
{
    case DomainRule = 'domain_rule';

    case GlossaryTerm = 'glossary_term';

    case OnboardingGuide = 'onboarding_guide';

    case PromptTemplate = 'prompt_template';
}
