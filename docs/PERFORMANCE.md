# Hiệu năng CinemaStar

## Điểm nghẽn đã đo trên Windows + Docker Desktop

Ngày 25/10/2026, máy local chạy PHP trong Linux container nhưng bind mount toàn bộ `D:\Claude\Cinema`, gồm `vendor` và cache PHP. Trang chủ có lượt trả HTML mất 1,5–3,7 giây, kết nối HTTP chỉ khoảng 1 ms. Trang phim dùng 2 query / khoảng 35 ms SQL; phần lớn thời gian thuộc bootstrap và đọc file, không phải số người dùng. OPcache đơn lẻ với mọi dependency vẫn trên bind mount chưa giải quyết được vấn đề.

Sau khi chuyển dependency và cache biên dịch sang volume Linux, giữ code bind mount để sửa trực tiếp: các lượt GET ấm đo được khoảng 96–160 ms cho trang chủ, phim, rạp và đăng nhập; lượt trang chủ đầu sau restart khoảng 580 ms. Đây là thời gian phản hồi HTML của máy này, không gồm tải poster ngoài mạng hoặc tốc độ dịch vụ thanh toán. Filesystem hoàn toàn Linux trong thí nghiệm đạt khoảng 26–57 ms sau làm nóng; không lấy số này làm tốc độ của cấu hình dev mặc định.

Docker xác nhận bind mount từ filesystem Linux nhanh hơn mount từ Windows: [WSL best practices](https://docs.docker.com/desktop/features/wsl/best-practices/). Nếu cần giảm thêm chi phí đọc code, đặt repo trong filesystem WSL và mở bằng IDE hỗ trợ WSL. Không cần chuyển repo để dùng các cải tiến bên dưới.

## Các thay đổi

- `vendor-data` và `runtime-data` chứa dependency, cache package/Blade trên Linux. Lần chạy đầu cần mạng để Composer cài đúng lock; restart sau không tải lại nếu manifest không đổi. Không mount đè thư mục session/upload, nên giữ nguyên dữ liệu và phiên hiện có.
- Web dùng OPcache với `validate_timestamps=1`, `revalidate_freq=0`; sửa PHP vẫn có hiệu lực ở request tiếp theo. CLI/test giữ `opcache.enable_cli=0`. Không dùng cache cấu hình/route cố định ở dev. Production đã có OPcache, config/route/view cache tại deploy.
- Trang “Vé của tôi” chỉ xử lý đơn hết hạn của người đang xem. Scheduler và trang chọn ghế vẫn xử lý đúng phạm vi; batch hết hạn dùng keyset 200 ID để không nạp toàn bộ backlog. Mỗi đơn được kiểm tra lại dưới khóa cũ; thứ tự khóa, trả ghế/điểm và callback không đổi.
- CSV đọc biên lai khi stream, theo batch 500; XLS/HTML cũng chỉ hydrate từng batch với eager loading, không giữ toàn bộ model trong RAM. XLS/HTML vẫn phải tạo nội dung HTML hoàn chỉnh, nên bộ nhớ chuỗi đầu ra còn tỷ lệ với số dòng. Ngày/filter và số tiền không đổi; thứ tự thêm ID làm mốc phụ khi cùng thời gian thanh toán.
- Các batch báo cáo dùng keyset `(paid_at, id)` thay offset: một đơn được hoàn trong khi xuất không làm dịch vị trí và bỏ sót biên lai khác ở batch kế tiếp. Có regression 504 biên lai và hoàn tiền giữa hai batch.
- Chỉ mục mới: lịch sử đơn theo user/ngày/ID; quét hết hạn theo status/payment_status/expires_at; báo cáo theo payment status/paid_at/booking; suất đang bán theo status/starts_at. Migration riêng, không sửa migration trên main. Có kiểm tra EXPLAIN và rollback giữ đơn/vé, khôi phục index khóa ngoại khi InnoDB đã thay index ngầm.
- Trình tạo QR chỉ tải khi trang có canvas vé; thư viện camera chỉ tải sau khi bấm mở scanner. JS chung giảm 106.709 → 81.465 byte; entry scanner giảm từ 335 KB → khoảng 1 KB, phần thư viện còn lại tải khi cần. Không bỏ chức năng QR hoặc đổi dữ liệu QR.
- Poster có kích thước xác định và decode bất đồng bộ; poster dưới phần hero/trang dài tải lười. Banner đầu ưu tiên cao, các slide còn lại tải lười.

## Kiểm tra

```powershell
docker compose up -d --build app
docker compose exec -T app php artisan test
docker compose exec -T app vendor/bin/pint --test
npm run build
curl.exe -s -o NUL -w "status=%{http_code} ttfb=%{time_starttransfer}\n" http://127.0.0.1:8000/movies
```

Lặp GET vài lần, phân biệt lượt đầu và lượt ấm. Đo lúc không build, cài package hoặc chạy suite DB; so cùng URL, cùng dữ liệu. PHPUnit chỉ dùng `cinema_test`, giữ nguyên guard và 87 test gốc. Không hạ bcrypt, tắt validation/CSRF, bỏ khóa ghế, hoặc cache trạng thái ghế/thanh toán để lấy số nhanh hơn.

Các regression mới: `ScopedExpiryTest` (đúng phạm vi, trả điểm/ghế và scheduler), `StreamingRevenueTest` (503 biên lai qua nhiều batch, chưa đọc trước stream), `QueryIndexesTest` (EXPLAIN và rollback giữ dữ liệu). Cả ba được chạy fail trên source cũ trước khi sửa. Các test thanh toán, hạng, hai tiến trình, hoàn tiền và QR hiện có phải tiếp tục pass.

## Sửa lỗi tìm thấy khi kiểm tra toàn dự án

- Database dev chưa áp dụng 6 migration đã có trong code, khiến chọn ghế trả 500 vì thiếu bảng combo. Sau khi người dùng cho phép rõ ràng, đã sao lưu DB rồi áp dụng đúng danh sách migration; số dòng của users/movies/showtimes/bookings/payments/tickets trước/sau giữ nguyên. Các test và fixture vẫn chỉ chạy trên `cinema_test`, không seed hoặc reset `cinema_db`.
- Thanh toán thành công thiếu bản ghi Nhật ký. `payment.success` giờ được ghi trong transaction, retry không tạo trùng; không lưu payload hoặc khóa xác thực trong properties. Có test mô phỏng, webhook, amount bị từ chối và link biên lai.
- URL SePay cũ có thể trả 500 khi cấu hình cổng bị gỡ. Giờ quay lại checkout kèm lỗi thân thiện, không đổi payment hoặc xác nhận đơn.
- `ApplicationPagesTest` mở các trang công khai, khách hàng, thanh toán, index/create/edit quản trị, báo cáo và staff với quan hệ thật. Các test cũ về quyền, nhập liệu, callback, hoàn tiền, ghế đôi, QR và race phải tiếp tục xanh.

Đã kiểm tra trình duyệt trên `cinema_test`: ghế + combo + voucher giữ qua đăng nhập; MoMo mô phỏng thành công; đổi 10 điểm, thanh toán 85.000đ, thưởng 8 điểm; hạng Bạc/307 tích lũy và 8 điểm còn lại; canvas QR hiện; dashboard doanh thu 85.000đ; tra cứu và check-in toàn đơn, nút check-in biến mất sau xử lý. Camera vật lý, SMTP và giao dịch ngân hàng thật không nằm trong lượt mô phỏng này.
