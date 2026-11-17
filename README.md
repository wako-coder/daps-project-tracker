# Project Tracker

A small multi-tenant project tracker. Several organizations share one app and one database; each organization sees only its own projects and tasks.

- **Live:** _added after the first deploy_
- **Pipeline:** see the Actions tab

## Architecture

- **Laravel 12, server-rendered Blade pages, session login.** No JavaScript build step.
- **PostgreSQL** in development, CI and production, so tests run on the engine that production uses.
- **One Docker image** (FrankenPHP) runs on Render's free tier; the database is a managed PostgreSQL.
- **GitHub Actions** tests every push and is the only way code reaches production.

Data model:

```
users ──< organization_user (role: admin | member) >── organizations ──< projects ──< tasks
```

- A user can belong to many organizations, with a different role in each.
- Money is stored as whole santim in `bigint` columns (`budget_santim`, `estimated_cost_santim`), never as floats.
- A project's **spent** is the estimated cost of its tasks that are done. **Planned** is the cost of all its tasks. Both are shown against the budget.

Roles: any member can read everything in the organization and create, edit and delete tasks. Only admins manage members and create, edit and delete projects (and so their budgets).

## How tenant isolation is enforced

Everything that belongs to an organization lives under one route group, `/orgs/{organization}/...`, in [routes/web.php](routes/web.php). Three things protect it, all on the server:

1. **Membership middleware.** [EnsureOrganizationMember](app/Http/Middleware/EnsureOrganizationMember.php) runs on every route in the group. It looks up the signed-in user's membership row for `{organization}` and returns **404** if there is none. 404 rather than 403, so the response does not confirm that the organization exists.
2. **Scoped route bindings.** `{project}` is loaded through `$organization->projects()` and `{task}` through `$project->tasks()`. A project id from another organization does not resolve, even for a user who belongs to both.
3. **The tenant never comes from the request body.** `organization_id` and `project_id` are not mass-assignable; rows are created only through the parent relation taken from the URL.

The organization list queries through the user's memberships (`$user->organizations()`), never `Organization::all()`.

[tests/Feature/TenantIsolationTest.php](tests/Feature/TenantIsolationTest.php) proves it. The main test walks **every** route under `/orgs/{organization}` with every HTTP method, as a signed-in admin of a different organization, and requires a 404 each time and unchanged data afterwards. New routes are covered automatically. A second test fails if a route is ever added to the group without the middleware.

### Where it could leak

- **A query written outside the pattern.** Isolation depends on always going through the relation (`$organization->projects()`). A future `Project::find($id)` or a raw SQL report would bypass it. The route-walking test catches this for HTTP routes, but not for background jobs, exports or console commands.
- **A new route outside the group** that takes a project or task id directly.
- **The database does not enforce it.** There is no row-level security; one application bug is enough. See hardening below.
- **Adding a member by email** tells an admin whether an email address has an account. Accepted for now, and limited to admins.

## Running it locally

Requires PHP 8.2+ with `pdo_pgsql`, Composer and Docker.

```bash
docker compose up -d            # PostgreSQL on port 5544, with a test database
composer install
cp .env.example .env            # then set DEMO_ADMIN_PASSWORD and DEMO_MEMBER_PASSWORD
php artisan key:generate
php artisan migrate --seed
php artisan serve
php artisan test
```
