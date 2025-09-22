<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\PersonalAccessToken;

class TokenAuth
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        \Log::info('TokenAuth middleware called', [
            'url' => $request->url(),
            'method' => $request->method(),
        ]);

        $token = $request->bearerToken();

        \Log::info('TokenAuth - Bearer token check', [
            'token_exists' => !empty($token),
            'token_length' => $token ? strlen($token) : 0,
        ]);

        if (!$token) {
            \Log::info('TokenAuth - No token provided, returning 401');
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Find the token in the database
        $personalAccessToken = PersonalAccessToken::where('token', hash('sha256', $token))->first();

        \Log::info('TokenAuth - Token lookup result', [
            'token_found' => !empty($personalAccessToken),
            'hashed_token' => hash('sha256', $token),
        ]);

        if (!$personalAccessToken) {
            \Log::info('TokenAuth - Invalid token, returning 401');
            return response()->json(['message' => 'Invalid token'], 401);
        }

        // Check if token is expired
        if ($personalAccessToken->expires_at && $personalAccessToken->expires_at->isPast()) {
            $personalAccessToken->delete();
            return response()->json(['message' => 'Token expired'], 401);
        }

        // Update last used timestamp
        $personalAccessToken->update(['last_used_at' => now()]);

        // Set the user for the request
        $user = $personalAccessToken->tokenable;
        
        \Log::info('TokenAuth - Setting user', [
            'user_id' => $user ? $user->id : null,
            'user_email' => $user ? $user->email : null,
        ]);
        
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        return $next($request);
    }
}
