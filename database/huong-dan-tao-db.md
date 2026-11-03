# HƯỚNG DẪN TẠO DATABASE TRÊN XAMPP

Áp dụng cho dự án **Chatting System – PHP thuần + MySQL**, sử dụng database tên `chatting\_system` và bộ SQL đã tinh gọn còn 15 bảng.

> \*\*Chuẩn bị:\*\* Giải nén gói mã nguồn dự án hoặc gói database. Các file SQL cần dùng nằm trong thư mục `database/` ở bên trong gói. Không đổi tên các file SQL.

## 1\. Tạo database trên phpMyAdmin

1. Mở **XAMPP Control Panel**.
2. Nhấn **Start** ở hai dịch vụ **Apache** và **MySQL**. Đảm bảo cả hai đang chạy.
3. Mở trình duyệt và truy cập: [http://localhost/phpmyadmin](http://localhost/phpmyadmin).
4. Trên phpMyAdmin, chọn tab **Import**.
5. Nhấn **Choose File** và chọn file:

   `database/create\_database.sql`

6. Kéo xuống cuối trang và nhấn **Import** hoặc **Go**.
7. Sau khi chạy thành công, database `chatting\_system` sẽ xuất hiện ở cột bên trái. Nhấn vào tên database đó để chọn.

File `create\_database.sql` đã thiết lập mặc định `utf8mb4`; không cần tìm hoặc chọn riêng tùy chọn này trong giao diện Import.

> \*\*Lưu ý:\*\* Hướng dẫn này dành cho cài đặt database mới. Nếu bạn đã có database `chatting\_system` đang sử dụng và đã có đủ 15 bảng, không tạo lại database và không import lại toàn bộ các file bảng. Hãy sao lưu trước mọi thao tác thay đổi dữ liệu.

## 2\. Import các file SQL theo thứ tự

Sau khi đã chọn database **`chatting\_system`** ở cột bên trái:

1. Chọn tab **Import**.
2. Nhấn **Choose File**.
3. Chọn file SQL theo đúng thứ tự trong danh sách bên dưới.
4. Nhấn **Import** hoặc **Go** để chạy file.
5. Lặp lại các bước trên cho file tiếp theo cho đến hết danh sách.

Tất cả các file trong danh sách đều nằm tại thư mục:

`database/tables/`

Import lần lượt theo thứ tự sau:

1. `users.sql`
2. `friends.sql`
3. `groups.sql`
4. `group\_members.sql`
5. `messages.sql`
6. `message\_edits.sql`
7. `message\_tags.sql`
8. `user\_mentions.sql`
9. `pinned\_chats.sql`
10. `pinned\_messages.sql`
11. `voice\_calls.sql`
12. `voice\_call\_ice.sql`
13. `social\_posts.sql`
14. `social\_post\_reactions.sql`
15. `social\_post\_comments.sql`

Sau khi import xong, nhấn **Structure** của database `chatting\_system` để kiểm tra danh sách bảng.

## 3\. Import dữ liệu mẫu

Sau khi tạo xong 15 bảng, tiếp tục import hai file dữ liệu mẫu. Vẫn giữ database `chatting\_system` được chọn, vào tab **Import**, chọn file rồi nhấn **Import/Go**.

**Import file thứ nhất:**

`database/seeds/default\_records.sql`

**Import file thứ hai:**

`database/seeds/demo\_data.sql`

Import theo đúng thứ tự trên. Không cần tạo dữ liệu thủ công trong từng bảng.

Mở trang đăng nhập của dự án, thường là:

[http://localhost/chatting-system/login.php](http://localhost/chatting-system/login.php)

