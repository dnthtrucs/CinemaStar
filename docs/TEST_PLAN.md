# Kế hoạch kiểm thử CinemaStar

## Kiểm thử tự động

Tạo database MySQL `cinema_test` trước khi chạy. PHPUnit cố định tên database và TestCase chặn cấu hình database khác trước khi `RefreshDatabase` chạy; không chạy test trên `cinema_db`. Test không phụ thuộc Vite manifest.

```powershell
php artisan config:clear
php artisan test
```

### Feature test hồi quy

| File | Nội dung |
|---|---|
| `LoyaltyPointsTest` | Lưu điểm đã dùng, không trừ lặp, hủy hoàn đủ điểm, lưu voucher, cộng điểm thưởng đúng một lần |
| `TicketCheckinTest` | URL QR công khai giữ định dạng cũ, chữ ký đúng/sai, không công bố thông tin liên hệ, đơn unpaid không hiện hợp lệ |
| `RefundTest` | Yêu cầu trùng trả lỗi thân thiện; chặn đơn đã check-in/đã bắt đầu; chuyển trạng thái đúng thứ tự; hoàn tiền giải phóng ghế, chỉnh điểm, ghi notification; chặn check-in và hoàn lặp; loại doanh thu đã hoàn |
| `VoucherTest` | Đơn tối thiểu trống lưu 0; hủy/hết hạn không tiêu lượt; thanh toán ghi lịch sử; chặn dùng lại; tính cả lượt đang giữ |
| `PaymentSafetyTest` | Chặn xác nhận giao dịch đã hủy; lỗi nhập điểm gắn vào form; expiry khóa và kiểm tra lại đơn, không xóa vé đã trả tiền; webhook đến muộn ghi log và cảnh báo đúng một lần, kể cả đơn đã hoàn điểm |
| `ManualRefundTest` | QR cũ sau đổi điểm, thanh toán lần hai và callback sau timeout; hai mã ngân hàng khác nhau dùng cùng QR được ghi đủ tiền cần hoàn; retry không ghi thêm; không cộng lại điểm/lượt voucher; VNPAY giữ phản hồi 02 cho đơn đã xác nhận |
| `PaymentTransactionRaceTest` | Hai PHP process xử lý cùng mã ngân hàng trên hai payment khác nhau: unique index chỉ cho một biên lai thành công, callback còn lại trả kết quả gửi lại, không lỗi 500 |
| `PaymentMigrationTest` | Nâng cấp FK/index trên bảng có dữ liệu; dừng rõ ràng nếu trùng mã ngân hàng, giữ nguyên dữ liệu; cho phép nhiều NULL và cùng mã ở hai provider |
| `AccountSafetyTest` | Tài khoản khóa bị đăng xuất khỏi session cũ; chặn xóa tài khoản có lịch sử thanh toán/hoàn tiền |
| `AccountDeletionRaceTest` | Hai PHP process thật xác nhận callback và xóa tài khoản đồng thời; không deadlock, biên lai và điểm thưởng được giữ |
| `ShowtimeSafetyTest` | Chặn đổi lịch khi đang giữ ghế; vẫn đổi giá được; giải phóng đơn hết hạn trước khi đổi lịch; hủy suất có khách trả tiền hiện cảnh báo; hủy suất trả ghế/điểm của đơn đang giữ và chặn thanh toán |
| `TestDatabaseGuardTest` | `DB_URL` trỏ sang database khác bị chặn trước khi test chạy |
| `HomePageTest` | Trang chủ hiển thị được |
| `ManualRefundResolutionTest` | Admin xử lý hoàn tay đúng một lần, giữ biên lai, có ghi chú/log; lọc và phân trang; staff/khách bị chặn |
| `LocalAssetsTest` | Trang chủ, đăng nhập, check-in không còn host CDN |
| `Unit/SchedulerSupervisorTest` | Scheduler thoát mã42 được khởi động lại sau5giây, có log/web còn chạy; PATH chỉ có fixture, cleanup dùng kill builtin và xác nhận không còn supervisor, không cần DB |
| `FileStorageSecurityTest` | Trang chủ đọc lại model từ cache file với allow-list; session JSON file giữ lỗi/old input/flash/đăng nhập qua các request |
| `MembershipTest`, `Unit/MembershipTierTest` | Ngưỡng 0/300/1000; đổi điểm giữ hạng; đơn hoàn không tính; hệ số trước đơn, callback lặp và thu hồi đúng điểm khi hoàn |
| `MembershipConcurrencyTest` | Hai callback độc lập cùng khách 299 điểm: đơn đầu cộng 8, đơn sau đọc commit mới và cộng 9; retry không cộng trùng, isolation mặc định không bị đổi |
| `ConcessionTest` | VIP/ghế đôi + combo, voucher/min_order toàn đơn, combo không hợp lệ, giá snapshot, CRUD/ảnh/quyền, tiền hoàn/điểm, hủy/hết hạn, mọi màn hình vé, cặp H3–H4 vẫn chọn được |
| `ConcessionZeroQuantityTest` | HTTP/service nhận dòng số lượng0 và bỏ trước kiểm tra khả dụng/giới hạn; 6 dòng chỉ5loại dương hợp lệ, 6loại dương bị từ chối |
| `ConcessionMigrationTest` | Nâng cấp/rollback trên booking có sẵn không đổi dữ liệu, giá combo mặc định 0 cho đơn cũ; seed lặp không đè giá admin |
| `Unit/VoucherDiscountTest` | Tính giảm giá voucher cố định/phần trăm, nhãn ghế (không cần database) |

