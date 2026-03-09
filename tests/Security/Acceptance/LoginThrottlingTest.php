<?php

namespace Tests\Security\Acceptance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LoginThrottlingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function excessive_failed_logins_are_rate_limited(): void
    {
        $payload = [
            'email' => 'nosuchuser@example.com',
            'password' => 'wrong-password',
        ];

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', $payload)->assertStatus(401);
        }

        $this->postJson('/api/login', $payload)->assertStatus(429);
    }
}

