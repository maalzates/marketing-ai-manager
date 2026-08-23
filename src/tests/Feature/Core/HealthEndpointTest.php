<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use Tests\TestCase;

class HealthEndpointTest extends TestCase
{
    public function test_health_endpoint_reports_ok_inside_the_standard_envelope(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertExactJson(['result' => ['status' => 'ok'], 'errors' => []]);
    }

    public function test_spa_entrypoint_is_served_for_unknown_paths(): void
    {
        $this->get('/dashboard/anything')->assertOk()->assertViewIs('app');
    }
}
