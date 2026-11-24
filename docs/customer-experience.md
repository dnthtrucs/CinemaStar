# Trải nghiệm CinemaStar — bản nâng cấp 25/11/2026

## Khách hàng

- Trang chủ có “Dành cho bạn” khi đăng nhập; khách chưa đăng nhập thấy “Khám phá phim”. Hồ sơ cho chọn/xóa 9 thể loại yêu thích. Gợi ý dựa trên sở thích, tối đa 50 đơn đã thanh toán gần nhất và 50 đánh giá đã duyệt gần nhất của chính tài khoản. Phim ngừng chiếu không được gợi ý; phim có lịch còn nhận đặt được ưu tiên. Khi chưa có lịch, thẻ ghi rõ “Lịch chiếu chưa công bố”. Đây là bộ gợi ý theo quy tắc, không phải mô hình học máy đã huấn luyện.
- Sơ đồ ghế cập nhật mỗi 5 giây khi tab đang hiển thị. Ghế chỉ được giữ sau khi tạo đơn, không phải ngay khi bấm chọn. Máy chủ kiểm tra lại và khóa tài nguyên trước khi tạo đơn. Gợi ý nhóm 1–10 người chọn ghế liền nhau, không đi xuyên ghế đã giữ/mua và không tách cặp đôi.
- Thông báo lưu trong tài khoản, độc lập với gửi mail. Số chưa đọc cập nhật mỗi 15 giây; có thông báo nổi khi phát hiện mục mới trong phiên đang mở. Thông báo cũ không được nhắc lại liên tục. Đây là thông báo trong trang, không phải thông báo đẩy khi đóng trình duyệt.
- Vé của tôi có bộ lọc Tất cả / Sắp chiếu / Chờ thanh toán / Lịch sử.

## Đổi suất có điều kiện

1. Vào chi tiết đơn đã thanh toán → Đổi suất có điều kiện.
2. Vé chưa dùng, chưa yêu cầu hoàn; suất cũ và suất mới còn hơn 60 phút. Chỉ đổi cùng phim, cùng rạp, giữ số vị trí và combo.
3. Chọn đủ vị trí; ghế đôi phải đủ cặp. Xem tổng mới dự kiến và phần cần bù/hoàn. Máy chủ tính lại, không tin giá gửi từ trình duyệt.
4. Gửi lý do. Vé cũ vẫn còn hiệu lực trong khi chờ. Ghế mới chưa được giữ ở bước yêu cầu.
5. Quản trị → Đổi suất: kiểm tra ghế và chứng từ thu bù/hoàn thực tế. Khi có chênh lệch phải nhập mã chứng từ chưa được dùng và đánh dấu xác nhận. Nút duyệt chỉ ghi nhận nghiệp vụ đã đối soát; không tự chuyển tiền hay tự thu tiền của khách.
6. Khi duyệt, hệ thống khóa và kiểm tra lại đơn, suất, giá, ghế; từ chối thay đổi nếu vé đã dùng, hết hạn điều kiện, đang hoàn, giá thay đổi hay ghế đã bị mua. Vé cũ không bị mất trong các trường hợp này. Quản trị từ chối yêu cầu và hướng dẫn lập lại khi cần.
7. Duyệt thành công giải phóng ghế cũ, tạo vé mới, xoay mã đặt vé/mã vạch để vô hiệu mã cũ, lưu nhật ký và thông báo mở vé mới. Nếu cần gửi lại email, dùng chức năng gửi vé của quản trị hiện có.

Giữ nguyên ưu đãi voucher/điểm đã dùng và combo chụp tại đơn; không cộng thêm điểm cho lần đổi. Biên lai gốc được giữ; chênh lệch có chứng từ được ghi thêm vào payments với provider exchange_adjustment (dương = thu bù, âm = đã hoàn). Báo cáo cộng theo số tiền thực ghi nhận. Không sửa số tiền biên lai ngân hàng gốc.

## Tiếp cận và điện thoại

