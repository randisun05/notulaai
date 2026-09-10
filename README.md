# AI Notula App

AI Meeting Workspace: transkrip & rangkuman notula otomatis (Whisper/Gemini), action items, task management dengan approval workflow, forum diskusi per rapat, dashboard & analytics, dan fitur enterprise (multi-tenant per unit, API token, audit log, webhook, SSO Google/Microsoft). Dibangun di atas Laravel 10 + Inertia.js + Vue 3.

## Menjalankan dengan Docker Compose

Cara ini tidak butuh PHP/MySQL/Node terpasang di komputer — semuanya jalan di container.

```bash
cp .env.example .env
php artisan key:generate   # atau isi APP_KEY manual di .env jika belum punya PHP lokal

docker compose up -d --build
docker compose exec app php artisan migrate --seed
```

Aplikasi bisa diakses di `http://localhost:8080`. Service yang berjalan: `app` (PHP-FPM), `nginx` (web server, port 8080), `mysql`, `redis`, `queue` (worker `queue:work`), `scheduler` (`schedule:work` — menjalankan reminder/eskalasi task terjadwal), `whisper` (STT lokal untuk transkrip audio).

Catatan:
- `docker-compose.yml` otomatis mengarahkan `DB_HOST`/`REDIS_HOST`/`STT_SERVICE_URL` ke service Docker dan mengaktifkan `CACHE_DRIVER`/`SESSION_DRIVER`/`QUEUE_CONNECTION=redis`, terlepas dari apa pun yang tertulis di `.env` untuk key tersebut — variabel lain di `.env` (API key AI, kredensial SMTP/OAuth, dst.) tetap dipakai apa adanya.
- File upload (lampiran forum, logo perusahaan) disimpan di named volume `storage_data`, dibagi antara `app` dan `nginx` supaya keduanya melihat isi yang sama.
- Service `whisper` (`docker/whisper/`) mengunduh bobot model saat boot pertama ke named volume `whisper_cache` (tidak hilang saat rebuild). Ukuran model diatur lewat env `WHISPER_MODEL` (default `base`). Healthcheck: `GET /health`.
- Setelah ganti kode, `docker compose up -d --build` untuk build ulang image (assets Vite dan `vendor/` di-bake saat build image, bukan lewat bind mount).

### Whisper STT tanpa Docker

Kalau menjalankan app secara lokal (bukan via Compose), jalankan sidecar-nya sendiri:

```bash
pip install -r docker/whisper/requirements.txt
python docker/whisper/stt_service.py   # listen di :5055, set STT_SERVICE_URL sesuai
```

<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
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

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com/)**
- **[Tighten Co.](https://tighten.co)**
- **[WebReinvent](https://webreinvent.com/)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel/)**
- **[Cyber-Duck](https://cyber-duck.co.uk)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Jump24](https://jump24.co.uk)**
- **[Redberry](https://redberry.international/laravel/)**
- **[Active Logic](https://activelogic.com)**
- **[byte5](https://byte5.de)**
- **[OP.GG](https://op.gg)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
