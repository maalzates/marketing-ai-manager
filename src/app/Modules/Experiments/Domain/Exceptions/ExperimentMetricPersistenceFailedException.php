<?php

declare(strict_types=1);

namespace App\Modules\Experiments\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ApiException;

class ExperimentMetricPersistenceFailedException extends ApiException {}
