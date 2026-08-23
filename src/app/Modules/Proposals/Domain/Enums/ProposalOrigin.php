<?php

declare(strict_types=1);

namespace App\Modules\Proposals\Domain\Enums;

enum ProposalOrigin: string
{
    case Chat = 'chat';
    case Guardian = 'guardian';
    case Evaluation = 'evaluation';
    case Planner = 'planner';
}