Các test điểm, QR, hoàn tiền, voucher và thanh toán đã được chạy trước sửa để tái hiện lỗi, sau đó chạy lại để xác nhận pass. Helper `showtimeWithSeats()` dùng chung tại `tests/Concerns/CreatesCinemaBookings.php`.

`AccountSafetyTest` kiểm tra thêm mọi trạng thái payment (initiated/pending/failed) đều chặn xóa tài khoản; đơn hủy/hết hạn không có payment vẫn được xóa; FK từ payments chặn xóa trực tiếp booking đã phát sinh giao dịch.

Chống trùng callback dựa trên `(provider, transaction_id)`. Khoản tiền mới chuyển lại QR đã xử lý tạo biên lai `DUPLICATE_TRANSFER` và một log để admin hoàn thủ công. Migration unique index không tự sửa/xóa biên lai trùng: cần đối soát dữ liệu rồi chạy lại migration.

Hai test chạy nhiều process dùng MySQL `cinema_test` và Symfony Process có sẵn, không cần `pcntl`. Không chạy nhiều suite dùng chung database này đồng thời.

## Test case nghiệp vụ chính

| ID | Trường hợp | Kết quả mong đợi |
|---|---|---|
| BK-01 | Chọn ghế thường/VIP | Đơn có đúng ghế và phụ thu 30.000₫ cho từng ghế VIP |
| BK-02 | Chọn ghế đôi H1–H2 | Một thao tác chọn đủ hai vị trí; giá bằng `2 × (giá cơ bản + 30.000₫) + 30.000₫` |
| BK-03 | Một vị trí của ghế đôi đã được đặt | Cả cặp không thể chọn/đặt |
| BK-04 | Hai khách chọn cùng ghế | Khách thứ hai bị từ chối bởi transaction/khóa ghế |
| BK-05 | Chọn ghế trước đăng nhập | Đăng nhập/đăng ký xong quay lại suất chiếu và giữ lựa chọn trên trình duyệt |
| BK-06 | Đơn hết hạn hoặc hủy | Ticket tạm bị xóa, ghế mở lại, điểm đã giữ được hoàn |
| VC-01 | Voucher và điểm | Voucher tính trước; 1 điểm giảm 1.000₫ trên số tiền còn lại |
| PM-01 | Thanh toán SePay đúng số tiền | Webhook đổi payment/booking thành công và phát hành vé |
| PM-02 | Webhook sai khóa, sai mã đơn hoặc sai số tiền | Bị từ chối, không đổi trạng thái đơn |
| PM-03 | Webhook lặp | Không cộng điểm và gửi email lần hai |
| EM-01 | Email sau thanh toán | Email có QR PNG hiển thị trong nội dung; không có file QR SVG |
| QR-01 | Quét QR đơn | Check-in toàn bộ ghế hợp lệ của một đơn đúng một lần |
| QR-02 | Quét lại QR đơn | Bị từ chối, ticket vẫn ở trạng thái Đã check-in |
| QR-03 | Suất đã kết thúc | Ticket chưa check-in không được check-in |
| ST-01 | Tạo suất trùng phòng | Bị từ chối hoặc bỏ qua khi tạo hàng loạt |
| RF-01 | Khách yêu cầu hoàn tiền | Admin duyệt/từ chối đúng trạng thái |
| AU-01 | Truy cập Admin/Staff sai quyền | Bị từ chối |

