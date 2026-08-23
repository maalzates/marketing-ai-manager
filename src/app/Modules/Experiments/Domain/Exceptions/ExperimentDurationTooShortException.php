<?php

declare(strict_types=1);

namespace App\Modules\Experiments\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use Symfony\Component\HttpFoundation\Response;

class ExperimentDurationTooShortException extends ClientException
{
    public static function needsDays(int $requestedDays, int $minimumDays): self
    {
        $exception = new self(
            sprintf(
                'Un experimento de ads dura al menos %d días y este dura %d. Evaluar antes es evaluar ruido.',
                $minimumDays,
                $requestedDays,
            ),
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );
        $exception->context = ['requested_days' => $requestedDays, 'minimum_days' => $minimumDays];

        return $exception;
    }
}
