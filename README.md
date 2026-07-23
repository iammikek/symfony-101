# Getting Fast at Symfony

A step-by-step **Symfony 7 + Doctrine** port of [fastAPI-101](https://github.com/iammikek/fastAPI-101) — same items/categories API, same Laravel crossover style, different PHP framework.

**Audience:** Laravel developers who want to learn Symfony without leaving PHP. You already know routes, Eloquent, migrations, middleware, and FormRequests — this repo maps those ideas directly.

**Monolith UI:** Symfony owns the JSON API plus a **server-rendered shop** at `/shop` (Twig + forms + session auth) — see **[docs/frontend.md](docs/frontend.md)**. Same pattern planned for laravel-101.

---

## What's Included

1. **Symfony 7 project** — attribute routes, autowired services, `config/packages/`
2. **`User` entity** — email login, register/login/me, JWT (LexikJWTAuthenticationBundle)
3. **`Category` + `Item` entities** — Doctrine ORM, repositories, service layer
4. **Service layer** — `src/Service/` (mirrors fastAPI-101 business logic)
5. **Pagination** — `{ items, total, skip, limit }` (same shape as FastAPI Step 20)
6. **Filtering** — `min_price`, `max_price`, `category_id`, `name_contains` on `GET /items`
7. **Item stats** — `GET /items/stats/summary` with per-category breakdown
8. **JWT auth** — Bearer tokens on write endpoints (register/login/me)
9. **Rate limiting** — 10/min auth, 60/min writes (Symfony RateLimiter)
10. **Catalog Shop** — server-rendered HTML at `/shop` (Twig, forms, session auth) — see **[docs/frontend.md](docs/frontend.md)**
11. **SQLite locally** — PostgreSQL in Docker (port **8002**)
12. **Tests** — PHPUnit feature tests (28 tests)
13. **CI** — GitHub Actions

---

## Table of Contents

1. [Quick Start](#1-quick-start)
2. [Project Structure](#2-project-structure)
3. [Framework maps](#3-framework-maps)
4. [Step 1: Project setup](#4-step-1-project-setup)
5. [Step 2: Health routes](#5-step-2-health-routes)
6. [Step 3: Item entity + migrations](#6-step-3-item-entity--migrations)
7. [Step 4: Service layer](#7-step-4-service-layer)
8. [Step 5: Item controller (CRUD)](#8-step-5-item-controller-crud)
9. [Step 6: Tests](#9-step-6-tests)
10. [Step 7: Categories + FK](#10-step-7-categories--fk)
11. [Step 8: Filtering](#11-step-8-filtering)
12. [Step 9: Pagination metadata](#12-step-9-pagination-metadata)
13. [Step 10: Item stats capstone](#13-step-10-item-stats-capstone)
14. [Step 11: JWT authentication](#14-step-11-jwt-authentication)
15. [Step 12: Rate limiting](#15-step-12-rate-limiting)
16. [Step 13: Error responses](#16-step-13-error-responses)
17. [Step 14: PostgreSQL (Docker)](#17-step-14-postgresql-docker)
18. [Step 15: CI](#18-step-15-ci)
19. [Step 16: Server-rendered shop](#19-step-16-server-rendered-shop)
20. [Quick Reference](#20-quick-reference)

---

## 1. Quick Start

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
**http://127.0.0.1:8002/shop** — browser UI (register, browse, add items)

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

## 2. Project Structure

```
symfony-101/
├── bin/
│   ├── console                 # Symfony CLI (Laravel artisan)
│   └── generate-jwt-keys.sh    # RSA keys for Lexik JWT
├── config/
│   ├── packages/               # security, doctrine, rate_limiter, jwt, cors
│   ├── routes.yaml             # loads src/Controller/* attributes
│   └── jwt/                    # RSA keys (gitignored)
├── migrations/                 # Doctrine migrations
├── public/
│   ├── index.php               # Front controller (Laravel public/index.php)
│   ├── router.php              # PHP built-in server router (make serve)
│   ├── serve-static.php        # Correct MIME types for shop CSS/JS
│   └── shop/style.css
├── src/
│   ├── Controller/             # HTTP layer
│   │   ├── HealthController.php
│   │   ├── AuthController.php
│   │   ├── ItemController.php
│   │   ├── CategoryController.php
│   │   └── Shop/               # Twig views (/shop)
│   ├── Form/                   # HTML forms for shop
│   ├── Entity/                 # Doctrine entities (Eloquent models)
│   ├── Service/                # Business logic
│   ├── Repository/             # Custom queries
│   ├── Serializer/ApiSerializer.php
│   ├── Exception/
│   └── EventSubscriber/
├── templates/shop/             # Twig templates
├── docs/frontend.md
├── tests/
│   ├── ApiTestCase.php
│   └── Feature/                # API + shop tests
├── docker-compose.yml
├── Makefile
└── README.md
```

---

## 3. Framework maps

| fastAPI-101 | symfony-101 |
|-------------|-------------|
| `app/main.py` | `config/` + `src/Kernel.php` |
| `APIRouter` | Controller classes + `#[Route]` attributes |
| Pydantic schemas | Symfony Validator + `ApiSerializer` |
| SQLAlchemy models | Doctrine `#[Entity]` |
| Alembic | Doctrine migrations |
| `Depends(get_current_user)` | `#[IsGranted('ROLE_USER')]` |
| `python-jose` JWT | LexikJWTAuthenticationBundle |
| `slowapi` | Symfony RateLimiter |
| pytest + TestClient | PHPUnit + WebTestCase |

| Laravel | symfony-101 |
|---------|-------------|
| `routes/api.php` | `#[Route]` on controllers (`config/routes.yaml` loads them) |
| `php artisan` | `php bin/console` |
| `php artisan make:model Item -m` | `make:entity` + `make:migration` |
| Eloquent | Doctrine ORM |
| API Resources | `ApiSerializer` |
| FormRequest | Validator `Assert` constraints in controller |
| `auth:sanctum` | JWT firewall + `#[IsGranted('ROLE_USER')]` |
| `throttle:10,1` | `auth_api` / `write_api` rate limiters |
| `app/Services/` | `src/Service/` (autowired) |
| `Handler.php` | `ApiExceptionSubscriber` |
| Pest/PHPUnit Feature tests | `tests/Feature/` + `ApiTestCase` |

---

## 4. Step 1: Project setup

This repo was bootstrapped with:

```bash
composer create-project symfony/skeleton:"7.2.*" symfony-101
composer require symfony/orm-pack symfony/validator symfony/serializer \
  symfony/security-bundle lexik/jwt-authentication-bundle nelmio/cors-bundle \
  symfony/rate-limiter symfony/lock
composer require --dev symfony/maker-bundle phpunit/phpunit symfony/browser-kit
```

**Symfony gotcha:** RateLimiter requires the Lock component. Add `LOCK_DSN=flock` to `.env` (see `.env.example`).

**Laravel parallel:** `composer create-project laravel/laravel` + Sanctum/Passport.

**Verify:**

```bash
php bin/console about
```

---

## 5. Step 2: Health routes

**`src/Controller/HealthController.php`** — root and health check (like fastAPI-101 `/` and `/health`):

```php
#[Route('/', name: 'root', methods: ['GET'])]
public function root(): JsonResponse
{
    return new JsonResponse(['message' => 'Hello from symfony-101']);
}

#[Route('/health', name: 'health', methods: ['GET'])]
public function health(): JsonResponse
{
    return new JsonResponse(['status' => 'ok']);
}
```

Routes are discovered from attributes via `config/routes.yaml`:

```yaml
controllers:
    resource:
        path: ../src/Controller/
        namespace: App\Controller
    type: attribute
```

**Laravel parallel:** two routes in `routes/api.php` pointing at a `HealthController`.

**Verify:**

```bash
curl http://127.0.0.1:8002/
curl http://127.0.0.1:8002/health
```

---

## 6. Step 3: Item entity + migrations

**`src/Entity/Item.php`** — Doctrine entity (Eloquent equivalent):

```php
#[ORM\Entity(repositoryClass: ItemRepository::class)]
#[ORM\Table(name: 'items')]
class Item
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $price;

    #[ORM\ManyToOne(targetEntity: Category::class, inversedBy: 'items')]
    private ?Category $category = null;
}
```

Generate and run migration:

```bash
php bin/console make:migration
php bin/console doctrine:migrations:migrate
```

**Symfony gotcha:** Doctrine stores decimals as strings in PHP (`"9.99"`), not floats. Cast to `float` in JSON responses.

**Laravel parallel:** `php artisan make:model Item -m`

**Verify:**

```bash
php bin/console doctrine:schema:validate
```

---

## 7. Step 4: Service layer

Business logic lives in **`src/Service/`**, not controllers — same pattern as injecting an `ItemService` in Laravel.

**`src/Service/ItemService.php`** handles queries, validation of FKs, and stats:

```php
public function listItems(int $skip, int $limit, array $filters = []): array
{
    $qb = $this->itemRepository->createQueryBuilder('i')
        ->leftJoin('i.category', 'c')
        ->addSelect('c');

    if (isset($filters['min_price'])) {
        $qb->andWhere('i.price >= :min_price')->setParameter('min_price', (string) $filters['min_price']);
    }
    // ... max_price, category_id, name_contains

    $total = /* count query */;
    $rows = $qb->setFirstResult($skip)->setMaxResults($limit)->getQuery()->getResult();

    return [$rows, $total];
}
```

Controllers stay thin: validate input → call service → return JSON via `ApiSerializer`.

**Laravel parallel:** `app/Services/ItemService.php` called from a slim controller.

---

## 8. Step 5: Item controller (CRUD)

**`src/Controller/ItemController.php`** — class-level route prefix:

```php
#[Route('/items')]
class ItemController extends AbstractController
{
    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse { ... }

    #[Route('/stats/summary', methods: ['GET'])]
    public function stats(): JsonResponse { ... }

    #[Route('/{itemId}', methods: ['GET'], requirements: ['itemId' => '\d+'])]
    public function show(int $itemId): JsonResponse { ... }

    #[Route('', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(Request $request): JsonResponse { ... }
}
```

| Method | Path | Auth |
|--------|------|------|
| GET | `/items` | Public |
| GET | `/items/{id}` | Public |
| GET | `/items/stats/summary` | Public |
| POST | `/items` | JWT |
| PATCH | `/items/{id}` | JWT |
| DELETE | `/items/{id}` | JWT |

**Symfony gotcha:** Define `/stats/summary` **before** `/{itemId}` or Symfony will treat `"stats"` as an ID. We use a dedicated route method with a static path segment.

**Laravel parallel:** `Route::apiResource('items', ItemController::class)` + `Route::get('items/stats/summary', ...)`.

**Verify** (after Step 11 JWT):

```bash
curl http://127.0.0.1:8002/items
# {"items":[],"total":0,"skip":0,"limit":10}
```

---

## 9. Step 6: Tests

**`tests/ApiTestCase.php`** — shared helpers (Laravel `TestCase` equivalent):

```php
protected function createAuthenticatedClient(): KernelBrowser
{
    $client = static::createClient();
    $this->resetDatabase();
    $client->request('POST', '/auth/register', ...);
    $client->request('POST', '/auth/login', parameters: ['username' => '...', 'password' => '...']);
    return $client;
}

protected function bearerHeaders(KernelBrowser $client): array
{
    $data = json_decode($client->getResponse()->getContent(), true);
    return ['HTTP_AUTHORIZATION' => 'Bearer ' . $data['access_token']];
}
```

**`tests/bootstrap.php`** runs Doctrine migrations once in the `test` environment.

```bash
php bin/phpunit
```

**28 tests** covering health, auth, items, categories, and shop pages.

**Laravel parallel:** Pest feature tests with `$this->actingAs($user)` — here we register + login to get a real JWT.

---

## 10. Step 7: Categories + FK

**`src/Entity/Category.php`** + nullable `Item.category` many-to-one.

Business rules in **`src/Service/CategoryService.php`**:

| Rule | Exception | HTTP |
|------|-----------|------|
| Duplicate category name | `CategoryNameExistsException` | 409 `CATEGORY_NAME_EXISTS` |
| Delete category with items | `CategoryInUseException` | 409 `CATEGORY_IN_USE` |
| Invalid `category_id` on item | `CategoryNotFoundException` | 404 `CATEGORY_NOT_FOUND` |

**Verify:**

```bash
# After login (see Step 11)
curl -X POST http://127.0.0.1:8002/categories \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name":"Tools","description":"Hand tools"}'
```

---

## 11. Step 8: Filtering

`GET /items?min_price=10&category_id=1&name_contains=widget`

Implemented in `ItemService::listItems()` with Doctrine query builder:

- `min_price` / `max_price` — `i.price >=` / `<=`
- `category_id` — `i.category = :category_id`
- `name_contains` — `LOWER(i.name) LIKE` (case-insensitive)

Invalid query params → **422** (Validator on the controller).

**Laravel parallel:** local scopes / `$query->when($request->min_price, ...)`.

**Verify:**

```bash
curl "http://127.0.0.1:8002/items?min_price=10&name_contains=widget"
```

---

## 12. Step 9: Pagination metadata

Same response shape as fastAPI-101 and django-101:

```json
{
  "items": [ ... ],
  "total": 42,
  "skip": 0,
  "limit": 10
}
```

Query params: `skip` (≥0), `limit` (1–100). Invalid values → **422**.

**Verify:**

```bash
curl "http://127.0.0.1:8002/items?skip=0&limit=5"
```

---

## 13. Step 10: Item stats capstone

`GET /items/stats/summary` — aggregates in `ItemService::getStats()`:

```json
{
  "total_items": 5,
  "average_price": 12.5,
  "min_price": 5.0,
  "max_price": 20.0,
  "uncategorized_count": 1,
  "by_category": [
    {
      "category_id": 1,
      "category_name": "Tools",
      "item_count": 2,
      "average_price": 10.0
    }
  ]
}
```

Empty database returns zeros and `null` min/max — same as fastAPI-101.

**Verify:**

```bash
curl http://127.0.0.1:8002/items/stats/summary
```

---

## 14. Step 11: JWT authentication

### Generate keys

JWT keys are **not** in git. Generate locally and in CI:

```bash
bash bin/generate-jwt-keys.sh
```

Creates `config/jwt/private.pem` and `config/jwt/public.pem`.

### Security config

**`config/packages/security.yaml`** — stateless JWT firewall:

```yaml
firewalls:
    api:
        pattern: ^/
        stateless: true
        provider: app_user_provider
        jwt: ~
```

Write endpoints use `#[IsGranted('ROLE_USER')]` on controller methods (like applying `auth:sanctum` to specific actions).

### Auth endpoints

| Endpoint | Purpose |
|----------|---------|
| `POST /auth/register` | `{ email, password }` → 201 |
| `POST /auth/login` | form `username` + `password` — **username = email** (FastAPI parity) |
| `GET /auth/me` | Bearer token required |

**Try it:**

```bash
# Register
curl -X POST http://127.0.0.1:8002/auth/register \
  -H "Content-Type: application/json" \
  -d '{"email":"you@example.com","password":"password123"}'

# Login (OAuth2-style form fields, like fastAPI-101)
curl -X POST http://127.0.0.1:8002/auth/login \
  -d 'username=you@example.com&password=password123'

# Create item
curl -X POST http://127.0.0.1:8002/items \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name":"Widget","price":9.99}'
```

**Laravel parallel:** Sanctum personal access tokens — here Lexik issues a JWT instead.

---

## 15. Step 12: Rate limiting

**`config/packages/rate_limiter.yaml`:**

```yaml
framework:
    rate_limiter:
        auth_api:
            policy: 'fixed_window'
            limit: 10
            interval: '1 minute'
        write_api:
            policy: 'fixed_window'
            limit: 60
            interval: '1 minute'
```

Injected into controllers via `config/services.yaml`:

```yaml
App\Controller\AuthController:
    bind:
        $authApiLimiter: '@limiter.auth_api'
```

| Endpoint group | Limit |
|----------------|-------|
| `/auth/register`, `/auth/login` | 10/minute per IP |
| POST/PATCH/DELETE on items & categories | 60/minute per IP |

**429 response:** `{ "detail": "Rate limit exceeded", "code": "RATE_LIMIT_EXCEEDED" }`

**Symfony gotcha:** RateLimiter needs Lock. Set in `.env`:

```env
LOCK_DSN=flock
```

Without this, controllers fail to instantiate with `Environment variable not found: "LOCK_DSN"`.

**Laravel parallel:** `throttle:10,1` middleware.

Rate limits are **disabled in the `test` environment** so PHPUnit stays fast.

---

## 16. Step 13: Error responses

**`src/EventSubscriber/ApiExceptionSubscriber.php`** maps domain exceptions to the same `{ detail, code }` JSON as fastAPI-101:

| Status | Code | When |
|--------|------|------|
| 404 | `ITEM_NOT_FOUND` | Missing item |
| 404 | `CATEGORY_NOT_FOUND` | Missing category |
| 409 | `CATEGORY_NAME_EXISTS` | Duplicate category name |
| 409 | `CATEGORY_IN_USE` | Category has items |
| 409 | `USER_EMAIL_EXISTS` | Duplicate email |
| 429 | `RATE_LIMIT_EXCEEDED` | Too many requests |

**Laravel parallel:** `app/Exceptions/Handler.php` rendering JSON for API requests.

**Verify:**

```bash
curl http://127.0.0.1:8002/items/999
# {"detail":"Item not found","code":"ITEM_NOT_FOUND"}
```

---

## 17. Step 14: PostgreSQL (Docker)

```bash
bash bin/generate-jwt-keys.sh
docker compose up --build
```

- **`database`** — Postgres 16 on host port **5434**
- **`api`** — Symfony on host port **8002**
- Migrations run on container startup

Local `make serve` still uses SQLite (`var/data.db`).

**Verify:**

```bash
curl http://localhost:8002/health
docker compose exec database psql -U app -d app -c "\dt"
```

---

## 18. Step 15: CI

**`.github/workflows/ci.yml`** runs on every push to `main`:

1. Generate JWT keys (`bin/generate-jwt-keys.sh`)
2. Copy `.env.example` → `.env` (Composer post-install scripts need it)
3. `composer install`
4. `doctrine:migrations:migrate --env=test`
5. `php bin/phpunit`

**CI gotchas we hit:**

| Problem | Fix |
|---------|-----|
| `Unable to read .env` | `cp .env.example .env` before `composer install` |
| `LOCK_DSN` not found | Added `LOCK_DSN=flock` to `.env.example` |
| JWT keys missing | Generate in CI step (keys are gitignored) |

---

## 19. Step 16: Server-rendered shop

A **Catalog Shop** at `/shop` demonstrates full-stack Symfony alongside the JSON API:

| Shop (browser) | API (JSON) |
|----------------|------------|
| `/shop/register` — signup + auto-login | `POST /auth/register` — JSON only |
| `/shop/login` — session cookie | `POST /auth/login` — JWT |
| `/shop/items` — HTML table + filters | `GET /items` — JSON list |
| `/shop/items/new` — HTML form | `POST /items` — Bearer token |

The shop calls **`ItemService` and `UserService` directly** — it does not fetch `/items`. Same monolith pattern as django-101 and the laravel-101 shop you will build next.

**Key pieces:**

- **Twig templates** in `templates/shop/`
- **Symfony Forms** in `src/Form/`
- **Dual firewalls** in `security.yaml` — `shop` (session + form_login) before `api` (JWT)
- **Flash messages** via `ShopFlashSubscriber`
- **Static assets** — `public/serve-static.php` is included from both `index.php` and `router.php` so the PHP built-in server returns `text/css` for `/shop/style.css` (browsers reject CSS served as `text/html`)
- **Header “API” link** — jumps to `GET /items` and shows raw JSON in the browser (same data as the shop list, different format). No OpenAPI UI yet — see [Compare with fastAPI-101](#compare-with-fastapi-101).

**Full walkthrough:** **[docs/frontend.md](docs/frontend.md)**

```bash
make serve
# http://127.0.0.1:8002/shop/register
# http://127.0.0.1:8002/items          ← raw JSON (header “API” link)
curl -I http://127.0.0.1:8002/shop/style.css   # Content-Type: text/css
```

**Laravel parallel (for laravel-101):** Blade views + `web` middleware + session auth, sharing the same services as API routes.

---

## 20. Quick Reference

| Goal | Command |
|------|---------|
| Copy env | `cp .env.example .env` |
| Generate JWT keys | `bash bin/generate-jwt-keys.sh` |
| Install deps | `composer install` |
| Migrate | `php bin/console doctrine:migrations:migrate` |
| Run local (SQLite) | `make serve` → http://127.0.0.1:8002 |
| Open shop UI | http://127.0.0.1:8002/shop |
| Raw JSON items | http://127.0.0.1:8002/items |
| Frontend docs | [docs/frontend.md](docs/frontend.md) |
| Run tests | `php bin/phpunit` |
| Docker + Postgres | `docker compose up --build` |
| Stop Docker | `docker compose down` |
| List routes | `php bin/console debug:router` |
| Symfony shell | `php bin/console` |

### API endpoints

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

### Environment variables

| Variable | Default | Purpose |
|----------|---------|---------|
| `DATABASE_URL` | SQLite `var/data.db` | Database connection |
| `APP_SECRET` | (required) | Symfony secret |
| `JWT_SECRET_KEY` | `config/jwt/private.pem` | JWT signing |
| `JWT_PUBLIC_KEY` | `config/jwt/public.pem` | JWT verification |
| `LOCK_DSN` | `flock` | Required by RateLimiter |

---

## Compare with fastAPI-101

Run both side by side:

| | fastAPI-101 | symfony-101 |
|--|-------------|-------------|
| Port (local/Docker) | 8000 | 8002 |
| Root message | `Hello from FastAPI!` | `Hello from symfony-101` |
| API shape | Same endpoints | Same endpoints |
| Browser shop | No | `/shop` |
| OpenAPI docs | `/docs` | Not included (future step) |
| Admin UI | No | No |
| Language | Python | PHP |

### *-101 Family

#### API backends

| Repo | Port | Type | Stack |
|------|------|------|-------|
| [fastAPI-101](https://github.com/iammikek/fastAPI-101) | 8000 | API-only | FastAPI, SQLAlchemy |
| [django-101](https://github.com/iammikek/django-101) | 8001 | Monolith | Django + DRF + shop |
| [**symfony-101**](https://github.com/iammikek/symfony-101) | **8002** | Monolith | Symfony + shop |
| [laravel-101](https://github.com/iammikek/laravel-101) | 8003 | Monolith | Laravel + shop |
| [framework-x-101](https://github.com/iammikek/framework-x-101) | 8004 | Monolith | Framework X + shop |
| [orchestr-101](https://github.com/iammikek/orchestr-101) | 8005 | Monolith | Orchestr + shop |
| [nest-101](https://github.com/iammikek/nest-101) | 8006 | API-only | NestJS, TypeScript |
| [express-101](https://github.com/iammikek/express-101) | 8007 | API-only | Express, Vitest |
| [go-101](https://github.com/iammikek/go-101) | 8000* | API-only | Gin, GORM |
| [fortran-101](https://github.com/iammikek/fortran-101) | 8008 | API-only | Fortran, fpm |
| [java-101](https://github.com/iammikek/java-101) | 8009 | API-only | Spring Boot, JPA, Flyway |
| [dotNet-101](https://github.com/iammikek/dotNet-101) | 8010 | API-only | ASP.NET Core, xUnit |
| [flask-101](https://github.com/iammikek/flask-101) | 8011 | API-only | Flask, pytest |
| [rails-101](https://github.com/iammikek/rails-101) | 8012 | Monolith | Rails + shop |
| [geblang-101](https://github.com/iammikek/geblang-101) | 8013 | API-only | Geblang, SQLite |
| [gebweb-101](https://github.com/iammikek/gebweb-101) | 8014 | API-only | Geblang + Gebweb |
\* go-101 also uses port 8000 — run one backend at a time, or change port in config.

#### Other clients

| Repo | Platform | Stack |
|------|----------|-------|
| [flutter-101](https://github.com/iammikek/flutter-101) | Mobile / desktop | Flutter (iOS, macOS, Android) |
| [react-101](https://github.com/iammikek/react-101) | Web browser | React 19, Vite, Vitest |
| [vue-101](https://github.com/iammikek/vue-101) | Web browser | Vue 3, Vite, Pinia |
| [alpine-101](https://github.com/iammikek/alpine-101) | Web browser | Alpine.js, Vite, Vitest |

#### Suggested pairing

- **Compare PHP stacks:** symfony-101 (8002) vs [laravel-101](https://github.com/iammikek/laravel-101) (8003) or [framework-x-101](https://github.com/iammikek/framework-x-101) (8004)
- **Into JVM:** [java-101](https://github.com/iammikek/java-101) (8009) after [laravel-101](https://github.com/iammikek/laravel-101)
- **Pair with a client:** [react-101](https://github.com/iammikek/react-101), [vue-101](https://github.com/iammikek/vue-101), [alpine-101](https://github.com/iammikek/alpine-101), or [flutter-101](https://github.com/iammikek/flutter-101)

Catalogue: [automica.io/learning-101](https://automica.io/learning-101.html)

---

## Resources

- [Symfony Documentation](https://symfony.com/doc/current/index.html)
- [Doctrine ORM](https://www.doctrine-project.org/projects/doctrine-orm/en/latest/index.html)
- [LexikJWTAuthenticationBundle](https://github.com/lexik/LexikJWTAuthenticationBundle)
- [fastAPI-101](https://github.com/iammikek/fastAPI-101) — reference API shape
- [django-101](https://github.com/iammikek/django-101) — same tutorial style, Python monolith