## Quy trình kiểm thử thủ công

1. Chạy migrate trên database thử nghiệm và tạo dữ liệu cần thiết.
2. Kiểm tra hàng E–G màu VIP, hàng H màu hồng và từng cặp ghế đôi.
3. Mở cùng suất chiếu ở hai tài khoản để kiểm tra giữ ghế/chống trùng.
4. Thử khách chọn ghế khi chưa đăng nhập, sau đó đăng nhập và tiếp tục thanh toán.
5. Thanh toán SePay bằng khoản nhỏ, kiểm tra trạng thái đơn, email và điểm.
6. Đăng nhập Staff trên điện thoại, quét QR của đơn và kiểm tra toàn bộ ghế được check-in.
7. Thử quét lại QR, quét đơn chưa thanh toán và đơn đã quá giờ.
8. Kiểm tra voucher, hoàn tiền, lịch chiếu hàng loạt và xuất báo cáo.
9. Bỏ trống đơn tối thiểu khi tạo voucher; đặt rồi hủy/hết hạn và kiểm tra lượt dùng; thanh toán rồi thử dùng lại mã bằng cùng tài khoản.
10. Gửi yêu cầu hoàn hai lần; admin duyệt rồi xác nhận đã chuyển tiền. Kiểm tra ghế được mở, điểm được điều chỉnh, doanh thu giảm và QR cũ không check-in được.
11. Gửi webhook trễ sau khi hết hạn đơn: ghế không được đặt lại và Dashboard hiện giao dịch cần xử lý hoàn tiền. Gửi lại webhook không tạo thêm log.
12. Khóa tài khoản đã đăng nhập rồi mở trang mới; thử xóa tài khoản đã có đơn thanh toán hoặc yêu cầu hoàn tiền.
13. Mở trang phim bằng khách chưa mua vé và khách đã trả tiền: chỉ khách đã trả tiền thấy form; chỉ đánh giá approved và điểm trung bình của chúng được công khai.
14. Mở Bản in (HTML), kiểm tra hộp thoại in, tiêu đề và bảng doanh thu khi in/Lưu PDF.
15. Chọn ghế và combo 0–10 phần, tải lại và đăng nhập: lựa chọn được giữ, tạm tính gồm cả hai. Trên 5 loại không tiếp tục được. Sau đặt, đổi giá/tắt combo trong admin rồi kiểm tra đơn cũ và các bản vé vẫn giữ đơn giá lúc đặt.
16. Kiểm tra huy hiệu hạng, điểm tích lũy và điểm còn lại tại hồ sơ/dashboard; đổi điểm không tụt hạng, hoàn một đơn làm giảm điểm tích lũy và trừ đúng điểm đã cộng.
