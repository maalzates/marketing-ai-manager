<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Modules\Core\Domain\Enums\LogType;
use App\Modules\Core\Domain\Exceptions\ApiException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class ApiExceptionTest extends TestCase
{
    public function test_input_driven_statuses_log_at_info(): void
    {
        $this->assertSame(
            LogType::INFO->value,
            (new ApiException('bad input', Response::HTTP_UNPROCESSABLE_ENTITY))->getLogLevel()
        );
    }

    public function test_other_client_errors_log_at_warning(): void
    {
        $this->assertSame(
            LogType::WARNING->value,
            (new ApiException('nope', Response::HTTP_NOT_FOUND))->getLogLevel()
        );
    }

    public function test_server_errors_log_at_error(): void
    {
        $this->assertSame(LogType::ERROR->value, (new ApiException('boom'))->getLogLevel());
    }

    public function test_a_code_outside_the_http_range_is_reported_as_500(): void
    {
        $this->assertSame(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            (new ApiException('driver said 1045', 1045))->getHttpStatusCode()
        );
    }

    public function test_wrap_keeps_the_original_as_previous_and_records_its_trace(): void
    {
        $wrapped = ApiException::wrap(new RuntimeException('disk on fire'), 'Could not store the asset');

        $this->assertSame('Could not store the asset', $wrapped->getMessage());
        $this->assertSame('disk on fire', $wrapped->getPrevious()->getMessage());
        $this->assertSame('disk on fire', $wrapped->getContext()['error']['exception']);
    }

    public function test_wrap_merges_extra_context(): void
    {
        $this->assertSame(
            'camp_1',
            ApiException::wrap(new RuntimeException('x'), context: ['campaign_id' => 'camp_1'])
                ->getContext()['campaign_id']
        );
    }
}
