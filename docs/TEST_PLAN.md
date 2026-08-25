# Kế hoạch kiểm thử CinemaStar

## Kiểm thử tự động

Chạy:

~~~powershell
php artisan test
~~~

Bộ test hiện có kiểm tra xác thực, hồ sơ người dùng và các luồng đặt vé quan trọng.

## Test case nghiệp vụ chính

| ID | Trường hợp | Kết quả mong đợi |
|---|---|---|
| BK-01 | Khách chọn nhiều ghế với loại ghế khác nhau | Tạo đơn đúng giá và đúng số vé |
| BK-02 | Hai khách chọn cùng một ghế | Khách thứ hai không tạo được đơn/ghế trùng |
| BK-03 | Đơn chưa thanh toán bị hủy hoặc quá hạn | Ghế được giải phóng; điểm đã đổi được hoàn lại nếu có |
| VC-01 | Voucher hợp lệ | Giá giảm đúng theo điều kiện voucher |
| LP-01 | Khách dùng điểm | 1 điểm giảm 1.000đ; không giảm quá tổng còn lại |
| PM-01 | Thanh toán demo thành công | Payment và booking cập nhật thành công; cộng điểm một lần |
| PM-02 | Callback thanh toán lặp | Không tạo doanh thu, email hay điểm thưởng lần thứ hai |
| QR-01 | Check-in theo mã đơn BK hoặc QR | Check-in toàn bộ vé của đơn một lần |
| QR-02 | Check-in lại đơn đã dùng | Bị từ chối, trạng thái vẫn là Đã check-in |
| QR-03 | Vé quá giờ kết thúc chưa check-in | Hiện Đã hết hiệu lực |
| ST-01 | Tạo một suất trùng phòng | Hệ thống báo lỗi trùng thời gian |
| ST-02 | Tạo suất hàng loạt | Tạo các suất hợp lệ; tự bỏ qua suất quá giờ hoặc trùng phòng |
| RF-01 | Khách gửi yêu cầu hoàn tiền | Đơn chuyển sang trạng thái yêu cầu hoàn tiền |
| AU-01 | Khách truy cập đường dẫn Admin/Staff | Hệ thống từ chối theo quyền |
| AU-02 | Staff check-in, không quản lý cấu hình | Chỉ truy cập được phần nghiệp vụ được cấp quyền |

## Quy trình kiểm thử thủ công trước bảo vệ

1. Chạy migrate và seed trên database thử nghiệm, sau đó đăng nhập bằng tài khoản Admin, Staff và Customer.
2. Admin tạo phim, rạp, phòng; thử tạo suất đơn lẻ và lịch hàng loạt có giờ trùng.
3. Customer chọn ghế, áp voucher và dùng điểm; xác nhận số tiền trước khi thanh toán.
4. Mở cùng một suất bằng tài khoản thứ hai để xác minh ghế đã giữ/mua không thể chọn lại.
5. Hoàn tất thanh toán demo; kiểm tra email xác nhận, mã đơn BK, QR và điểm được cộng.
6. Admin hoặc Staff nhập mã BK/quét QR để check-in; thử check-in lần thứ hai.
7. Kiểm tra một vé đã qua giờ phim nhưng chưa check-in có trạng thái Đã hết hiệu lực.
8. Tạo yêu cầu hoàn tiền và xác minh Admin có thể duyệt/từ chối đúng trạng thái.
9. Thử thay đổi booking ID trên URL bằng tài khoản khác; hệ thống phải từ chối.
10. Thử gửi giá tiền không hợp lệ từ trình duyệt; server phải tự tính lại giá.

## Kiểm thử MoMo/VNPAY sandbox

- Dùng URL HTTPS công khai trong APP_URL và khai báo đúng return/IPN URL.
- Kiểm tra thanh toán thành công, người dùng hủy, sai chữ ký, sai số tiền và callback lặp.
- Đối chiếu mã giao dịch trong bảng payments với dashboard sandbox của cổng thanh toán.
