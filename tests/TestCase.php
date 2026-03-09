<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create([
            'role' => 'admin',
        ]);

        Sanctum::actingAs($user, ['*']);
    }
}
