<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Domain\Enums;

enum ReportType: string
{
    case ExperimentVerdict = 'experiment_verdict';
    case Periodic = 'periodic';
}
