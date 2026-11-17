<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\VersionController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/orgs');

// Which commit is live. The deploy pipeline polls this to confirm a release.
Route::get('/version', VersionController::class)->name('version');

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store']);
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:10,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    // Lists only the signed-in user's organizations (queried through their memberships).
    Route::get('/orgs', [OrganizationController::class, 'index'])->name('organizations.index');
    Route::post('/orgs', [OrganizationController::class, 'store'])->name('organizations.store');

    /*
     * Everything that belongs to one organization lives in this group.
     *
     *  - `member` (EnsureOrganizationMember) returns 404 unless the signed-in
     *    user is a member of {organization}.
     *  - scopeBindings() resolves {project} through $organization->projects()
     *    and {task} through $project->tasks(), so an id from another
     *    organization is a 404 even for someone who belongs to both.
     *
     * tests/Feature/TenantIsolationTest.php fails if a route is added under
     * /orgs/{organization} without the `member` middleware.
     */
    Route::prefix('/orgs/{organization}')
        ->middleware('member')
        ->scopeBindings()
        ->group(function () {
            // Any member: read everything, work on tasks.
            Route::get('/', [OrganizationController::class, 'show'])->name('organizations.show');
            Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
            Route::post('/projects/{project}/tasks', [TaskController::class, 'store'])->name('tasks.store');
            Route::get('/projects/{project}/tasks/{task}/edit', [TaskController::class, 'edit'])->name('tasks.edit');
            Route::put('/projects/{project}/tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
            Route::delete('/projects/{project}/tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');

            // Admins only: members, and projects with their budgets.
            Route::middleware('can:manage,organization')->group(function () {
                Route::post('/members', [MemberController::class, 'store'])->name('members.store');
                Route::put('/members/{user}', [MemberController::class, 'update'])->name('members.update');
                Route::delete('/members/{user}', [MemberController::class, 'destroy'])->name('members.destroy');

                Route::get('/projects/create', [ProjectController::class, 'create'])->name('projects.create');
                Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
                Route::get('/projects/{project}/edit', [ProjectController::class, 'edit'])->name('projects.edit');
                Route::put('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
                Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');
            });
        });
});
