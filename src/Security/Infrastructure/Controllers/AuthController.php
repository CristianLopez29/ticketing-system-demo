<?php

declare(strict_types=1);

namespace Src\Security\Infrastructure\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Security\Application\UseCases\LoginUseCase;
use Symfony\Component\HttpFoundation\Response;

class AuthController
{
    public function __construct(
        private readonly LoginUseCase $loginUseCase
    ) {}

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        /** @var string $email */
        $email = $request->input('email');
        /** @var string $password */
        $password = $request->input('password');

        return response()->json([
            'access_token' => $this->loginUseCase->execute($email, $password),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return new JsonResponse([
                'message' => 'Unauthorized',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $user->tokens()->delete();

        return new JsonResponse([
            'message' => 'Logged out',
        ], Response::HTTP_OK);
    }

    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return new JsonResponse(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $user->tokens()->delete();
        $newToken = $user->createToken('api')->plainTextToken;

        return new JsonResponse([
            'token' => $newToken,
        ], Response::HTTP_OK);
    }

    public function revokeAllTokens(int $id): JsonResponse
    {
        $user = User::query()->find($id);
        if (! $user) {
            return new JsonResponse([
                'message' => 'User not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $user->tokens()->delete();

        return new JsonResponse([
            'message' => 'All tokens revoked',
        ], Response::HTTP_OK);
    }
}
