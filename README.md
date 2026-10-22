# CinemaStar – Hệ thống quản lý và đặt vé rạp chiếu phim

CinemaStar là hệ thống Laravel 11 phục vụ quản lý rạp, lịch chiếu, đặt vé trực tuyến, thanh toán và check-in QR. Ứng dụng dùng MySQL, Blade/Bootstrap và giao diện tiếng Việt.

## Chức năng chính

### Khách hàng

- Đăng ký, đăng nhập, cập nhật hồ sơ và xem lịch sử đơn vé.
- Trang Hỗ trợ khách hàng với tìm kiếm FAQ, hướng dẫn thanh toán/QR và liên hệ CSKH.
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
- Theo dõi dashboard, đơn vé, hoàn tiền và xuất báo cáo CSV/Excel/Bản in (HTML).
- Admin/Staff quét QR bằng camera điện thoại hoặc tra cứu mã BK để check-in; một lần quét check-in toàn bộ ghế trong đơn.

## Công nghệ

- PHP 8.2+, Laravel 11, MySQL 8+/MariaDB 10.6+
- Blade, Bootstrap, Vite
- Endroid QR Code
- SePay, MoMo, VNPAY
- Docker/Render cho triển khai cloud

## Cài đặt bằng Docker (khuyên dùng khi phát triển)

Chỉ cần Docker Desktop và Node.js 20+; PHP 8.3 và MySQL 8 chạy trong container (`compose.yaml`).

```powershell
git clone https://github.com/dnthtrucs/CinemaStar.git
cd CinemaStar
copy .env.example .env
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan storage:link
npm install
npm run build
```

Mở http://127.0.0.1:8000. MySQL trong container được mở ra máy ở cổng `3307` và đã có sẵn hai database `cinema_db` (dev) và `cinema_test` (test). Khi sửa giao diện, chạy `npm run dev` để Vite tự nạp lại CSS/JS.

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

### Tài khoản demo

Sau khi chạy `php artisan migrate --seed`:

| Vai trò | Email | Mật khẩu |
|---|---|---|
| Quản trị viên | admin@cinema.test | Admin@123 |
| Nhân viên | staff@cinema.test | Staff@123 |
| Khách hàng | customer@cinema.test | User@123 |

Khi cập nhật từ phiên bản cũ, chạy `php artisan migrate` để thêm bảng thông báo hoàn tiền.

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

Phải duy trì `php artisan schedule:work` trong lúc demo để scheduler tự giải phóng ghế của đơn chưa thanh toán đã hết hạn và hoàn lại điểm đã giữ.

## Hoàn tiền và voucher

- Khách mở **Vé của tôi → Chi tiết đơn → Yêu cầu hoàn tiền**. Đơn phải đã xác nhận và thanh toán, chưa check-in, suất chiếu chưa bắt đầu và chưa có yêu cầu hoàn tiền.
- Admin mở **Hoàn tiền**, chuyển **Chờ xử lý → Đã duyệt/Từ chối**, sau đó **Đã duyệt → Đã hoàn tiền/Từ chối**. Chỉ chọn **Đã hoàn tiền** sau khi đã chuyển tiền lại cho khách qua ngân hàng/cổng thanh toán; ứng dụng không tự chuyển tiền.
- Khi xác nhận đã hoàn: hủy đơn, giải phóng ghế, chặn check-in, thu hồi điểm thưởng (không để âm), hoàn điểm đã dùng và loại khoản đó khỏi doanh thu. Khách nhận thông báo trong database và email sau khi lưu thành công; không cần queue worker cho thông báo hoàn tiền.
- Giao dịch đến sau khi đơn hết hạn/hủy được ghi nhận trên Dashboard ở **Cần xử lý hoàn tiền**. Admin đối chiếu mã ngân hàng và liên hệ khách để xử lý thủ công; hệ thống không giữ lại ghế cho đơn này.
- Voucher chỉ tính lượt và lưu lịch sử sử dụng sau thanh toán thành công. Mỗi khách dùng một mã một lần; đơn pending còn hạn tạm giữ lượt, đơn hủy/hết hạn trả lượt giữ.
- **Bản in (HTML)** mở hộp thoại in của trình duyệt, có thể chọn Lưu dưới dạng PDF.

