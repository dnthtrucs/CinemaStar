# Triển khai CinemaStar trên Render

CinemaStar có `Dockerfile` và `.dockerignore` ở thư mục gốc. Render dùng Docker để cài PHP, Composer, Node/Vite và PHP GD.

## 1. Chuẩn bị

- Repository GitHub: `dnthtrucs/CinemaStar`.
- Một MySQL công khai. Render không cung cấp MySQL tích hợp; có thể dùng Aiven, Railway hoặc MySQL hosting riêng.
- Gói Render hoạt động liên tục nếu nhận thanh toán thật. Service ngủ có thể làm callback bị chậm hoặc thất bại.

## 2. Tạo Web Service

Trong Render chọn **New → Web Service**, kết nối repository, chọn branch `main` và đặt:

| Trường | Giá trị |
|---|---|
| Runtime | Docker |
| Root Directory | Để trống |
| Build Command | Để trống |
| Start Command | Để trống |
| Region | Singapore |

Render tự dùng `Dockerfile`. Sau deploy sẽ có URL HTTPS dạng `https://ten-service.onrender.com`.

## 3. Environment

Tạo `APP_KEY` trên máy phát triển:

```powershell
php artisan key:generate --show
```

Thêm vào Render Environment. Không commit các giá trị bí mật:

```env
APP_NAME=CinemaStar
APP_ENV=production
APP_DEBUG=false
APP_URL=https://ten-service.onrender.com
APP_TIMEZONE=Asia/Ho_Chi_Minh
APP_KEY=base64:...

DB_CONNECTION=mysql
DB_HOST=...
DB_PORT=3306
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...

SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync

MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=...
MAIL_FROM_NAME=CinemaStar

PAYMENT_MODE=production
SEPAY_API_KEY=...
SEPAY_BANK_CODE=MBBank
SEPAY_ACCOUNT_NUMBER=...
SEPAY_ACCOUNT_NAME="TEN_CHU_TAI_KHOAN"
SEPAY_QR_URL=https://qr.sepay.vn/img
```

Thêm biến MoMo/VNPAY chỉ sau khi có mã merchant production do từng cổng cấp.

## 4. Khởi tạo database

Khi Web Service đã live, mở Render Shell:

```bash
php artisan migrate --force
php artisan storage:link --force
php artisan optimize:clear
```

Chỉ với database mới muốn có dữ liệu mẫu:

```bash
php artisan db:seed --force
```

## 5. Webhook và scheduler

Cấu hình webhook SePay:

```text
https://ten-service.onrender.com/payments/sepay/webhook
```

Tạo Render Cron Job từ cùng repository, dùng lịch và lệnh:

```text
* * * * *
php artisan schedule:run
```

Copy Environment cần thiết từ Web Service sang Cron Job.

## 6. Kiểm tra sau triển khai

- Đăng ký/đăng nhập, chọn ghế, ghế VIP và ghế đôi.
- Thanh toán SePay với giá trị nhỏ; kiểm tra webhook đổi đơn sang Đã thanh toán.
- Kiểm tra email QR PNG và scan QR đơn bằng tài khoản Staff.
- Kiểm tra banner đã tải. Lưu trữ file trên Render là tạm thời; dùng Cloudinary/S3 cho banner lâu dài.
- Giữ `APP_DEBUG=false`; không công khai `.env`, Gmail App Password, API key hoặc mật khẩu database.
