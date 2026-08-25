# Kiến trúc hệ thống CinemaStar

## Phạm vi nghiệp vụ

CinemaStar có ba nhóm người dùng:

| Vai trò | Phạm vi |
|---|---|
| Khách hàng | Xem lịch, chọn ghế, đặt vé, thanh toán, dùng voucher/điểm, xem vé và gửi yêu cầu hoàn tiền |
| Nhân viên (Staff) | Tra cứu mã đơn/QR và check-in khách vào rạp |
| Quản trị viên (Admin) | Quản lý dữ liệu rạp, lịch chiếu, ưu đãi, tài khoản, hoàn tiền và báo cáo |

Luồng chính: **chọn phim → chọn suất → chọn ghế → giữ ghế → thanh toán → phát hành vé QR/email → check-in**.

## Mô hình dữ liệu

~~~mermaid
erDiagram
    USERS ||--o{ BOOKINGS : creates
    USERS ||--o{ REFUND_REQUESTS : requests
    CINEMAS ||--o{ ROOMS : contains
    ROOMS ||--o{ SEATS : has
    MOVIES ||--o{ SHOWTIMES : scheduled
    ROOMS ||--o{ SHOWTIMES : hosts
    SHOWTIMES ||--o{ BOOKINGS : receives
    BOOKINGS ||--|{ TICKETS : issues
    SEATS ||--o{ TICKETS : assigned
    BOOKINGS ||--o{ PAYMENTS : attempts
    VOUCHERS ||--o{ BOOKINGS : applied_to
~~~

## Quy tắc nghiệp vụ quan trọng

1. Mỗi ghế chỉ được bán một lần cho một suất chiếu. Việc giữ/tạo booking chạy trong transaction để chống hai khách đặt cùng ghế.
2. Booking chưa thanh toán có hạn giữ ghế. Khi quá hạn, tác vụ định kỳ giải phóng ghế và cập nhật trạng thái đơn.
3. Tổng tiền do server tính từ giá suất chiếu, phụ thu ghế, voucher và điểm đổi; không tin dữ liệu giá từ trình duyệt.
4. Voucher được áp dụng trước, sau đó điểm thành viên giảm tiếp trên số tiền còn lại. Một điểm tương đương 1.000đ.
5. Điểm được cộng một lần sau khi thanh toán thành công; điểm đã dùng được hoàn lại khi đơn chưa thanh toán bị hủy/hết hạn.
6. Mỗi đơn thanh toán có một mã đơn BK... và QR. Nhân viên check-in một lần cho cả đơn, không check-in lặp.
7. Trạng thái vé ưu tiên: **Đã check-in** → **Đã hết hiệu lực** (quá giờ kết thúc phim, chưa check-in) → **Sẵn sàng vào rạp**.
8. Khi tạo lịch chiếu, giờ kết thúc = giờ bắt đầu + thời lượng phim + 15 phút chuẩn bị phòng. Các suất chồng thời gian trong cùng phòng bị từ chối hoặc bỏ qua khi tạo hàng loạt.
9. Callback thanh toán chỉ xác nhận khi hợp lệ; không ghi nhận thanh toán/điểm/doanh thu hai lần.

## Trạng thái chính

| Thực thể | Trạng thái tiêu biểu |
|---|---|
| Booking | pending, confirmed, cancelled, expired |
| Payment | initiated, pending, success, failed |
| Showtime | scheduled, cancelled |
| Refund request | requested, approved, refunded, rejected |
| Vé hiển thị | Sẵn sàng vào rạp, Đã check-in, Đã hết hiệu lực |

## Tổ chức mã nguồn

- app/Http/Controllers: luồng khách hàng, hồ sơ, đặt vé, thanh toán, hoàn tiền và xác thực vé.
- app/Http/Controllers/Admin: dashboard, quản lý dữ liệu rạp, lịch chiếu, voucher, báo cáo, check-in và xử lý hoàn tiền.
- app/Services/BookingService.php: giữ ghế, transaction và tính tiền đơn.
- app/Services/PaymentGatewayService.php: tạo yêu cầu thanh toán và xác minh callback.
- app/Notifications: email xác nhận thanh toán và thông báo hoàn tiền.
- app/Http/Requests: validation phía server cho dữ liệu quản trị.
- database/migrations: schema, khóa ngoại và ràng buộc toàn vẹn.
- resources/views: giao diện Blade cho khách hàng, Admin và Staff.
- tests/Feature: kiểm thử các luồng nghiệp vụ trọng yếu.
