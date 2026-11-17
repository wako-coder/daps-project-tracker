<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OrganizationController extends Controller
{
    public function index(Request $request): View
    {
        // Through the membership relation, never Organization::all().
        $organizations = $request->user()
            ->organizations()
            ->withCount(['projects', 'users'])
            ->orderBy('name')
            ->get();

        return view('organizations.index', ['organizations' => $organizations]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        // The creator becomes the first admin; both rows or neither.
        $organization = DB::transaction(function () use ($request, $data) {
            $organization = Organization::create($data);
            $organization->users()->attach($request->user(), ['role' => Role::Admin->value]);

            return $organization;
        });

        return redirect()
            ->route('organizations.show', $organization)
            ->with('status', 'Organization created.');
    }

    public function show(Organization $organization): View
    {
        return view('organizations.show', [
            'organization' => $organization,
            'projects' => $organization->projects()->withCostTotals()->withCount('tasks')->orderBy('name')->get(),
            'members' => $organization->users()->orderBy('name')->get(),
            'roles' => Role::cases(),
        ]);
    }
}
