# TaskForge API

Multi-tenant REST API for task and project management. Laravel 12, PHP 8.3, MySQL 8, Redis.

Every model is tenant-scoped. Every file declares strict types. No exceptions.

## Setup

```bash
git clone <repo-url>
cd taskforge-api
make setup
```

Docker handles the rest. App runs at `http://localhost:8000`.

## Make targets

```
make setup          Build containers, install deps, generate key, run migrations
make up             Start containers
make down           Stop containers
make restart        Restart containers
make build          Rebuild from scratch (no cache)
make shell          Shell into app container
make test           Run test suite
make test-coverage  Run tests with coverage (min 80%)
make lint           Check code style (Pint)
make lint-fix       Fix code style
make migrate        Run migrations
make fresh          Drop everything, migrate, seed
make seed           Run seeders
make cache-clear    Clear all caches
make optimize       Cache config, routes, views
make queue-work     Start queue worker
make tinker         REPL
make mysql          MySQL CLI
make redis          Redis CLI
```

## Architecture

```
Request → Controller → FormRequest → Service → Repository → Model
```

- **Controllers** return responses. No business logic.
- **Services** contain business logic. No queries.
- **Repositories** abstract data access behind interfaces. Cacheable.
- **Models** define relationships and scopes. No logic beyond that.
- **Policies** handle authorization per-model.
- **Global scopes** enforce `tenant_id` filtering on every query. Automatic.

## Multi-tenancy

Every tenant-scoped model uses the `BelongsToTenant` trait. A global scope filters by `tenant_id` derived from the authenticated user. The `tenant` middleware resolves this on every request. Cross-tenant data access is structurally impossible at the query level.

## RBAC

Four roles, granular permissions. Enforced via Spatie Permission + model policies.

| Role | Access |
|------|--------|
| **owner** | Everything, including tenant transfer and deletion |
| **admin** | Full CRUD on all resources except tenant management |
| **member** | CRUD on tasks, comments, tag creation, project view/create |
| **viewer** | Read-only across all resources |

Permissions: `workspace.*`, `project.*`, `task.*`, `tag.*`, `comment.*`, `member.*`, `tenant.*`

## API

All endpoints under `/api`. Auth via Sanctum bearer tokens. Rate-limited.

### Auth

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/auth/register` | No | Create tenant + owner account |
| POST | `/auth/login` | No | Get bearer token |
| POST | `/auth/logout` | Yes | Revoke all tokens |
| POST | `/auth/logout-current-device` | Yes | Revoke current token |
| GET | `/auth/me` | Yes | Current user |

### Tenant

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/tenant` | Show tenant |
| PUT | `/tenant` | Update tenant |
| POST | `/tenant/transfer-ownership` | Transfer ownership |

### Members

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/members` | List tenant members |
| GET | `/members/{id}` | Show member |
| PATCH | `/members/{id}` | Update member role |
| DELETE | `/members/{id}` | Remove member |

### Invitations

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/invitations` | Yes | Send invitation |
| GET | `/invitations` | Yes | List pending |
| DELETE | `/invitations/{id}` | Yes | Cancel |
| POST | `/invitations/accept` | No | Accept and create account |

### Workspaces

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/workspaces` | List |
| POST | `/workspaces` | Create |
| GET | `/workspaces/{id}` | Show |
| PUT | `/workspaces/{id}` | Update |
| DELETE | `/workspaces/{id}` | Delete |
| GET | `/workspaces/{id}/members` | List members |
| POST | `/workspaces/{id}/members` | Add member |
| POST | `/workspaces/{id}/members/bulk` | Add multiple |
| DELETE | `/workspaces/{id}/members/{user}` | Remove member |

### Projects

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/projects` | List |
| POST | `/projects` | Create |
| GET | `/projects/{id}` | Show |
| PUT | `/projects/{id}` | Update |
| DELETE | `/projects/{id}` | Delete |
| GET | `/projects/{id}/members` | List members |
| POST | `/projects/{id}/members` | Add member |
| POST | `/projects/{id}/members/bulk` | Add multiple |
| DELETE | `/projects/{id}/members/{user}` | Remove member |
| GET | `/projects/{id}/columns` | List columns |
| POST | `/projects/{id}/columns` | Create column |
| GET | `/projects/{id}/columns/{col}` | Show column |
| PUT | `/projects/{id}/columns/{col}` | Update column |
| DELETE | `/projects/{id}/columns/{col}` | Delete column |
| POST | `/projects/{id}/columns/reorder` | Reorder columns |
| GET | `/projects/{id}/activity` | Activity history |

