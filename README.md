# TaskForge API

Multi-tenant SaaS REST API for task and project management.

Built with Laravel 12, PHP 8.3, MySQL, and Redis. Repository pattern, strict typing, and automated tenant isolation via global scopes.

[![CI/CD](https://github.com/username/taskforge-api/workflows/CI%2FCD%20Pipeline/badge.svg)](https://github.com/username/taskforge-api/actions)
[![PHP Version](https://img.shields.io/badge/PHP-8.3-blue)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-12.x-red)](https://laravel.com)

## Architecture

Layered design with strict boundaries:

```
Controller → Form Request → Service → Repository → Model
```

No business logic in controllers. No queries in services. Repositories behind interfaces. Every PHP file declares strict types.

### Multi-tenancy

Data isolation by `tenant_id` enforced at the query level via Eloquent global scopes. Controllers never see cross-tenant data.

### Key decisions

- **Repository pattern** — data access abstracted behind interfaces, swappable implementations, cacheable at the base layer
- **Service layer** — business logic lives here, not in controllers or models
- **Strict types** — `declare(strict_types=1)` on every file, no exceptions
- **Atomic transactions** — registration, invitations, ownership transfers all run in DB transactions
- **Pest PHP** — tests are readable, fast, and cover architecture boundaries

## Quick start

```bash
git clone https://github.com/username/taskforge-api.git
cd taskforge-api
make setup
```

That's it. Docker handles PHP, MySQL, Redis, and Nginx.

## Commands

```
make setup       First-time setup
make up          Start containers
make down        Stop containers
make test        Run tests
make lint        Check code style
make migrate     Run migrations
make fresh       Drop, migrate, and seed
make shell       Enter app container
```

## API

All endpoints prefixed with `/api`. Authentication via Laravel Sanctum bearer tokens.

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/auth/register` | No | Create tenant + owner account |
| POST | `/auth/login` | No | Authenticate and get token |
| POST | `/auth/logout` | Yes | Revoke all tokens |
| GET | `/auth/me` | Yes | Current user profile |
| GET | `/tenant` | Yes | Tenant details |
| PUT | `/tenant` | Yes | Update tenant settings |
| POST | `/tenant/transfer-ownership` | Yes | Transfer tenant ownership |
| POST | `/invitations` | Yes | Invite member by email |
| GET | `/invitations` | Yes | List pending invitations |
| DELETE | `/invitations/{id}` | Yes | Cancel invitation |
| POST | `/invitations/accept` | No | Accept invitation, create account |

## Tech stack

- **Laravel 12** — framework
- **PHP 8.3** — runtime, strict types
- **MySQL 8.0** — primary datastore
- **Redis** — cache, sessions, queues
- **Laravel Sanctum** — API authentication
- **Spatie Permission** — role-based access control
- **Spatie Activity Log** — audit trail
- **Pest PHP** — testing framework
- **GitHub Actions** — CI/CD pipeline

## Project structure

```
app/
├── Http/Controllers/     Request handling and response formatting
├── Http/Requests/        Input validation
├── Models/               Eloquent entities
├── Repositories/         Data access layer
│   └── Contracts/        Repository interfaces
├── Scopes/               Eloquent global scopes
├── Services/             Business logic
└── Traits/               Reusable model behavior

tests/
├── Feature/              HTTP endpoint tests
└── Unit/                 Architecture and domain tests
```

## Testing

```bash
make test              # All tests
make test-coverage     # With coverage report
```

Architecture tests enforce layer boundaries and strict typing. Feature tests cover every endpoint.

## CI/CD

Every push triggers: lint → test → Docker build. Merges to `main` deploy to production, `develop` to staging.

## License

MIT
