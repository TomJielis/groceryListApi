<?php

namespace Tests\Feature;

use Tests\TestCase;

class StatusTest extends TestCase
{
    public function test_answers_without_logging_in(): void
    {
        $this->getJson('/api/status')->assertOk()->assertExactJson(['status' => 'ok']);
    }

    public function test_answers_503_in_maintenance_mode(): void
    {
        $this->artisan('down')->assertSuccessful();

        try {
            $this->getJson('/api/status')->assertStatus(503);
        } finally {
            $this->artisan('up');
        }
    }
}
