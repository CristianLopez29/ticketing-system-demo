<?php

declare(strict_types=1);

namespace Tests\Shared\Acceptance;

use Tests\TestCase;

class DocsBasicAuthTest extends TestCase
{
    public function test_it_challenges_a_browser_instead_of_redirecting_to_login(): void
    {
        // auth:sanctum used to guard this route, which sent every browser to the /login
        // stub: the documentation was unreachable in any non-local environment.
        config(['security.docs.protect' => true, 'security.docs.password' => 'secret']);

        $response = $this->get('/api/documentation');

        $response->assertStatus(401);
        $response->assertHeader('WWW-Authenticate', 'Basic realm="API documentation"');
    }

    public function test_it_serves_the_ui_with_valid_credentials(): void
    {
        config([
            'security.docs.protect' => true,
            'security.docs.username' => 'docs',
            'security.docs.password' => 'secret',
        ]);

        $response = $this->get('/api/documentation', [
            'Authorization' => 'Basic '.base64_encode('docs:secret'),
        ]);

        $response->assertOk();
    }

    public function test_it_rejects_a_wrong_password(): void
    {
        config([
            'security.docs.protect' => true,
            'security.docs.username' => 'docs',
            'security.docs.password' => 'secret',
        ]);

        $response = $this->get('/api/documentation', [
            'Authorization' => 'Basic '.base64_encode('docs:wrong'),
        ]);

        $response->assertStatus(401);
    }

    public function test_it_fails_closed_when_no_password_is_configured(): void
    {
        // A missing password must never be read as "open to everyone".
        config(['security.docs.protect' => true, 'security.docs.password' => null]);

        $this->get('/api/documentation')->assertStatus(503);
    }

    public function test_it_relaxes_the_content_security_policy_so_swagger_ui_can_render(): void
    {
        // The global `default-src 'none'` blocks Swagger UI's own script and stylesheet.
        config(['security.docs.protect' => false]);

        $response = $this->get('/api/documentation');

        $response->assertOk();
        $this->assertStringContainsString("script-src 'self'", (string) $response->headers->get('Content-Security-Policy'));
    }
}
