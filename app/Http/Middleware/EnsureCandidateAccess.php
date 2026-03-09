<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCandidateAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user) {
            abort(401);
        }

        if ($user->role === 'admin') {
            return $next($request);
        }

        $param = $request->route('id');
        $candidateId = is_numeric($param) ? (int) $param : null;

        if ($user->role === 'candidate' && $candidateId !== null && (int) $user->candidate_id === $candidateId) {
            return $next($request);
        }

        abort(403);
    }
}

