# CinemaStar – Hệ thống quản lý và đặt vé rạp chiếu phim

CinemaStar là đồ án tốt nghiệp xây dựng bằng **Laravel 11, MySQL và Blade/Bootstrap**. Hệ thống mô phỏng quy trình vận hành rạp chiếu phim: quản lý nội dung, lập lịch chiếu, đặt ghế, thanh toán, phát hành vé QR, check-in, ưu đãi thành viên, hoàn tiền và báo cáo.

## Chức năng chính

### Khách hàng

- Đăng ký, đăng nhập, cập nhật hồ sơ.
- Xem phim, rạp, phòng chiếu và suất chiếu còn hiệu lực.
- Chọn ghế trực quan, giữ ghế trong thời gian cấu hình và chống đặt trùng.
- Áp dụng voucher; dùng điểm thành viên để giảm giá (**1 điểm = 1.000đ**).
- Thanh toán mô phỏng hoặc qua MoMo/VNPAY khi đã cấu hình sandbox.
- Nhận email xác nhận thanh toán kèm thông tin vé, mã đơn và QR.
- Xem lịch sử đơn/vé; gửi yêu cầu hủy hoặc hoàn tiền theo điều kiện của hệ thống.
- Theo dõi trạng thái vé: **Sẵn sàng vào rạp**, **Đã check-in** hoặc **Đã hết hiệu lực**.

### Quản trị viên và nhân viên

- Dashboard doanh thu, đơn vé, tài khoản và hoạt động gần đây.
- Quản lý phim, rạp, phòng, ghế, suất chiếu và người dùng.
- Tạo một suất hoặc **tạo suất chiếu hàng loạt** theo khoảng ngày và nhiều khung giờ.
- Tự tính giờ kết thúc theo thời lượng phim + 15 phút chuẩn bị phòng; tự bỏ qua lịch trùng phòng.
- Quản lý voucher, điểm thành viên, đơn vé và yêu cầu hoàn tiền.
- Admin/Staff check-in bằng mã đơn `BK...` hoặc QR; một đơn chỉ check-in một lần.
- Xuất báo cáo doanh thu CSV, Excel và PDF.

## Công nghệ

- PHP 8.2+, Laravel 11
- MySQL 8+/MariaDB 10.6+
- Blade, Bootstrap, Vite
- Endroid QR Code
- MoMo/VNPAY (chế độ mô phỏng hoặc sandbox)

## Cài đặt trên XAMPP

```powershell
git clone https://github.com/dnthtrucs/CinemaStar.git
cd CinemaStar
composer install
copy .env.example .env
php artisan key:generate
```

Tạo database trong phpMyAdmin, ví dụ `cinema_db`, sau đó chỉnh file `.env`:

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

Khởi tạo dữ liệu và chạy ứng dụng:

```powershell
php artisan migrate --seed
npm install
npm run build
php artisan serve
```

Mở: http://127.0.0.1:8000

> Không commit file `.env`. File này chứa cấu hình database, email và khóa thanh toán.

## Cấu hình email

CinemaStar gửi email sau khi thanh toán thành công. Cấu hình SMTP trong `.env`, sau đó xóa cache cấu hình:

```powershell
php artisan optimize:clear
```

Khi dùng Gmail, nên dùng App Password thay cho mật khẩu đăng nhập Gmail thông thường.

## Cấu hình thanh toán

Khi demo trên XAMPP:

```env
DEMO_PAYMENT_ENABLED=true
PAYMENT_MODE=simulate
```

Để kết nối MoMo/VNPAY sandbox, nhập thông tin do cổng thanh toán cung cấp trong `.env` và đặt:

```env
PAYMENT_MODE=sandbox
APP_URL=https://ten-mien-cong-khai.example
```

Return URL/IPN chỉ hoạt động ổn định khi `APP_URL` là địa chỉ HTTPS công khai.

## Tác vụ theo thời gian

Chạy lệnh sau trong một cửa sổ PowerShell riêng khi demo để xử lý các tác vụ đã lập lịch, như giải phóng ghế giữ quá hạn:

```powershell
php artisan schedule:work
```

## Kiểm thử

```powershell
php artisan test
```

Tài liệu chi tiết:

- [Kiến trúc hệ thống](docs/ARCHITECTURE.md)
- [Kế hoạch kiểm thử](docs/TEST_PLAN.md)
- [Hướng dẫn triển khai](docs/DEPLOYMENT.md)
