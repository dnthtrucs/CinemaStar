# Kế hoạch kiểm thử CinemaStar

## Kiểm thử tự động

```powershell
php artisan test
```

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
