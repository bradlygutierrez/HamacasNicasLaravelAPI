<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## Production setup

For a fresh production database, connect to the server over SSH and run these
commands from the Laravel application directory (the one containing `artisan`):

Set `SANCTUM_EXPIRATION` in `.env` to the desired number of minutes first
(43200 minutes, or 30 days, is the default), then cache the configuration:

```bash
php artisan config:cache
```

Create the schema and first administrator:

```bash
php artisan migrate --force
php artisan app:create-first-admin
```

On an existing database, do not run pending variant migrations with this
command until the staging, backup, and restore checks in
[High-risk variant migrations](#high-risk-variant-migrations) are complete.

The admin command prompts for the name and email and asks for a password
interactively (hidden while typing, minimum 12 characters). It refuses to run
without an interactive terminal or if an administrator already exists. After
creating this account, add other users through the application.

Do not run `php artisan db:seed` in production. The seeder is restricted to
local/testing environments and creates demo accounts with known passwords.

Sanctum personal access tokens expire after 30 days by default. To configure a
different lifetime, set `SANCTUM_EXPIRATION` in `.env` to the desired number of
minutes before running `php artisan config:cache`.

This setting applies to personal access tokens, not browser session cookies.

Replace the example domains and database placeholders in `.env.example` with
the SiteGround values. Generate `APP_KEY` once for the deployed app and keep it
private. Keep `CORS_ALLOWED_ORIGINS` to the exact HTTPS origins for the panel
and public website; include the website origin when it is deployed. If the
frontend and API use different subdomains and the XSRF cookie must be shared,
set `SESSION_DOMAIN` to their common parent domain. Keep `SESSION_SECURE_COOKIE`
and `SESSION_HTTP_ONLY` enabled.

The local filesystem uses persistent `storage/` directories on SiteGround.
Keep `storage/` when deploying new releases, ensure the web process can write
to `storage/` and `bootstrap/cache/`, and run `php artisan storage:link` for
public product photos. Back up `storage/app/public` along with the database.

## High-risk variant migrations

Do not run these migrations in production until they have been rehearsed
against a recent database clone:

- `2026_09_26_000002_promote_hamaca_variants_to_products.php` creates a product
  for each variant, copies color/photo relations, archives parent products,
  rewrites recipe, inventory, proforma, order, and invoice references, and
  consolidates duplicate inventory rows by summing quantities. Its `down()`
  intentionally throws; the data transformation has no automatic rollback.
- `2026_09_26_000003_remove_hamaca_variant_structure.php` drops variant
  columns, pivot tables, the promotion map, and `hamaca_variantes`. Its
  `down()` also intentionally throws. On MySQL, DDL may commit independently,
  so a partial failure may leave a partly changed schema.

Before each production execution, stop writes (maintenance mode and workers),
take a full database dump and a separate archive of `storage/app/public`, and
verify that both can be restored together to an isolated staging copy. Confirm
that the clone passes the migration and that inventory totals, product-color
and photo links, recipes, proformas, orders, and invoice references remain
correct. Retain the pre-migration backup; if execution fails, restore that
backup rather than relying on `migrate:rollback`.

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

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
