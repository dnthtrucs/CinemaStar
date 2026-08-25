CINEMASTAR - NANG CAP DO AN

Chuc nang moi
- Dashboard doanh thu 6 thang va tai bao cao CSV.
- Voucher: tao, bat/tat va ap dung khi chon ghe.
- Diem thanh vien: 1 diem moi 10.000 dong thanh toan thanh cong.
- Tai khoan Nhan vien: chi dung trang check-in ve QR/ma ve.
- Giu ghe va chong dat trung: giu nguyen, da co san trong ban truoc.

CACH CAI DAT
1. Sao luu thu muc C:\xampp\htdocs\Cinema.
2. Dung php artisan serve va npm run dev.
3. Giai nen goi nay vao C:\xampp\htdocs\Cinema va chon Replace.
4. Mo Terminal trong C:\xampp\htdocs\Cinema, chay:
   php artisan optimize:clear
   php artisan migrate
   php artisan serve

TAI KHOAN NHAN VIEN
Dang nhap admin > Quan ly tai khoan > doi vai tro mot tai khoan thanh Nhan vien.
Nhan vien dang nhap va mo: http://127.0.0.1:8000/staff/tickets/check-in

TAO VOUCHER
Admin > Voucher > tao ma. Khach nhap ma o trang chon ghe.

BAO CAO
Admin > Tong quan > Tai bao cao CSV, hoac Admin > Bao cao CSV.

LUU Y: Chay php artisan migrate, KHONG dung migrate:fresh neu khong muon xoa du lieu cu.
