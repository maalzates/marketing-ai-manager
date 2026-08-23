<?php

declare(strict_types=1);

namespace App\Modules\Experiments\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use Symfony\Component\HttpFoundation\Response;

class ExperimentNotFoundException extends ClientException
{
    public static function withId(int $id): self
    {
        $exception = new self('Experiment not found.', Response::HTTP_NOT_FOUND);
        $exception->context = ['experiment_id' => $id];

        return $exception;
    }
}
