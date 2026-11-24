# Nâng cấp trải nghiệm đặt vé — 24/11/2026

- Sơ đồ ghế lấy trạng thái máy chủ mỗi 5 giây khi trang đang hiển thị; cập nhật ngay khi quay lại tab. Không cần dịch vụ WebSocket riêng.
- Ghế vừa bị giữ/mua sẽ bị vô hiệu hóa và bỏ khỏi lựa chọn. Ghế hết hạn được giải phóng qua BookingService. Máy chủ vẫn kiểm tra, khóa tài nguyên và tính giá khi tạo đơn.
- Chọn số người 1–10 và bấm “Gợi ý ghế liền nhau”. Hệ thống tìm nhóm không đứt đoạn, ưu tiên giữa phòng, không tách ghế đôi. Gợi ý không đồng nghĩa giữ ghế.
- Thông báo xác nhận được lưu trong cùng transaction với thanh toán thành công. Callback lặp không tạo thêm thông báo; lỗi email sau commit không xóa thông báo/vé.
- Mục “Thông báo” trên thanh điều hướng hiển thị số chưa đọc. Chỉ chủ tài khoản xem hoặc đánh dấu đọc được thông báo của mình. Nút mở vé chuyển đến trang đơn có kiểm tra quyền sở hữu hiện có.
- Không cần migration mới: sử dụng bảng notifications có sẵn. Các đơn cũ không được tạo thông báo ngược; vé cũ vẫn ở “Vé của tôi”.
- Bộ đếm thông báo cập nhật khi tải/chuyển trang, không phải thông báo đẩy của hệ điều hành.

## Kiểm tra thủ công

1. Mở cùng suất ở hai trình duyệt/tài khoản. Tạo đơn giữ ghế ở cửa sổ thứ nhất; cửa sổ thứ hai cập nhật trong khoảng 5 giây và không tiếp tục giữ ghế đã mất.
2. Hủy đơn chưa thanh toán; ghế xuất hiện trở lại sau lần cập nhật kế tiếp.
3. Thử gợi ý 1, 2, 3 và 10 người; nhóm không đủ ghế liền nhau được báo rõ; ghế đôi không bị tách.
4. Hoàn tất một thanh toán thử hợp lệ; vào Thông báo để mở vé; đánh dấu đã đọc và tải lại.
5. Không dùng bộ kiểm thử với dữ liệu vận hành. Tests/TestCase.php chỉ cho phép cinema_test.

Đây là thay đổi mã nguồn trên máy; cần triển khai phiên bản mới trước khi website Render có các chức năng này.
