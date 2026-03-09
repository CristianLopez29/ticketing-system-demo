<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEvaluatorAccess
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

        $param = $request->route('evaluatorId');
        $evaluatorId = is_numeric($param) ? (int) $param : null;

        if ($user->role === 'evaluator' && $evaluatorId !== null && (int) $user->evaluator_id === $evaluatorId) {
            return $next($request);
        }

        abort(403);
    }
}
