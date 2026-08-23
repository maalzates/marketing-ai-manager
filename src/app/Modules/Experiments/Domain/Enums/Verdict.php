<?php

declare(strict_types=1);

namespace App\Modules\Experiments\Domain\Enums;

enum Verdict: string
{
    case Worked = 'worked';
    case DidNotWork = 'did_not_work';
    case Inconclusive = 'inconclusive';
}
