<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Zona /producer: utilizatorul trebuie să aibă un profil de producător.
 * Un producător vede DOAR propriile date (izolare server-side).
 */
class EnsureProducer
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || ! $user->producer) {
            if ($user && $user->hasRole('producer')) {
                return redirect()->route('producer.onboarding');
            }
            abort(403);
        }

        return $next($request);
    }
}
