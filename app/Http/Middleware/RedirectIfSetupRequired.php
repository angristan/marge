<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Actions\Admin\SetupAdmin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfSetupRequired
{
    /**
     * Handle an incoming request.
     * Redirects to setup if not complete.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! SetupAdmin::isComplete() && ! $request->routeIs('admin.setup*')) {
            return redirect()->route('admin.setup');
        }

        if (SetupAdmin::isComplete() && $request->routeIs('admin.setup')) {
            return redirect()->route('admin.dashboard');
        }

        return $next($request);
    }
}
