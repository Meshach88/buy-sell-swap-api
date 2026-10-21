<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_health_reports_ok_with_a_working_database(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertJsonPath('database', 'ok')
            ->assertJsonPath('app', config('app.name'));
    }
}
