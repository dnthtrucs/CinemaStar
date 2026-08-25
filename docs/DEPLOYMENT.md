# Hướng dẫn triển khai

## Cấu hình production

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://cinema.example.com
DEMO_PAYMENT_ENABLED=false
PAYMENT_MODE=sandbox
SESSION_SECURE_COOKIE=true
```

Thiết lập MySQL, SMTP, khóa MoMo/VNPAY production và không commit `.env`.

## Lệnh triển khai

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan storage:link
php artisan optimize
```

Cấu hình web server trỏ document root vào `Cinema/public`, chạy queue worker và cron:

```cron
* * * * * cd /var/www/cinema && php artisan schedule:run >> /dev/null 2>&1
```

## Checklist

- HTTPS hợp lệ; `APP_DEBUG=false`.
- Backup database định kỳ và thử phục hồi.
- Quyền ghi chỉ cấp cho `storage` và `bootstrap/cache`.
- Tài khoản admin demo đã đổi mật khẩu.
- Return URL/IPN URL trên gateway khớp `APP_URL`.
- Log không chứa secret hoặc dữ liệu thẻ.
