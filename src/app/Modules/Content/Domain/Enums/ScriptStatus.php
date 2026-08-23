<?php

declare(strict_types=1);

namespace App\Modules\Content\Domain\Enums;

enum ScriptStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
