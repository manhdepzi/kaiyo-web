# Kaiyo Web

Nền tảng thương mại điện tử/B2B Kaiyo, xây dựng bằng Laravel modular monolith. V1 vận hành Commerce, CRM, CMS, SEO, Merchant và Analytics. AI Platform là V2, không được trở thành dependency của Commerce V1.

Tài liệu này hướng dẫn một máy mới chạy dự án từ đầu: mã nguồn, PHP/Node, MySQL/phpMyAdmin, Redis Docker, migration, worker, scheduler, kiểm thử và các kiểm tra trước khi push.

> Không commit `.env`, mật khẩu database, API key, file backup hoặc dữ liệu production. Mọi ví dụ mật khẩu trong README chỉ là placeholder, phải thay bằng mật khẩu riêng của môi trường.

## 1. Kiến trúc và yêu cầu

| Thành phần | Phiên bản / mục đích |
| --- | --- |
| PHP | PHP 8.5 là baseline CI/deployment; PHP 8.3+ dùng được cho local development |
| Laravel | Laravel 13 |
| Composer | Composer 2.x |
| Node.js | Node.js 24 LTS và npm |
| MySQL | MySQL 8.4 là system of record |
| Redis | Redis 8.2, dùng cho cache, session và queue; không phải nguồn dữ liệu thương mại gốc |
| Frontend | Blade, Livewire 4, Vite, Tailwind CSS 4 |
| Test/quality | PHPUnit 12, Pint, PHPStan/Larastan level 8 |
| Docker | Tùy chọn, dùng để chạy MySQL/Redis/phpMyAdmin local |

PHP cần có tối thiểu các extension: `mbstring`, `intl`, `pdo_mysql`, `openssl`, `curl`, `fileinfo`, `zip` và `xml`.

Trên Windows có thể dùng Laragon cho PHP/MySQL/Nginx hoặc Apache. Docker Desktop chỉ cần thiết nếu chạy Redis/MySQL trong Docker.

## 2. Lấy mã nguồn trên máy mới

```powershell
git clone https://github.com/manhdepzi/kaiyo-web.git
cd kaiyo-web

composer install
npm ci

Copy-Item .env.example .env
php artisan key:generate
```

Nếu repository private, đăng nhập GitHub hoặc cấu hình SSH/PAT trước khi `git clone`.

Kiểm tra nhanh phiên bản cài đặt:

```powershell
php -v
composer --version
node --version
npm --version
docker --version       # chỉ cần khi dùng Docker
```

## 3. Chọn cách chạy MySQL và Redis

Chọn **một** MySQL local: Laragon/phpMyAdmin hoặc MySQL Docker. Không chạy hai MySQL cùng port `3306`.

### Phương án A: Laragon/phpMyAdmin + Redis Docker

1. Mở Laragon và Start MySQL.
2. Trong phpMyAdmin, tạo database UTF-8:

   ```sql
   CREATE DATABASE kaiyo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

3. Tạo một MySQL user riêng có quyền trên database `kaiyo`. Không dùng user production hoặc tài khoản root trong `.env` nếu không cần thiết.
4. Chạy Redis Docker:

   ```powershell
   docker run --name kaiyo-redis -d -p 6379:6379 redis:8.2-alpine redis-server --appendonly yes
   ```

5. Kiểm tra Redis:

   ```powershell
   docker exec kaiyo-redis redis-cli ping
   # Kết quả mong đợi: PONG
   ```

Nếu container `kaiyo-redis` đã tồn tại nhưng đang dừng:

```powershell
docker start kaiyo-redis
```

### Phương án B: MySQL + Redis đều chạy Docker

Tạo network một lần:

```powershell
docker network create kaiyo-network
```

Chạy MySQL 8.4. Thay `CHANGE_ME_*` bằng mật khẩu local của bạn:

```powershell
docker run --name kaiyo-mysql --network kaiyo-network -d `
  -p 3306:3306 `
  -e MYSQL_DATABASE=kaiyo `
  -e MYSQL_USER=kaiyo `
  -e MYSQL_PASSWORD=CHANGE_ME_APP_PASSWORD `
  -e MYSQL_ROOT_PASSWORD=CHANGE_ME_ROOT_PASSWORD `
  -v kaiyo-mysql-data:/var/lib/mysql `
  mysql:8.4
```

Chạy Redis:

```powershell
docker run --name kaiyo-redis --network kaiyo-network -d `
  -p 6379:6379 `
  -v kaiyo-redis-data:/data `
  redis:8.2-alpine redis-server --appendonly yes
```

Tùy chọn: chạy phpMyAdmin để xem database ở `http://127.0.0.1:8081`:

