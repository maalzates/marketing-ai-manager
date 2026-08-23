<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Modules\Core\Domain\Exceptions\ApiException;
use App\Modules\Core\Domain\Exceptions\ClientException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Route;
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

    public function test_an_out_of_range_code_falls_back_to_500(): void
    {
        Route::get('/api/testing/weird-code', fn () => throw new ApiException('Provider blew up', 7));

        $this->getJson('/api/testing/weird-code')
            ->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR)
            ->assertJsonPath('errors.status_code', Response::HTTP_INTERNAL_SERVER_ERROR);
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
