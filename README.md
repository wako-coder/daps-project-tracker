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

Each task also has a **priority** (low, medium or high), and a project's task list can be filtered by it. The budget figures always cover the whole project, whatever the filter.

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

## How the pipeline works

[.github/workflows/ci-cd.yml](.github/workflows/ci-cd.yml). Render's own auto-deploy is turned off in [render.yaml](render.yaml), so this workflow is the only way code reaches production.

1. **Every push and pull request** starts the `Test` job.
2. `Test` starts a PostgreSQL 16 service, installs PHP 8.3 and the Composer dependencies, checks code style with Pint, and runs the whole test suite against PostgreSQL.
3. **`Deploy` needs `Test`.** It runs only for a push to `main`, and only after `Test` passed. A pull request or a red build never deploys.
4. `Deploy` calls Render's deploy hook (a repository secret) and asks for this exact commit.
5. Render builds the Docker image and starts it. [docker/entrypoint.sh](docker/entrypoint.sh) runs `php artisan migrate --force` before the app accepts traffic. If the migration fails the container exits and Render keeps the previous release serving.
6. The job polls `/version` until the live site reports this commit (Render sets `RENDER_GIT_COMMIT`). A deploy that never comes up turns the run red after 15 minutes.
7. A smoke test requests `/up` and `/login` on the live URL.

Runs on `main` are never cancelled part way, so a newer push cannot overtake a deploy in progress.

## The priority migration

[database/migrations/2026_11_18_000001_add_priority_to_tasks_table.php](database/migrations/2026_11_18_000001_add_priority_to_tasks_table.php)

**Up**, in one transaction (PostgreSQL runs schema changes transactionally):

1. Add `priority` as a nullable `varchar(10)`.
2. Backfill: `UPDATE tasks SET priority = 'medium' WHERE priority IS NULL`. Every existing task becomes medium.
3. Make the column `NOT NULL` with default `medium`, and index `(project_id, priority)` for the filter.
4. Add a `CHECK` constraint so the database itself only accepts `low`, `medium` or `high`.

It ships like any other change: merging to `main` runs the tests, and the container runs the migration on start-up against the live database. The demo seeder sees its data already exists and does nothing, so the seeded organization, users, project and tasks stay as they were, now with priority medium.

**Rolling back.** `down()` drops the constraint, the index and the column, and nothing else. The plan, in order:

1. **Revert the code.** Revert the merge commit on `main` and push; the pipeline tests and deploys it like any change. The old code works against the new schema: it never reads `priority`, and new rows get the column default `medium`. The site is back to the previous behaviour within one deploy.
2. **Then, if the column should go too,** ship a new migration whose `up()` does what this one's `down()` does, and let the pipeline deploy it. Running `migrate:rollback` by hand is the alternative, but it has no safe moment: before step 1 the running code still reads the column, and after step 1 the migration file is gone.

The only data lost is the priorities chosen after the deploy. Titles, costs, statuses and budgets are untouched. On a site with real users, take a database backup (Render dashboard, or `pg_dump`) before step 2.

Locally, or in a rehearsal, `php artisan migrate:rollback --step=1` runs `down()` directly.

[tests/Feature/TaskPriorityTest.php](tests/Feature/TaskPriorityTest.php) rehearses this: it puts the schema back to the version before priorities, inserts tasks the old way, runs the migration, checks they are all medium, then rolls back and migrates again.

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
