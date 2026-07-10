# Getting Fast at Symfony

A step-by-step **Symfony 7 + Doctrine** port of [fastAPI-101](https://github.com/iammikek/fastAPI-101) — same items/categories API, same Laravel crossover style, different PHP framework.

**Audience:** Laravel developers who want to learn Symfony without leaving PHP. You already know routes, Eloquent, migrations, middleware, and FormRequests — this repo maps those ideas directly.

---

## What's Included

1. **Symfony 7 API** — attribute routes, controllers, service layer
2. **Doctrine ORM** — `Category`, `Item`, `User` entities + migrations
3. **JWT auth** — register, login, Bearer tokens on write endpoints (LexikJWTAuthenticationBundle)
4. **Pagination metadata** — `{ items, total, skip, limit }` on list endpoints
5. **Filtering** — `min_price`, `max_price`, `category_id`, `name_contains` on `GET /items`
6. **Item stats** — `GET /items/stats/summary` with per-category breakdown
7. **Rate limiting** — 10/min auth, 60/min writes (Symfony RateLimiter)
8. **SQLite locally** — PostgreSQL in Docker (port **8002**)
9. **Tests** — PHPUnit feature tests (19 tests)
10. **CI** — GitHub Actions

---

## Quick Start

### Local PHP (SQLite)

```bash
cd symfony-101
cp .env.example .env
bash bin/generate-jwt-keys.sh
composer install
php bin/console doctrine:migrations:migrate
make serve
```

Open **http://127.0.0.1:8002/** — root message  
**http://127.0.0.1:8002/items** — item list JSON (empty)

### Docker (PostgreSQL)

```bash
bash bin/generate-jwt-keys.sh   # keys mounted into container
docker compose up --build
```

API on **http://localhost:8002** (fastAPI-101 uses 8000, django-101 uses 8001).

### Tests

```bash
bash bin/generate-jwt-keys.sh
composer install
php bin/phpunit
```

---

## Project Structure

```
symfony-101/
├── bin/
│   ├── console
│   └── generate-jwt-keys.sh
├── config/
│   ├── packages/           # security, doctrine, rate_limiter, jwt, cors
│   └── jwt/                # RSA keys (gitignored, generate locally)
├── migrations/             # Doctrine migrations (Laravel migrations/)
├── public/index.php        # Front controller (Laravel public/index.php)
├── src/
│   ├── Controller/         # HTTP layer (Laravel controllers)
│   ├── Entity/             # Doctrine entities (Eloquent models)
│   ├── Service/            # Business logic (Laravel services)
│   ├── Repository/         # Custom queries (Eloquent scopes)
│   ├── Serializer/         # API response shaping (API Resources)
│   ├── Exception/          # Domain exceptions
│   └── EventSubscriber/      # Global error handler (Laravel Handler)
├── tests/
│   ├── ApiTestCase.php     # Base test + auth helpers
│   └── Feature/            # HTTP integration tests
├── docker-compose.yml
├── Dockerfile
├── Makefile
└── README.md
```

---

## Laravel ↔ Symfony map

| Laravel | symfony-101 |
|---------|-------------|
| `routes/api.php` | `#[Route]` attributes on controllers |
| `php artisan make:controller` | `php bin/console make:controller` |
| Eloquent `Model` | Doctrine `#[Entity]` |
| `php artisan migrate` | `php bin/console doctrine:migrations:migrate` |
| API Resources | `ApiSerializer` (or Symfony Serializer groups) |
| FormRequest validation | Symfony Validator `Assert` constraints |
| `auth:sanctum` | JWT + `#[IsGranted('ROLE_USER')]` |
| `throttle:10,1` | RateLimiter (`auth_api`, `write_api`) |
| `app/Services/` | `src/Service/` |
| `tests/Feature/` | `tests/Feature/` (PHPUnit WebTestCase) |
| `Handler.php` exceptions | `ApiExceptionSubscriber` |

| fastAPI-101 | symfony-101 |
|-------------|-------------|
| `APIRouter` | Controller classes + `#[Route]` |
| Pydantic schemas | Validator constraints + manual DTO parsing |
| SQLAlchemy models | Doctrine entities |
| Alembic | Doctrine migrations |
| `Depends(get_current_user)` | `#[IsGranted('ROLE_USER')]` |
| `python-jose` JWT | LexikJWTAuthenticationBundle |
| `slowapi` | Symfony RateLimiter |
| pytest + TestClient | PHPUnit + WebTestCase |

---

## Step 1: Project setup

```bash
composer create-project symfony/skeleton:"7.2.*" symfony-101
composer require symfony/orm-pack symfony/validator symfony/serializer \
  symfony/security-bundle lexik/jwt-authentication-bundle nelmio/cors-bundle \
  symfony/rate-limiter symfony/lock
composer require --dev symfony/maker-bundle phpunit/phpunit symfony/browser-kit
```

**Laravel parallel:** `composer create-project laravel/laravel` + installing Sanctum/Passport.

---

## Step 2: Entities + migrations

**`src/Entity/Item.php`** — Doctrine entity with `Category` many-to-one:

```php
#[ORM\Entity(repositoryClass: ItemRepository::class)]
class Item
{
    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $price;

    #[ORM\ManyToOne(targetEntity: Category::class, inversedBy: 'items')]
    private ?Category $category = null;
}
```

```bash
php bin/console make:migration
php bin/console doctrine:migrations:migrate
```

**Laravel parallel:** `php artisan make:model Item -m`

---

## Step 3: Service layer

Business logic lives in `src/Service/`, not controllers — same pattern as Laravel injecting a `ItemService`:

```php
// src/Service/ItemService.php
public function listItems(int $skip, int $limit, array $filters = []): array
{
    // Query builder with filters, pagination, total count
}
```

Controllers stay thin: validate input, call service, return JSON.

---

## Step 4: Controllers + routes

Symfony 7 uses PHP attributes instead of a routes file:

```php
#[Route('/items')]
class ItemController extends AbstractController
{
    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse { ... }

    #[Route('', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(Request $request): JsonResponse { ... }
}
```

**Laravel parallel:** `Route::apiResource('items', ItemController::class)` with `auth:sanctum` middleware on writes.

---

## Step 5: JWT authentication

1. Generate RSA keys: `bash bin/generate-jwt-keys.sh`
2. Configure `lexik_jwt_authentication.yaml`
3. `security.yaml` — stateless `jwt` firewall
4. `AuthController` — `POST /auth/register`, `POST /auth/login`, `GET /auth/me`

Login accepts OAuth2 form fields (`username` = email, `password`) like fastAPI-101:

```bash
curl -X POST http://127.0.0.1:8002/auth/login \
  -d 'username=alice@example.com&password=password123'
```

Returns `{ "access_token": "...", "token_type": "bearer" }`.

Protected writes:

```bash
curl -X POST http://127.0.0.1:8002/items \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{"name":"Widget","price":9.99}'
```

**Laravel parallel:** Sanctum token issuance + `auth:sanctum` middleware.

---

## Step 6: Error responses

`ApiExceptionSubscriber` returns the same `{ detail, code }` shape as fastAPI-101:

| Status | Code | When |
|--------|------|------|
| 404 | `ITEM_NOT_FOUND` | Missing item |
| 404 | `CATEGORY_NOT_FOUND` | Missing category |
| 409 | `CATEGORY_NAME_EXISTS` | Duplicate category name |
| 409 | `USER_EMAIL_EXISTS` | Duplicate email |
| 429 | `RATE_LIMIT_EXCEEDED` | Too many requests |

---

## Step 7: Tests

PHPUnit feature tests mirror fastAPI-101 coverage:

```bash
php bin/phpunit
```

`tests/ApiTestCase.php` provides:
- `resetDatabase()` between tests
- `createAuthenticatedClient()` — register + login
- `bearerHeaders()` — JWT for protected endpoints

**Laravel parallel:** Pest/PHPUnit feature tests with `$this->actingAs($user)`.

---

## API Endpoints

| Path | Method | Auth | Purpose |
|------|--------|------|---------|
| `/` | GET | — | Hello message |
| `/health` | GET | — | Health check |
| `/auth/register` | POST | — | Create user |
| `/auth/login` | POST | — | Get JWT |
| `/auth/me` | GET | JWT | Current user |
| `/categories` | GET | — | List categories |
| `/categories` | POST | JWT | Create category |
| `/categories/{id}` | GET/PATCH/DELETE | JWT on writes | Category CRUD |
| `/items` | GET | — | List/filter items |
| `/items/stats/summary` | GET | — | Item statistics |
| `/items/{id}` | GET/PATCH/DELETE | JWT on writes | Item CRUD |

---

## Environment Variables

See `.env.example`:

| Variable | Default | Purpose |
|----------|---------|---------|
| `DATABASE_URL` | SQLite `var/data.db` | Database connection |
| `APP_SECRET` | (required) | Symfony secret |
| `JWT_SECRET_KEY` | `config/jwt/private.pem` | JWT signing |
| `JWT_PUBLIC_KEY` | `config/jwt/public.pem` | JWT verification |

---

## How it fits the *-101 family

| Repo | Port | Stack |
|------|------|-------|
| fastAPI-101 | 8000 | Python reference API |
| django-101 | 8001 | Python monolith + admin + shop |
| **symfony-101** | **8002** | **PHP API (Laravel crossover)** |
| go-101 | 8000 | Go port |
| orchestr-101 | 3000 | Laravel-style Node |

Run all three Python/PHP backends side by side and hit the same endpoints with the same curl commands.

---

## Resources

- [Symfony Documentation](https://symfony.com/doc/current/index.html)
- [Doctrine ORM](https://www.doctrine-project.org/projects/doctrine-orm/en/latest/index.html)
- [LexikJWTAuthenticationBundle](https://github.com/lexik/LexikJWTAuthenticationBundle)
- [fastAPI-101](https://github.com/iammikek/fastAPI-101) — reference API shape
