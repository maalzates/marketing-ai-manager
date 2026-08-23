<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Modules\Core\Domain\Enums\LogType;
use App\Modules\Core\Domain\Exceptions\ApiException;
use App\Modules\Core\Domain\Exceptions\ClientException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class ApiExceptionRenderingTest extends TestCase
{
    public function test_a_domain_exception_renders_as_the_error_envelope(): void
    {
        Route::get('/api/testing/boom', fn () => throw new ApiException('Campaign not found', Response::HTTP_NOT_FOUND));

        $this->getJson('/api/testing/boom')
            ->assertNotFound()
            ->assertExactJson([
                'result' => [],
                'errors' => ['message' => 'Campaign not found', 'status_code' => Response::HTTP_NOT_FOUND],
            ]);
    }

    public function test_a_client_exception_returns_its_client_message_and_extras(): void
    {
        Route::get('/api/testing/client-boom', fn () => throw new TestClientException);

        $this->getJson('/api/testing/client-boom')
            ->assertStatus(Response::HTTP_BAD_REQUEST)
            ->assertJsonPath('errors.message', 'Pick a shorter caption.')
            ->assertJsonPath('errors.field', 'caption');
    }

    public function test_an_unknown_api_route_uses_the_same_envelope(): void
    {
        $this->getJson('/api/does-not-exist')
            ->assertNotFound()
            ->assertJsonPath('result', [])
            ->assertJsonPath('errors.status_code', Response::HTTP_NOT_FOUND);
    }

    public function test_a_validation_failure_reports_its_fields(): void
    {
        Route::post('/api/testing/validated', fn (TestValidatedRequest $request) => $request->validated());

        $this->postJson('/api/testing/validated', [])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonPath('errors.status_code', Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonPath('errors.fields.name.0', 'The name field is required.');
    }

    public function test_a_code_outside_the_http_range_falls_back_to_500(): void
    {
        Route::get('/api/testing/weird-code', fn () => throw new ApiException('Provider blew up', 7));

        $this->getJson('/api/testing/weird-code')
            ->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR)
            ->assertJsonPath('errors.status_code', Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public function test_a_wrapped_exception_keeps_the_original_detail_in_the_log_only(): void
    {
        Log::spy();

        Route::get('/api/testing/wrapped', fn () => throw ApiException::wrap(
            new RuntimeException('disk on fire'),
            'Could not store the asset',
            context: ['asset_id' => 'asset_1'],
        ));

        $this->getJson('/api/testing/wrapped')
            ->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR)
            ->assertJsonPath('errors.message', 'Could not store the asset')
            ->assertDontSee('disk on fire');

        Log::shouldHaveReceived('log')->withArgs(
            fn (string $level, string $message, array $context): bool => $level === LogType::ERROR->value
                && $message === 'Could not store the asset'
                && $context['asset_id'] === 'asset_1'
                && $context['error']['exception'] === 'disk on fire'
        )->once();
    }

    /**
     * @param  array{0: int, 1: string}  $case
     */
    #[DataProvider('logLevels')]
    public function test_the_log_level_follows_the_status_code(int $status, string $expectedLevel): void
    {
        Log::spy();

        Route::get("/api/testing/level-{$status}", fn () => throw new ApiException('nope', $status));

        $this->getJson("/api/testing/level-{$status}");

        Log::shouldHaveReceived('log')
            ->withArgs(fn (string $level): bool => $level === $expectedLevel)
            ->once();
    }

    public static function logLevels(): array
    {
        return [
            'rejected input logs at info' => [Response::HTTP_UNPROCESSABLE_ENTITY, LogType::INFO->value],
            'bad request logs at info' => [Response::HTTP_BAD_REQUEST, LogType::INFO->value],
            'other client errors log at warning' => [Response::HTTP_NOT_FOUND, LogType::WARNING->value],
            'server errors log at error' => [Response::HTTP_INTERNAL_SERVER_ERROR, LogType::ERROR->value],
        ];
    }
}

class TestValidatedRequest extends FormRequest
{
    public function rules(): array
    {
        return ['name' => ['required', 'string']];
    }
}

class TestClientException extends ClientException
{
    protected ?string $clientMessage = 'Pick a shorter caption.';

    protected array $extras = ['field' => 'caption'];
}