```powershell
docker run --name kaiyo-phpmyadmin --network kaiyo-network -d `
  -p 8081:80 `
  -e PMA_HOST=kaiyo-mysql `
  phpmyadmin:latest
```

Kiểm tra trạng thái:

```powershell
docker ps
docker exec kaiyo-mysql mysqladmin ping -h localhost -u root -p
docker exec kaiyo-redis redis-cli ping
```

Các lệnh `docker stop`, `docker start` an toàn cho container. Không chạy `docker rm -v` hoặc xóa volume `kaiyo-mysql-data` nếu muốn giữ dữ liệu.

## 4. Cấu hình `.env`

Mở `.env` và cập nhật phần quan trọng sau. Đây là cấu hình local mẫu khi app chạy trên máy host và MySQL/Redis mở port ra `127.0.0.1`:

```dotenv
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=kaiyo
DB_USERNAME=kaiyo
DB_PASSWORD=CHANGE_ME_APP_PASSWORD

CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_CLIENT=predis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

# Local HTTP không dùng HTTPS cookie.
SESSION_SECURE_COOKIE=false
HEALTH_DB_CHECK=true
HEALTH_CACHE_CHECK=true
```

Nếu dùng MySQL Laragon, thay `DB_USERNAME` và `DB_PASSWORD` bằng tài khoản MySQL local đã tạo. Nếu app cũng chạy trong Docker network, `DB_HOST`/`REDIS_HOST` phải là tên container (`kaiyo-mysql`, `kaiyo-redis`) thay vì `127.0.0.1`.

Không bật `PAYMENT_ONLINE_GATEWAY_ENABLED=true` hoặc thêm provider credentials cho local nếu chưa có provider contract được duyệt. Giá trị mặc định `MAIL_MAILER=log` nghĩa là email local được ghi log, không gửi ra ngoài.

Sau mọi thay đổi `.env`:

```powershell
php artisan config:clear
php artisan cache:clear
```

## 5. Khởi tạo database và dữ liệu local

Kiểm tra kết nối trước:

```powershell
php artisan migrate:status
php artisan performance:baseline --json
```

Chạy migration lần đầu:

```powershell
php artisan migrate
php artisan db:seed
```

`db:seed` chỉ nạp Demo Catalog trong môi trường `local`/`testing`.

Tạo hoặc cập nhật tài khoản admin local. Lệnh sẽ hỏi mật khẩu trong terminal và chỉ chạy khi `APP_ENV=local` hoặc `testing`:

```powershell
php artisan admin:provision-local admin@example.test
```

Sau khi đăng nhập bằng admin local, cần enrol 2FA tại **Account > Security** trước khi truy cập route staff/admin.

> `php artisan migrate:fresh`, `migrate:refresh`, rollback migration và reset database có thể xóa dữ liệu. Chỉ dùng trên database disposable/local đã được phép xóa; không dùng trên `kaiyo` có dữ liệu cần giữ hay production.

## 6. Chạy ứng dụng

Mở ba terminal riêng tại thư mục project.

### Terminal 1 - Laravel web server

```powershell
php artisan serve
```

Mở `http://127.0.0.1:8000`.

### Terminal 2 - Vite development server

```powershell
npm run dev
```

Dùng lệnh này khi phát triển giao diện. Nếu chỉ muốn build asset tĩnh:

```powershell
npm run build
```

### Terminal 3 - Queue worker

```powershell
php artisan queue:work redis --tries=3 --timeout=120
```

### Terminal 4 - Scheduler

```powershell
php artisan schedule:work
```

Hoặc dùng một lệnh phát triển tổng hợp (web server, worker, log và Vite):

```powershell
composer dev
```

## 7. Kiểm tra ứng dụng sau khi chạy

```powershell
# Liveness và sanitized readiness
Invoke-WebRequest http://127.0.0.1:8000/up | Select-Object StatusCode, Content
Invoke-WebRequest http://127.0.0.1:8000/ready | Select-Object StatusCode, Content

# Dependency/readiness, outbox và delivery - chỉ đọc, không thay đổi dữ liệu commerce
php artisan operations:health --json
php artisan performance:baseline --json
php artisan outbox:status --json
php artisan growth:delivery-status --json
php artisan dr:status --json
```

Khi `HEALTH_DB_CHECK=true` và `HEALTH_CACHE_CHECK=true`, `/ready` chỉ trả `200` nếu MySQL và Redis truy cập được. `dr:status` mặc định chỉ quan sát; không được coi là bằng chứng backup/restore production.

## 8. Kiểm thử và quality gate

Các lệnh sau không gọi payment/carrier/provider thật:

```powershell
composer validate --strict
composer lint
composer analyse
composer test:critical
composer test
composer quality

npm run build
npm audit --audit-level=high
npx --yes @redocly/cli@2.47.0 lint docs/api/openapi.yaml --extends=recommended
php artisan security:source-scan --json
php artisan security:configuration-audit --json
```

