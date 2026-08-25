# Hướng dẫn triển khai CinemaStar

## 1. Yêu cầu máy chủ

- PHP 8.2 trở lên, Composer 2
- MySQL 8 hoặc MariaDB 10.6 trở lên
- Node.js 20 trở lên
- Web server Apache/Nginx trỏ document root vào thư mục public
- HTTPS công khai nếu sử dụng MoMo/VNPAY sandbox hoặc production

## 2. Cấu hình production

Sao chép .env.example thành .env và cấu hình riêng trên máy chủ:

~~~env
APP_NAME=CinemaStar
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.example
APP_TIMEZONE=Asia/Ho_Chi_Minh

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=cinema_db
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password

DEMO_PAYMENT_ENABLED=false
PAYMENT_MODE=sandbox
SESSION_SECURE_COOKIE=true
~~~

Không commit file .env, khóa SMTP, khóa MoMo/VNPAY hoặc mật khẩu database lên GitHub.

## 3. Lệnh triển khai

~~~bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan storage:link
php artisan optimize
~~~

Đặt quyền ghi cho hai thư mục:

~~~bash
chmod -R ug+rwx storage bootstrap/cache
~~~

## 4. Scheduler

Scheduler xử lý các công việc theo thời gian, bao gồm giải phóng ghế giữ quá hạn. Thiết lập cron trên Linux:

~~~cron
* * * * * cd /var/www/cinemastar && php artisan schedule:run >> /dev/null 2>&1
~~~

Khi demo trên Windows/XAMPP, chạy cửa sổ PowerShell riêng:

~~~powershell
php artisan schedule:work
~~~

## 5. Thanh toán và email

- APP_URL phải là HTTPS công khai để nhận return URL/IPN từ MoMo và VNPAY.
- Cấu hình đúng callback URL, mã đối tác và secret trong .env.
- Dùng SMTP/App Password phù hợp cho email xác nhận vé.
- Sau khi đổi .env, chạy: php artisan optimize:clear.

## 6. Checklist trước khi bàn giao

- APP_DEBUG=false và HTTPS hợp lệ.
- Đã đổi mật khẩu tài khoản Admin demo.
- Đã kiểm tra đặt vé, voucher, đổi điểm, thanh toán, email QR và check-in.
- Đã kiểm tra tạo lịch chiếu hàng loạt không sinh lịch trùng phòng.
- Đã chạy backup database và thử khôi phục trên môi trường thử nghiệm.
- Log, file .env và khóa bí mật không xuất hiện trong GitHub.
