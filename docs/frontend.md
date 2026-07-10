# Server-rendered frontend (Catalog Shop)

This document explains how the **browser UI** at `/shop` was built — a classic Symfony full-stack frontend that sits alongside the JSON API, not a JavaScript client calling `/items`.

---

## Why two interfaces?

symfony-101 is primarily an **API learning project** (JWT, Doctrine, same shape as fastAPI-101). The shop was added to show what **full-stack Symfony** looks like compared to API-only:

| | JSON API | Catalog Shop (`/shop`) |
|--|----------|-------------------------|
| **Response** | `application/json` | `text/html` |
| **Auth** | JWT Bearer token | Session cookie |
| **Views** | Controllers returning `JsonResponse` | Controllers returning `render()` + Twig |
| **Validation** | Symfony Validator in controllers | Symfony Forms |
| **Client** | curl, mobile, future SPA | Browser (full page loads) |

Both paths use the **same services and entities** — the shop does **not** HTTP-call `/items`. That is intentional: in a Symfony monolith, HTML views talk to `ItemService` directly (like a Laravel controller using Eloquent, not `Http::get('/api/items')`).

When you build **laravel-101** next, use the same split: JSON API + `/shop` Blade UI sharing services.

---

## Architecture

```
Browser request
      │
      ▼
/shop/*  ──► src/Controller/Shop/*  ──► src/Form/* (POST validation)
      │              │
      │              ▼
      │        src/Service/*  ──► src/Entity/*  ──► SQLite / Postgres
      │
      └──► templates/shop/*.twig + public/shop/style.css


Separate path:

/items  ──► src/Controller/ItemController  ──► same ItemService  ──► same DB
```

**Laravel parallel:** `/shop/*` ≈ web routes + Blade + `FormRequest`; `/items` ≈ API routes + API Resources.

---

## URLs

| URL | Controller | Auth | Purpose |
|-----|------------|------|---------|
| `/shop` | `ShopHomeController` | Public | Landing page + catalog stats |
| `/shop/items` | `ShopItemController::list` | Public | Browse/filter items (paginated) |
| `/shop/items/{id}` | `ShopItemController::detail` | Public | Single item page |
| `/shop/items/new` | `ShopItemController::create` | Session login | Add item via HTML form |
| `/shop/register` | `ShopAuthController::register` | Public | Create account + auto-login |
| `/shop/login` | `ShopAuthController::login` | Public | Session login |
| `/shop/logout` | `ShopAuthController::logout` | POST | End session |

Route names: `shop_home`, `shop_item_list`, `shop_item_detail`, `shop_item_create`, `shop_register`, `shop_login`, `shop_logout`.

---

## File layout

```
symfony-101/
├── templates/
│   ├── base.html.twig            # Site layout (nav, messages, footer)
│   └── shop/
│       ├── home.html.twig
│       ├── item_list.html.twig
│       ├── item_detail.html.twig
│       ├── item_form.html.twig
│       ├── login.html.twig
│       └── register.html.twig
├── public/shop/style.css         # Shop styles
├── src/
│   ├── Controller/Shop/          # Template views (not JSON API)
│   ├── Form/                       # RegistrationFormType, ItemFormType, ItemFilterFormType
│   └── EventSubscriber/ShopFlashSubscriber.php
└── config/packages/security.yaml # Dual firewalls: shop (session) + api (JWT)
```

---

## Dual firewalls

Symfony runs **two security firewalls**:

```yaml
shop:
    pattern: ^/shop
    form_login: ...
    logout: ...
api:
    pattern: ^/
    stateless: true
    jwt: ~
```

The **shop firewall** must be listed **before** the API firewall. `/shop` routes use session cookies; everything else stays stateless JWT.

**Laravel parallel:** `web` middleware group vs `api` middleware group in `bootstrap/app.php`.

---

## Auth — session vs JWT

| Action | Shop (browser) | API |
|--------|----------------|-----|
| Register | `POST /shop/register` → session cookie | `POST /auth/register` → JSON only |
| Login | `POST /shop/login` → session cookie | `POST /auth/login` → JWT |
| Protected write | `#[IsGranted('ROLE_USER')]` + session | `#[IsGranted('ROLE_USER')]` + Bearer token |

**Register on the shop auto-logs you in** via `$security->login($user)`. API register does not — clients must call `/auth/login` for a token.

---

## Request walkthrough: add an item

1. User visits `/shop/items/new` — must be logged in (else redirect to `/shop/login`).
2. Browser GET → `ShopItemController::create` → renders `item_form.html.twig`.
3. User submits POST with name, price, category, CSRF token.
4. `ItemFormType` validates input.
5. Controller calls `ItemService::create(...)`.
6. Redirect to `/shop/items/{id}` with flash message.

Compare to API flow: same service call, but API uses JSON body + JWT instead of form + session.

---

## Try it

With the server running on port **8002**:

```bash
make serve
```

| Page | URL |
|------|-----|
| Shop home | http://127.0.0.1:8002/shop |
| Browse items | http://127.0.0.1:8002/shop/items |
| Register | http://127.0.0.1:8002/shop/register |
| Log in | http://127.0.0.1:8002/shop/login |
| JSON API (contrast) | http://127.0.0.1:8002/items |

**Typical browser flow:**

1. Open `/shop/register` → create account (logged in automatically).
2. Go to `/shop/items/new` → add an item.
3. Browse `/shop/items` and open an item detail page.
4. Compare the same data at `/items` (JSON).

---

## Tests

Shop pages are covered in **`tests/Feature/ShopTest.php`** (PHPUnit `WebTestCase`, not JWT):

- Home and list pages render
- Item detail shows API-created items
- Create requires login; logged-in create works
- Register creates user and session
- Duplicate email shows form error

```bash
php bin/phpunit tests/Feature/ShopTest.php
```

---

## Should the frontend call the API?

**Not in this project.** Same reasoning as django-101: direct service calls are simpler and idiomatic for server-rendered Symfony.

**When API-first frontend makes sense:** separate React/Vue app, mobile apps, or multiple clients — the pattern you will use for **laravel-101** when you add Inertia or a SPA later.

---

## Possible extensions

- Edit/delete items in the browser
- Category browse pages
- HTMX for partial updates
- EasyAdmin or Sonata Admin (Symfony’s “Django Admin” equivalent)