Test mặc định dùng SQLite in-memory. Test MySQL/Redis dùng database cô lập tên `kaiyo_test`, tuyệt đối không trỏ vào database `kaiyo` đang dùng:

```powershell
# Tạo database test riêng trước khi chạy.
php vendor/bin/phpunit --configuration=phpunit.mysql.xml
php artisan outbox:concurrency-probe
```

Không dùng user/password production cho `kaiyo_test`. CI GitHub tự tạo MySQL 8.4 và Redis 8.2 riêng cho test.

## 9. GitHub Actions và push

Workflow nằm tại [`.github/workflows/quality.yml`](./.github/workflows/quality.yml), chạy khi push vào `main` hoặc pull request.

Quy trình đúng trước khi push:

```powershell
composer quality
npm run build
git status
git add <files-da-kiem-tra>
git commit -m "mo ta thay doi"
git push origin main
```

Push thành công chỉ có nghĩa là GitHub đã nhận commit. Dấu X đỏ trên GitHub là CI sau push bị lỗi, không nhất thiết là lỗi push. Vào **Actions** để đọc bước fail đầu tiên.

Các Blade layout dùng `@vite(...)`. Vì vậy CI phải chạy `npm run build` trước các test render view; nếu không runner sạch sẽ thiếu `public/build/manifest.json` và các test có thể trả HTTP 500.

## 10. Troubleshooting

### Lỗi `SQLSTATE`, phpMyAdmin không thấy database hoặc không kết nối MySQL

1. Kiểm tra MySQL đang chạy và port trong `.env` khớp (`3306` mặc định).
2. Xác nhận `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`.
3. Chạy `php artisan config:clear` rồi `php artisan migrate:status`.
4. Nếu MySQL Docker vừa khởi động, đợi health check hoàn tất trước khi migration.

### Lỗi Redis `Connection refused`

```powershell
docker ps --filter "name=kaiyo-redis"
docker start kaiyo-redis
docker exec kaiyo-redis redis-cli ping
```

Xác nhận `REDIS_HOST=127.0.0.1`, `REDIS_PORT=6379` khi Laravel chạy trên host.

### Lỗi 500 khi chạy `php artisan serve`

```powershell
Get-Content storage/logs/laravel.log -Tail 100
php artisan optimize:clear
php artisan config:clear
php artisan migrate:status
```

Không gửi `.env`, password hoặc full log chứa secret lên GitHub/chat. Chỉ gửi phần stack trace đã che thông tin nhạy cảm.

### Giao diện không có CSS/JS hoặc Vite manifest missing

```powershell
npm ci
npm run dev      # khi phát triển
# hoặc
npm run build    # tạo public/build/manifest.json
```

### Queue không chạy hoặc email không thấy gửi

```powershell
php artisan queue:work redis --tries=3 --timeout=120
Get-Content storage/logs/laravel.log -Tail 100
```

Với cấu hình local mặc định, mail được ghi vào log (`MAIL_MAILER=log`), không gửi email thật.

### Docker daemon không chạy

Mở Docker Desktop, đợi engine sẵn sàng rồi kiểm tra:

```powershell
docker info
docker ps
```

Nếu Windows báo không có quyền Docker daemon, đăng nhập lại Docker Desktop hoặc chạy terminal bằng tài khoản có quyền Docker; không tự xóa container/volume để “sửa nhanh”.

## 11. Production/staging boundary

README này chỉ hướng dẫn local development. Production/staging cần cấu hình provider, secret manager, HTTPS, WAF/CDN, backup, monitoring, alerting, rollback và DR theo tài liệu operations.

Trước release protected, chạy các gate read-only sau trong môi trường đã được phê duyệt:

```powershell
php artisan security:configuration-audit --json --production
php artisan release:preflight --json
```

`release:preflight` không deploy, migrate, backup hoặc restore. Nó sẽ fail-closed nếu security configuration, readiness, delivery health hoặc DR evidence chưa đạt. Không được đánh dấu production-ready nếu chưa có test staging/browser, evidence restore thực tế và approval theo [Execution Master Plan](./planManh.md).

## 12. Tài liệu tham chiếu

- [Execution Master Plan](./planManh.md)
- [Engineering governance](./docs/governance/engineering-governance.md)
- [CI/CD design](./docs/operations/ci-cd.md)
- [Performance budgets](./docs/performance/performance-budgets.md)
- [Observability/SLO plan](./docs/operations/observability-slo.md)
- [Disaster recovery plan](./docs/operations/disaster-recovery.md)
- [Testing strategy](./docs/testing/testing-strategy.md)
- [Security threat model](./docs/security/threat-model.md)
