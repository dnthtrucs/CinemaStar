# CinemaStar – Hệ thống quản lý và đặt vé rạp chiếu phim

CinemaStar là  tốt nghiệp xây dựng bằng Laravel 11,  đầy đủ quy trình vận hành rạp chiếu phim: quản lý phim/rạp/phòng/ghế/suất chiếu, giữ ghế chống đặt trùng, đặt vé, thanh toán, phát hành mã QR vé, check-in và báo cáo doanh thu.

## Chức năng

### Khách hàng

- Đăng ký, đăng nhập, cập nhật hồ sơ và bảo vệ đăng nhập bằng rate limit.
- Xem phim đang chiếu/sắp chiếu, thông tin rạp và lịch chiếu.
- Chọn ghế trực quan; giá được tính ở máy chủ theo loại ghế.
- Giữ ghế trong thời gian cấu hình; đơn hết hạn tự trả ghế.
- Thanh toán qua MoMo, VNPAY hoặc cổng  khi demo.
- Xem lịch sử đơn, mã vé và hủy đơn chưa thanh toán.

### Quản trị viên

- Dashboard doanh thu, đơn hàng, khách hàng và giao dịch gần đây.
- CRUD phim, rạp, phòng chiếu, sơ đồ ghế và suất chiếu.
- Phát hiện lịch chiếu trùng phòng.
- Quản lý đơn đặt vé, giao dịch, tài khoản và phân quyền.
- Tra cứu mã vé và check-in; ngăn tái sử dụng vé.

### An toàn dữ liệu

- CSRF, escaping Blade, validation phía server và rate limiting đăng nhập.
- Middleware phân quyền admin; kiểm tra quyền sở hữu đơn hàng.
- Transaction và row lock khi giữ ghế; unique constraint chống trùng ghế.
- Xác minh chữ ký callback và số tiền từ MoMo/VNPAY; xử lý callback idempotent.
- Không lưu khóa thanh toán trong source code.

## Yêu cầu

- PHP 8.2 trở lên và Composer 2
- MySQL 8/MariaDB 10.6 trở lên (SQLite dùng được cho kiểm thử)
- Node.js 20 trở lên

## Cài đặt

```bash
git clone https://github.com/dnthtrucs/WebNangCao.git
cd WebNangCao/Cinema
composer install
cp .env.example .env
php artisan key:generate
```

Tạo database MySQL rồi cấu hình `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cinema_graduate
DB_USERNAME=root
DB_PASSWORD=
```

Khởi tạo dữ liệu và chạy ứng dụng:

```bash
php artisan migrate:fresh --seed
npm install
npm run build
php artisan serve
```

Mở `http://127.0.0.1:8000`.

Tài khoản demo:

| Vai trò | Email | Mật khẩu |
|---|---|---|
| Admin | `admin@cinema.test` | `Admin@123` |
| Khách hàng | `customer@cinema.test` | `User@123` |

Đổi các mật khẩu này trước khi triển khai thật.

## Cấu hình thanh toán

Chế độ trình diễn trên XAMPP không cần khóa API và vẫn cho phép chọn giao diện MoMo/VNPAY:

```env
DEMO_PAYMENT_ENABLED=true
PAYMENT_MODE=simulate
```

Muốn kết nối môi trường thử nghiệm thật, đổi sang:

```env
PAYMENT_MODE=sandbox
APP_URL=https://ten-mien-https-cong-khai.example
```

VNPAY sandbox:

```env
VNPAY_URL=https://sandbox.vnpayment.vn/paymentv2/vpcpay.html
VNPAY_TMN_CODE=your_tmn_code
VNPAY_HASH_SECRET=your_hash_secret
```

MoMo sandbox:

```env
MOMO_URL=https://test-payment.momo.vn/v2/gateway/api/create
MOMO_PARTNER_CODE=your_partner_code
MOMO_ACCESS_KEY=your_access_key
MOMO_SECRET_KEY=your_secret_key
```

Khi dùng callback/IPN thật, `APP_URL` phải là URL HTTPS công khai. Sau khi sửa `.env`, chạy `php artisan optimize:clear`. Tắt `DEMO_PAYMENT_ENABLED` trên production.

## Tác vụ nền và kiểm thử

Giải phóng đơn hết hạn tự động:

```bash
php artisan schedule:work
```

Chạy kiểm thử:

```bash
php artisan test
```

Xem [tài liệu kiến trúc](docs/ARCHITECTURE.md), [kịch bản kiểm thử](docs/TEST_PLAN.md) và [hướng dẫn triển khai](docs/DEPLOYMENT.md).