### Tasks

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/tasks` | List (filterable) |
| POST | `/tasks` | Create |
| GET | `/tasks/{id}` | Show |
| PUT | `/tasks/{id}` | Update |
| DELETE | `/tasks/{id}` | Soft delete |
| GET | `/tasks/{id}/subtasks` | List subtasks |
| GET | `/tasks/{id}/assignees` | List assignees |
| POST | `/tasks/{id}/assignees` | Assign user |
| POST | `/tasks/{id}/assignees/bulk` | Assign multiple |
| DELETE | `/tasks/{id}/assignees/{user}` | Unassign |
| GET | `/tasks/{id}/tags` | List tags |
| POST | `/tasks/{id}/tags` | Attach tags |
| DELETE | `/tasks/{id}/tags/{tag}` | Detach tag |
| GET | `/tasks/{id}/comments` | List comments |
| POST | `/tasks/{id}/comments` | Add comment |
| PUT | `/tasks/{id}/comments/{cid}` | Edit comment |
| DELETE | `/tasks/{id}/comments/{cid}` | Delete comment |
| GET | `/tasks/{id}/attachments` | List files |
| POST | `/tasks/{id}/attachments` | Upload file |
| DELETE | `/tasks/{id}/attachments/{mid}` | Delete file |
| GET | `/tasks/{id}/activity` | Activity history |

**Task filters:** `project_id`, `project_column_id`, `priority`, `parent_id`, `assignee_id`, `tag_id`, `deadline` (`overdue`, `upcoming`, `today`, date range via `deadline_from`/`deadline_to`), `order_by`, `order_dir`.

### Tags

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/tags` | List (searchable) |
| POST | `/tags` | Create |
| GET | `/tags/{id}` | Show |
| PUT | `/tags/{id}` | Update |
| DELETE | `/tags/{id}` | Delete |

## Data model

```
Tenant
├── User (members, roles)
├── Workspace
│   └── Project
│       ├── ProjectColumn (ordered)
│       └── Task
│           ├── Task (subtasks, self-referential)
│           ├── User (assignees, pivot)
│           ├── Tag (pivot)
│           ├── Comment (with @mentions)
│           └── Media (attachments via Spatie)
├── Tag
└── Invitation
```

## Features

- **Multi-tenancy** — automatic tenant isolation via global scopes
- **Workspaces & projects** — hierarchical organization with member management
- **Kanban columns** — per-project, ordered, reorderable
- **Tasks** — CRUD, soft deletes, priorities, deadlines, subtasks
- **Assignment** — single or bulk user assignment to tasks
- **Tags** — tenant-scoped, colored, attachable to tasks, filterable
- **Comments** — markdown content, `@username` mention extraction, soft deletes
- **Attachments** — file upload/download/delete via Spatie Media Library (10MB limit)
- **Activity log** — tracks changes with before/after values on tasks and projects
- **RBAC** — four roles, granular permissions, policy-based authorization
- **Invitations** — email-based, token-secured tenant member onboarding

## Stack

| Component | Tool |
|-----------|------|
| Framework | Laravel 12 |
| Runtime | PHP 8.3 |
| Database | MySQL 8.0 |
| Cache/Queue | Redis |
| Auth | Laravel Sanctum |
| RBAC | Spatie Permission |
| Audit trail | Spatie Activitylog |
| File storage | Spatie Media Library |
| Tests | Pest PHP |
| Code style | Laravel Pint |
| CI/CD | GitHub Actions |
| Containers | Docker Compose |

## Project structure

```
app/
├── Http/
│   ├── Controllers/      17 controllers
│   ├── Requests/         Form request validation
│   └── Resources/        API resource transformers
├── Models/               9 Eloquent models
├── Policies/             Authorization policies
├── Repositories/         Data access layer
│   └── Contracts/        Repository interfaces
├── Scopes/               Global tenant scope
├── Services/             Business logic
└── Traits/               BelongsToTenant, etc.

database/
├── factories/            Model factories
├── migrations/           Schema definitions
└── seeders/              Role & permission seeder

tests/
├── Feature/              20+ endpoint test files
└── Unit/                 Architecture & permission tests
```

## Testing

```bash
make test
make test-coverage
```

Tests cover auth, multi-tenancy, RBAC, CRUD for every resource, assignments, tags, comments, activity log, deadlines, subtasks, and architectural constraints.

## License

MIT
