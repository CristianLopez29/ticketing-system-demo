<?php

declare(strict_types=1);

namespace Tests\Security\Unit;

use App\Models\User;
use Illuminate\Http\Request;
use Src\Security\Infrastructure\Middleware\EnsureRole;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * `role:` always runs after `auth:sanctum` on the registered routes, so the
 * anonymous branch is unreachable there. It still has to fail closed if the
 * middleware is ever applied on its own.
 */
class EnsureRoleTest extends TestCase
{
    public function test_it_rejects_a_request_with_no_authenticated_user(): void
    {
        $this->assertStatusCodeOnAbort(401, Request::create('/api/events/1/stats'));
    }

    public function test_it_rejects_a_user_whose_role_is_not_allowed(): void
    {
        $this->assertStatusCodeOnAbort(403, $this->requestAs('user'));
    }

    public function test_it_lets_an_allowed_role_through(): void
    {
        $response = (new EnsureRole)->handle(
            $this->requestAs('admin'),
            fn () => new Response('ok'),
            'admin'
        );

        $this->assertSame('ok', $response->getContent());
    }

    private function assertStatusCodeOnAbort(int $expected, Request $request): void
    {
        try {
            (new EnsureRole)->handle($request, fn () => new Response, 'admin');
            $this->fail("Expected the middleware to abort with {$expected}.");
        } catch (HttpException $aborted) {
            $this->assertSame($expected, $aborted->getStatusCode());
        }
    }

    private function requestAs(string $role): Request
    {
        $user = new User;
        $user->role = $role;

        $request = Request::create('/api/events/1/stats');
        $request->setUserResolver(fn () => $user);

        return $request;
    }
}
