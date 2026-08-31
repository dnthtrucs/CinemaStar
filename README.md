# CinemaStar – Hệ thống quản lý và đặt vé rạp chiếu phim

CinemaStar là hệ thống Laravel 11 phục vụ quản lý rạp, lịch chiếu, đặt vé trực tuyến, thanh toán và check-in QR. Ứng dụng dùng MySQL, Blade/Bootstrap và giao diện tiếng Việt.

## Chức năng chính

### Khách hàng

- Đăng ký, đăng nhập, cập nhật hồ sơ và xem lịch sử đơn vé.
- Xem phim, rạp, suất chiếu; chọn ghế trực quan và giữ ghế trong thời gian cấu hình.
- Nếu chưa đăng nhập, lựa chọn ghế và mã giảm giá được giữ lại trên cùng trình duyệt; đăng nhập hoặc đăng ký xong sẽ trở lại đúng trang chọn ghế.
- Dùng voucher và điểm thành viên; 1 điểm tương đương 1.000₫.
- Thanh toán QR SePay; hỗ trợ tích hợp MoMo và VNPAY khi có tài khoản merchant.
- Nhận email xác nhận với QR PNG hiển thị trong nội dung thư; PHP GD phải được bật để tạo QR PNG.
- Một đơn có một QR duy nhất cho toàn bộ ghế; QR này dùng để check-in cả đơn.

### Sơ đồ ghế

- Ghế thường: hàng A–D.
- Ghế VIP: hàng E, F, G; phụ thu 30.000₫ mỗi ghế.
- Ghế đôi màu hồng: hàng H, được chọn theo cặp H1–H2, H3–H4…
- Giá một ghế đôi: `2 × (giá ghế cơ bản + 30.000₫) + 30.000₫`.
- Server tự giữ cả hai vị trí của ghế đôi và chặn đặt trùng.

### Quản trị viên và nhân viên

- Quản lý phim, rạp, phòng, banner trang chủ, suất chiếu, người dùng và voucher.
- Tạo suất chiếu đơn lẻ hoặc hàng loạt; tự tính giờ kết thúc và chặn trùng phòng.
- Theo dõi dashboard, đơn vé, hoàn tiền và xuất báo cáo CSV/Excel/PDF.
- Admin/Staff quét QR bằng camera điện thoại hoặc tra cứu mã BK để check-in; một lần quét check-in toàn bộ ghế trong đơn.

## Công nghệ

- PHP 8.2+, Laravel 11, MySQL 8+/MariaDB 10.6+
- Blade, Bootstrap, Vite
- Endroid QR Code
- SePay, MoMo, VNPAY
- Docker/Render cho triển khai cloud

## Cài đặt trên XAMPP

```powershell
git clone https://github.com/dnthtrucs/CinemaStar.git
cd CinemaStar
composer install
copy .env.example .env
php artisan key:generate
```

Tạo database `cinema_db` trong phpMyAdmin, rồi chỉnh `.env`:

```env
APP_NAME=CinemaStar
APP_URL=http://127.0.0.1:8000
APP_TIMEZONE=Asia/Ho_Chi_Minh

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cinema_db
DB_USERNAME=root
DB_PASSWORD=
```

Khởi tạo ứng dụng:

```powershell
php artisan migrate --seed
npm install
npm run build
php artisan storage:link
php artisan serve
```

Mở http://127.0.0.1:8000.

### Bật QR PNG trong email

Mở `C:\xampp\php\php.ini`, bỏ dấu `;` trước dòng sau, sau đó khởi động lại Apache:

```ini
extension=gd
```

Kiểm tra:

```powershell
php -m | findstr /I gd
```

## Cấu hình email

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=emailcuaban@gmail.com
MAIL_PASSWORD="mat_khau_ung_dung_gmail"
MAIL_FROM_ADDRESS=emailcuaban@gmail.com
MAIL_FROM_NAME=CinemaStar
```

Sau khi đổi `.env`:

```powershell
php artisan optimize:clear
```

## Cấu hình SePay

Không đưa API key vào GitHub. Chỉ điền trong `.env` hoặc Environment của Render:

```env
SEPAY_API_KEY=
SEPAY_BANK_CODE=MBBank
SEPAY_ACCOUNT_NUMBER=
SEPAY_ACCOUNT_NAME="TEN_CHU_TAI_KHOAN"
SEPAY_QR_URL=https://qr.sepay.vn/img
```

Webhook SePay khi có URL HTTPS công khai:

```text
https://ten-mien-cua-ban/payments/sepay/webhook
```

## Tác vụ định kỳ

```powershell
php artisan schedule:work
```

Scheduler giải phóng ghế của đơn chưa thanh toán đã hết hạn.

## Kiểm thử

```powershell
php artisan test
```

- [Kiến trúc hệ thống](docs/ARCHITECTURE.md)
- [Kế hoạch kiểm thử](docs/TEST_PLAN.md)
- [Hướng dẫn triển khai Render](docs/DEPLOYMENT.md)
