<?php

declare(strict_types=1);

namespace Src\Security\Infrastructure\Auth;

use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Src\Security\Domain\Ports\Authenticator;

class SanctumAuthenticator implements Authenticator
{
    public function attempt(string $email, string $password): ?string
    {
        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            return null;
        }

        return $user->createToken('auth_token')->plainTextToken;
    }
}
