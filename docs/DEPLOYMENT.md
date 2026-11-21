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

### Không có Render Cron Job

Đặt `RUN_SCHEDULER=true` trong Environment của Web Service. Script `deploy/start.sh` kiểm tra HTTPS/APP_KEY, migrate, tạo storage link và dựng cache; không tự tạo tài khoản mẫu; sau đó chạy `schedule:work` nền, log chung với web, trước khi mở HTTP server. Khi scheduler thoát, vòng giám sát ghi mã thoát, đợi 5 giây rồi khởi động lại. Web vẫn dùng `exec php artisan serve` làm tiến trình chính. Mặc định là `false`.

Chỉ dùng **một** trong hai cách: Cron Job bên ngoài hoặc `RUN_SCHEDULER=true`; không bật cả hai cùng lúc. Scheduler trong web chỉ chạy khi container còn hoạt động; service ngủ sẽ không chạy tác vụ trong thời gian ngủ.

### Cache và session Laravel 13

Cache chỉ cho phép deserialize Eloquent Collection và các model trang chủ (Banner, Movie, Showtime, Room, Cinema). Session dùng JSON; lỗi form, dữ liệu nhập lại, flash và đăng nhập đã được kiểm tra bằng session file thật. Khi deploy lần đầu cấu hình JSON, người dùng hiện có sẽ bị đăng xuất **một lần** và cần đăng nhập lại.

## 6. Kiểm tra sau triển khai

- Đăng ký/đăng nhập, chọn ghế, ghế VIP và ghế đôi.
- Thanh toán SePay với giá trị nhỏ; kiểm tra webhook đổi đơn sang Đã thanh toán.
- Kiểm tra email QR PNG và scan QR đơn bằng tài khoản Staff.
- Kiểm tra banner đã tải. Lưu trữ file trên Render là tạm thời; dùng Cloudinary/S3 cho banner lâu dài.
- Giữ `APP_DEBUG=false`; không công khai `.env`, Gmail App Password, API key hoặc mật khẩu database.


## Bộ triển khai HTTPS hiện tại

- Dùng `render.yaml` để tạo web service Docker tại Singapore. Tên dịch vụ có thể đổi nếu trùng.
- Chọn gói Free cho bản trình diễn, chưa tạo tài nguyên có phí. URL thực tế do Render trả về; không tự đoán URL.
- Cần MySQL từ nhà cung cấp riêng: Render không có MySQL miễn phí tích hợp. Không nhập địa chỉ `127.0.0.1` hoặc IP Wi-Fi vào DB_HOST trên Render.
- Sau khi Render cấp URL, đặt APP_URL đúng URL HTTPS đó và triển khai lại. APP_KEY tạo một lần và lưu trong Environment; giữ ổn định qua các lần deploy.
- Blueprint để trống các bí mật bằng `sync: false`. Không đưa `.env` hay file SQL vào Git hoặc Docker image.
- Chạy `php artisan cinema:admin` trong shell của máy chủ để tạo quản trị viên với mật khẩu nhập ẩn. Không chạy DemoAccountSeeder trên website công khai.
- Bản web dùng Apache, document root là `public/`, nhận PORT do Render cấp. Laravel chỉ tin proxy khi TRUSTED_PROXIES được cấu hình. Cookie đăng nhập trên bản cloud được đặt Secure.
- Bật scheduler/queue trong một instance bằng RUN_SCHEDULER/RUN_QUEUE_WORKER. Nếu dùng cron/worker riêng thì tắt các tiến trình trùng tương ứng.
- Dùng SESSION_DRIVER=database và CACHE_STORE=database để giữ đăng nhập và khóa tác vụ qua deploy. Các bảng cần thiết đã có migration trong dự án.
- Docker không chứa storage của máy phát triển. Chuyển riêng ảnh công khai sang lưu trữ bền vững hoặc volume; không chuyển file sao lưu, khóa, session và dữ liệu khách hàng cùng ảnh.
- MAIL_MAILER=log chỉ dành cho chuẩn bị. Cấu hình SMTP trước khi dùng email xác nhận thật; không xem log mail là gửi email thành công.
- Cấu hình SePay webhook tới `<APP_URL>/payments/sepay/webhook`, API Key và API Token riêng trong Environment. API_TOKEN dùng đọc đối soát, API_KEY xác thực webhook.
- Free web service có thể ngủ khi không có truy cập. Scheduler trong web không chạy lúc ngủ; để vận hành thanh toán liên tục cần chọn dịch vụ luôn hoạt động sau khi chủ tài khoản duyệt chi phí.

