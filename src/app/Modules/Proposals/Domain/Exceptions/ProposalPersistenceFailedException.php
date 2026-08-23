<?php

declare(strict_types=1);

namespace App\Modules\Proposals\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ApiException;

class ProposalPersistenceFailedException extends ApiException {}
