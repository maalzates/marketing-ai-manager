<?php

declare(strict_types=1);

namespace App\Modules\Settings\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ApiException;

class SettingWriteFailedException extends ApiException {}
