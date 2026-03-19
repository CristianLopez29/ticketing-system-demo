<?php

declare(strict_types=1);

namespace Src\Security\Infrastructure\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Src\Security\Application\UseCases\LoginUseCase;
use Src\Security\Domain\Exceptions\AuthenticationFailedException;
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

        try {
            $token = $this->loginUseCase->execute($request->input('email'), $request->input('password'));

            return response()->json(['access_token' => $token]);
        } catch (AuthenticationFailedException $e) {
            return response()->json(['message' => 'Invalid login details'], 401);
        }
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