### Kiểm tra trước khi nhận khách

1. Địa chỉ HTTPS mở được từ điện thoại ngoài Wi-Fi của máy phát triển, không có cảnh báo chứng chỉ.
2. Đăng nhập/đăng xuất ổn định; tài khoản khách không truy cập được chức năng in vé.
3. Tài nguyên CSS/JS/ảnh và liên kết QR đều dùng hostname công khai, không chứa localhost hay IP nội bộ.
4. Kiểm tra giao dịch SePay với sự cho phép của chủ tài khoản, webhook/đối soát tự cập nhật và không cộng điểm hai lần.
5. Kiểm tra lưu ảnh sau restart, sao lưu database ngoài web container và thử khôi phục trên database riêng.
6. Gắn tên miền riêng sau: khai báo domain trên Render, nhập đúng bản ghi DNS do Render cấp và chờ trạng thái TLS hợp lệ. Không cần mua tên miền để có HTTPS đầu tiên.

Tài liệu nhà cung cấp: https://render.com/docs/docker, https://render.com/docs/free, https://render.com/docs/custom-domains.


## Gmail API qua HTTPS trên Render Free

Render Free chặn SMTP Gmail ở cổng 587. Website hỗ trợ gửi nguyên email MIME có QR qua Gmail API trên HTTPS, dùng cùng mẫu vé điện tử hiện có.

1. Trong Google Cloud, chọn dự án CinemaStar và bật Gmail API.
2. Trong Google Auth Platform, thiết lập Branding (CinemaStar và email hỗ trợ), chọn Audience External. Khi thử đồ án ở chế độ Testing, thêm Gmail gửi vé vào Test users.
3. Tạo OAuth Client loại Web application. Authorized redirect URI phải đúng URL sau (không thêm dấu / cuối):

```text
https://cinemastar-90am.onrender.com/admin/email/gmail/callback
```

4. Lưu GMAIL_CLIENT_ID và GMAIL_CLIENT_SECRET trực tiếp trong Render Environment. Không gửi Client Secret vào chat, không commit file Google JSON. APP_KEY phải giữ nguyên để đọc được refresh token đã mã hóa.
5. Triển khai các biến đó, đăng nhập quản trị và mở `/admin/email`, chọn **Kết nối Gmail**. Đăng nhập tài khoản gửi vé và tự cấp quyền Google. Phạm vi Gmail chỉ gồm gmail.send; openid/email dùng xác minh tài khoản, không đọc hoặc xóa thư.
6. Sau khi trang báo **Đã kết nối**, đặt `MAIL_MAILER=gmail_api` trong Render và triển khai lại. Không cần SMTP password hay cổng 587. From được lấy từ tài khoản Google đã cấp quyền, tránh gửi nhầm địa chỉ cấu hình cũ.
7. Mở chi tiết đơn đã thanh toán, chọn **Gửi lại email vé**. Kiểm tra thư gửi và hộp thư nhận, cả mục Spam. Chỉ xem là gửi thành công khi Gmail API trả về message ID; việc vào Inbox cần kiểm tra ở hộp thư nhận.

Trong chế độ OAuth Testing, Google có thể hết hạn refresh token sau 7 ngày với quyền Gmail; cần kết nối lại trước buổi trình diễn nếu bị thu hồi/hết hạn. Không tự bỏ qua cảnh báo Google hay chia sẻ token. Kết nối được lưu mã hóa trong MySQL nên tồn tại qua các lần Render khởi động lại. Khóa access token trong cache cũng được mã hóa. Khi đổi OAuth Client ID cần kết nối lại.

Gửi thư không tự retry POST vì timeout có thể xảy ra sau khi Gmail đã nhận thư. Quản trị viên cần kiểm tra Sent trước khi gửi lại trong tình huống chưa rõ kết quả. Callback thanh toán vẫn giữ trạng thái đã thanh toán khi dịch vụ mail gặp lỗi.

Nguồn: https://developers.google.com/workspace/gmail/api/guides/sending
OAuth: https://developers.google.com/identity/protocols/oauth2/web-server
Quyền: https://developers.google.com/workspace/gmail/api/auth/scopes