- Tab/phím cách chọn ghế; ghế có tên đọc rõ; có liên kết bỏ qua menu và viền focus. Nút Chữ dễ đọc tăng cỡ chữ, được lưu trên máy; tôn trọng tùy chọn giảm chuyển động của thiết bị.
- Quản trị → Phòng → Sửa → Vị trí hỗ trợ xe lăn. Chỉ đánh dấu các vị trí đã xác nhận thực tế; không tự gán vị trí giả vào phòng. Không đánh dấu ghế đôi. Trang chọn ghế hiển thị ♿ ở vị trí đã xác nhận.
- Chrome Android: nút Cài CinemaStar xuất hiện khi trình duyệt đủ điều kiện cài. iPhone Safari: Chia sẻ → Thêm vào Màn hình chính. Cần HTTPS hoặc localhost để đăng ký service worker.
- Chỉ lưu trang mất kết nối và icon công khai; không lưu trang tài khoản, vé, thanh toán hoặc phản hồi API ngoại tuyến. Mất mạng sẽ yêu cầu kết nối lại để xem trạng thái chính xác.

## Cập nhật dữ liệu

Migration 2026_11_24_000001_add_customer_experience thêm favorite_genres, is_accessible và booking_exchanges; không xóa dữ liệu hiện có. Đã áp dụng trên cơ sở dữ liệu cục bộ. Render cần triển khai bản mã mới; deploy/start.sh hiện có sẽ chạy migration. Không chạy seed dữ liệu demo khi triển khai.

## Kiểm tra

- tests/Feature/CustomerExperienceTest.php: đổi suất và tính tiền, điều kiện, quyền truy cập, ghế mất chỗ, giá thay đổi, vô hiệu mã vé cũ, gợi ý riêng tư, sở thích, vị trí tiếp cận, tài nguyên PWA.
- tests/js/seat-suggestions.test.mjs, notification-polling.test.mjs, pwa.test.mjs: thuật toán nhóm ghế, thông báo và bộ nhớ ngoại tuyến an toàn.
- Camera/máy in và quy trình đối soát tiền thực cần được nghiệm thu tại rạp. Các ca tự động dùng cinema_test, mailer thử và giao dịch giả lập trong kiểm thử; không phải chuyển tiền thật.

## Theo dõi và rút yêu cầu đổi suất — 25/11/2026

- Chi tiết vé → Đổi suất / theo dõi yêu cầu: hiện lịch sử, thời điểm, trạng thái, tổng cũ/mới và phần tiền bù/hoàn. Yêu cầu mới lưu thêm ảnh chụp dữ liệu ghế và thời gian tại lúc gửi; yêu cầu cũ vẫn đọc được.
- Khi đang chờ, khách có nút Rút yêu cầu, giữ vé hiện tại. Chỉ chủ vé được rút; rút không hủy vé, không giải phóng ghế cũ và không thay đổi thanh toán. Có thể lập yêu cầu mới sau khi rút.
- Không rút được yêu cầu đã duyệt/từ chối/rút trước đó. Duyệt và rút dùng khóa cùng đơn/yêu cầu nên chỉ một thao tác có thể kết thúc yêu cầu.
- Rút yêu cầu không tự thu hoặc hoàn tiền. Nếu đã đối soát ngoài hệ thống, cần liên hệ rạp để xử lý khoản tiền thực tế.
- Sau thời hạn đổi suất, khách vẫn xem được lịch sử và rút yêu cầu đang chờ; không còn biểu mẫu lập yêu cầu mới.
- Quản trị → Đổi suất: mặc định chỉ hiện Chờ xử lý, có số lượng từng trạng thái và các mục Đã đổi / Từ chối / Khách đã rút / Tất cả. Hiện ghế cũ/mới, tổng cũ/mới, lý do, người xử lý và thời gian.
- Duyệt mới yêu cầu xác nhận kiểm tra chứng từ. Từ chối không yêu cầu quản trị đánh dấu rằng đã có giao dịch tiền.
