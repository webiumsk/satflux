<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyOwnership
{
    public function handle(Request $request, Closure $next): Response
    {
        $company = $request->route('company');

        if (! $company instanceof Company) {
            abort(404, 'Company not found');
        }

        $user = $request->user();
        if ($company->user_id !== $user->id) {
            if (! $user->isSupport() && ! $user->isAdmin()) {
                abort(403, 'Unauthorized access to company');
            }
        }

        return $next($request);
    }
}
