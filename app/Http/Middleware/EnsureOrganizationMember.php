<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * The tenant boundary. Every route under /orgs/{organization} passes through
 * here, and the request continues only if the signed-in user has a membership
 * row for that organization.
 *
 * Non-members get a 404, the same answer as for an organization that does not
 * exist, so the response never confirms that someone else's organization is there.
 */
class EnsureOrganizationMember
{
    public function handle(Request $request, Closure $next): Response
    {
        $organization = $request->route('organization');

        $role = $organization instanceof Organization
            ? $request->user()?->roleIn($organization)
            : null;

        abort_if($role === null, 404);

        View::share('role', $role);

        return $next($request);
    }
}
