# TeamCollab — Enterprise Multi-Tenant Realtime Collaboration SaaS

> A production-grade Slack / Teams alternative built with **Laravel 12 · Vue 3 · Inertia · Reverb · Stancl Tenancy**

---

## Features

- **Realtime messaging** via Laravel Reverb (WebSockets)
- **Database-per-tenant** multi-tenancy with Stancl Tenancy
- **Public & Private Channels** + Direct Messages + Threads
- **Emoji reactions**, file sharing, @mentions, pin messages, read receipts
- **Typing indicators** + online presence (Online / Away / DND / Offline)
- **Role-based access** (Owner / Admin / Member / Guest) via Spatie Permission
- **Analytics dashboard** with Chart.js — message trends, channel activity
- **Global search** — messages, channels, members, files
- **Queue jobs** — notifications, file processing, activity logging
- **Subscription architecture** — Free / Pro / Enterprise plans
- **Premium dark-mode UI** — GSAP animations, Tailwind CSS
- **Modern chat extras** — message forwarding, link previews, voice messages, custom emoji & stickers, GIF picker (Giphy), scheduled messages, built-in slash commands (`/invite` `/mute` `/remind` `/giphy`), Web Push notifications
- **Calls** — 1-on-1 and group (mesh P2P, up to 6) audio/video calls with screen sharing and client-side recording (with in-call consent notices)

---

## Stack

| Layer | Tech |
|-------|------|
| Backend | Laravel 12, PHP 8.3, MySQL, Redis |
| Realtime | Laravel Reverb (WebSockets) |
| Multi-tenancy | Stancl Tenancy v3 (DB-per-tenant) |
| Permissions | Spatie Laravel Permission |
| Frontend | Vue 3, Inertia.js, Pinia, Tailwind CSS |
| Animations | GSAP |
| Charts | Chart.js |
| Auth | Laravel Sanctum |

---

## Quick Start

```bash
composer install
npm install --legacy-peer-deps
cp .env.example .env && php artisan key:generate
# Edit .env — set DB_DATABASE, Redis, Reverb keys
php artisan migrate && php artisan db:seed
php artisan tenants:migrate
npm run build
```

Then run 4 terminals:

```bash
php artisan serve              # :8000
php artisan reverb:start       # :8080
php artisan queue:work         # background jobs (queue driver from .env)
npm run dev                    # hot reload (dev)
```

**Windows shortcut:** double-click `start-dev.bat` — it checks MySQL (XAMPP),
`vendor/`, `node_modules/`, reads the ports from `.env`, opens the four
servers in their own windows and launches the browser. `stop-dev.bat` shuts
them all down again.

See **[INSTALLATION.md](INSTALLATION.md)** for the complete Windows 11 + XAMPP guide.

### Optional integrations

| Feature | `.env` | Notes |
|---|---|---|
| GIF picker | `GIPHY_API_KEY` | Hidden until a key is set |
| Web Push | `VAPID_PUBLIC_KEY` / `VAPID_PRIVATE_KEY` | `php artisan webpush:vapid`; needs HTTPS (or `localhost`) |
| Calls behind NAT | `TURN_URLS` + `TURN_SECRET` | See below |

**TURN relay for calls.** Calls use STUN by default, which fails for peers behind
symmetric NAT (common on mobile and corporate networks) — and in a group call that
shows up as "some people can't see each other". Point the app at a
[coturn](https://github.com/coturn/coturn) server and it will mint short-lived,
per-user credentials for every call (`GET /calls/ice-servers`):

```ini
# .env
TURN_URLS="turn:turn.example.com:3478?transport=udp,turns:turn.example.com:5349"
TURN_SECRET=change-me            # same value as coturn's static-auth-secret
TURN_CREDENTIAL_TTL=3600
```

```ini
# /etc/turnserver.conf (coturn)
listening-port=3478
tls-listening-port=5349
realm=turn.example.com
use-auth-secret
static-auth-secret=change-me
cert=/etc/letsencrypt/live/turn.example.com/fullchain.pem
pkey=/etc/letsencrypt/live/turn.example.com/privkey.pem
```

Providers that only hand out a fixed username/password work too: set
`TURN_USERNAME` / `TURN_CREDENTIAL` instead of `TURN_SECRET`.

---

## Demo Credentials

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@teamcollab.app | password |
| Member | john@teamcollab.app | password |

Workspace: `acme` → http://acme.localhost:8000

---

## Project Structure

```
app/
  Models/Tenant/        # Channel, Message, Conversation, File …
  Services/             # WorkspaceService, ChannelService, MessageService …
  Events/               # MessageSent, UserTyping, UserPresenceUpdated …
  Jobs/                 # LogActivity, ProcessFile, CreateDefaultChannels …
database/
  migrations/           # Central tables
  migrations/tenant/    # Tenant tables (channels, messages …)
resources/js/
  Stores/               # Pinia (auth, channel, messages, presence …)
  Layouts/              # AppLayout, AuthLayout, GuestLayout
  Pages/                # Dashboard, Channel/Show, Auth/*, Workspace/*
  Components/Chat/      # ChatArea, MessageItem, MessageInput, Reactions …
  Components/Sidebar/   # AppSidebar, ChannelList, WorkspaceSwitcher …
routes/
  web.php               # Central (auth + workspace)
  tenant.php            # Tenant (channels, messages, DM, files …)
  channels.php          # Broadcast channel auth
```

---

## License

MIT
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
