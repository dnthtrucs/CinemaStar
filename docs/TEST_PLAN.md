# Kế hoạch kiểm thử CineBook

## Kiểm thử tự động

Chạy `php artisan test`. Bộ test bao gồm xác thực Breeze, hồ sơ người dùng và luồng đặt vé chính.

| ID | Trường hợp | Kết quả mong đợi |
|---|---|---|
| BK-01 | Khách chọn ghế thường + VIP | Tạo booking pending, đúng tổng tiền và 2 ticket |
| BK-02 | Hai khách chọn cùng một ghế | Khách sau nhận lỗi, không tạo đơn thứ hai |
| BK-03 | Hủy đơn chưa thanh toán | Booking cancelled, ticket giữ ghế được xóa |
| PM-01 | Thanh toán demo thành công | Payment success, booking confirmed/paid |
| AU-01 | Customer truy cập `/admin` | HTTP 403 |
| AU-02 | Admin truy cập `/admin` | HTTP 200 |

## Kiểm thử thủ công trước bảo vệ

1. Chạy `php artisan migrate:fresh --seed` và đăng nhập bằng hai tài khoản demo.
2. Admin thêm phim, rạp, phòng và suất chiếu; thử tạo hai suất trùng phòng.
3. Customer chọn ghế, xác nhận số tiền và hoàn tất thanh toán .
4. Dùng một tài khoản khác mở cùng suất chiếu; ghế đã mua phải bị khóa.
5. Admin tìm mã ticket, check-in hai lần; lần thứ hai phải bị từ chối.
6. Đặt vé nhưng không thanh toán, chạy `php artisan bookings:expire`; ghế phải được trả lại.
7. Thử sửa ID booking trên URL bằng tài khoản khác; hệ thống phải trả 403.
8. Thử gửi giá tiền giả từ trình duyệt; server phải bỏ qua và tự tính lại.

## Kiểm thử thanh toán sandbox

- Dùng HTTPS public URL cho `APP_URL` và đăng ký đúng return/IPN URL với gateway.
- Kiểm tra thành công, người dùng hủy thanh toán, sai chữ ký, sai số tiền và callback lặp.
- Đối chiếu transaction ID trong bảng `payments` với dashboard sandbox.