## Kiểm thử

Tạo database MySQL riêng trước khi chạy test:

```sql
CREATE DATABASE IF NOT EXISTS cinema_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Tham khảo `.env.testing.example` để cấu hình host/user MySQL nếu khác môi trường dev. Giữ `DB_DATABASE=cinema_test`. PHPUnit cố định database này và `tests/TestCase.php` kiểm tra cấu hình trước khi chạy migration; tuyệt đối không dùng `cinema_db`. Test tự tạo lại schema trên `cinema_test`, không cần seed hoặc chạy `npm run build`.

```powershell
php artisan config:clear
php artisan test
```

Nếu cài bằng Docker:

```powershell
docker compose exec app php artisan test
```

GitHub Actions (`.github/workflows/tests.yml`) tự chạy kiểm tra style, build giao diện và toàn bộ test trên MySQL cho mỗi pull request và mỗi lần push lên `main`.

## Quy trình phát triển

| Việc | Lệnh |
|---|---|
| Chạy web + queue + log + Vite cùng lúc | `composer dev` |
| Build CSS/JS cho production | `npm run build` |
| Định dạng code PHP (PSR-12, Laravel Pint) | `vendor/bin/pint` |
| Kiểm tra style như CI | `vendor/bin/pint --test` |
| Chạy test | `php artisan test` |

Quy ước:

- Làm việc trên nhánh riêng (`fix/...`, `feature/...`, `chore/...`), mở pull request vào `main`; CI phải xanh trước khi merge.
- Thay đổi schema luôn bằng migration mới, không sửa migration đã chạy.
- Thêm hoặc sửa nghiệp vụ thì kèm test trong `tests/Feature`; logic thuần (không cần DB) đặt trong `tests/Unit`.
- CSS chung đặt trong `resources/css/app.css`, JavaScript trong `resources/js`; không viết `<style>` dài trong layout.

## Cấu trúc thư mục

```text
app/
  Http/Controllers/        Controller phía khách; Admin/ cho trang quản trị và check-in
  Http/Middleware/         Phân quyền admin/staff, khóa tài khoản
  Http/Requests/           Validate form (phim, rạp, phòng, suất chiếu, banner, hồ sơ)
  Models/                  Eloquent model và quy tắc trạng thái (Booking, Payment, Voucher...)
  Notifications/           Email vé điện tử, thông báo hoàn tiền
  Services/                BookingService (giữ ghế, giá, hết hạn), PaymentGatewayService (SePay/MoMo/VNPAY)
config/cinema.php          Cấu hình thời gian giữ ghế và cổng thanh toán
database/migrations/       Lịch sử schema; database/seeders/ dữ liệu demo
resources/views/           Blade: layouts/ (app, auth), admin/, auth/, bookings/, payments/...
resources/css, resources/js  Giao diện được Vite build vào public/build
routes/web.php             Route khách, admin (/admin) và nhân viên (/staff)
routes/console.php         Lệnh bookings:expire và lịch chạy scheduler
tests/Feature, tests/Unit  Test nghiệp vụ và test đơn vị
docker/, compose.yaml      Môi trường phát triển; Dockerfile ở gốc dùng để deploy
docs/                      Kiến trúc, kế hoạch kiểm thử, hướng dẫn triển khai
```

## Tài liệu

- [Kiến trúc hệ thống](docs/ARCHITECTURE.md)
- [Kế hoạch kiểm thử](docs/TEST_PLAN.md)
- [Hướng dẫn triển khai Render](docs/DEPLOYMENT.md)
