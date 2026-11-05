# HƯỚNG DẪN TẠO DATABASE TRÊN XAMPP

Áp dụng cho dự án **Chatting System – PHP thuần + MySQL**, sử dụng database tên `chatting_system`.

> **Chuẩn bị:** Giải nén gói mã nguồn dự án. Các file SQL cần dùng nằm trong thư mục `database/`. Không đổi tên các file SQL.

## 1. Tạo database trên phpMyAdmin

1. Mở **XAMPP Control Panel**.
2. Nhấn **Start** ở hai dịch vụ **Apache** và **MySQL**. Đảm bảo cả hai đang chạy.
3. Mở trình duyệt và truy cập [http://localhost/phpmyadmin](http://localhost/phpmyadmin) (hoặc thêm cổng nếu Apache chạy cổng khác 80, ví dụ [http://localhost:81/phpmyadmin](http://localhost:81/phpmyadmin)).
4. Trên phpMyAdmin, chọn tab **Import**.
5. Nhấn **Choose File** và chọn file `database/create_database.sql`.
6. Kéo xuống cuối trang và nhấn **Import** hoặc **Go**.
7. Sau khi chạy thành công, database `chatting_system` sẽ xuất hiện ở cột bên trái. Nhấn vào tên database đó để chọn.

File `create_database.sql` đã thiết lập mặc định `utf8mb4`; không cần chọn riêng tùy chọn này trong giao diện Import.

> **Lưu ý:** Hướng dẫn này dành cho cài đặt database mới. Nếu bạn đã có database `chatting_system` đang sử dụng, hãy sao lưu trước mọi thao tác thay đổi dữ liệu. Không import lại toàn bộ các file bảng trên database đang có dữ liệu nếu chưa kiểm tra cấu trúc hiện tại.

## 2. Import các file SQL cấu trúc bảng theo thứ tự

Sau khi đã chọn database **`chatting_system`** ở cột bên trái:

1. Chọn tab **Import**.
2. Nhấn **Choose File**.
3. Chọn file SQL theo đúng thứ tự trong danh sách bên dưới.
4. Nhấn **Import** hoặc **Go** để chạy file.
5. Lặp lại các bước trên cho file tiếp theo.

Tất cả các file trong danh sách đều nằm tại thư mục `database/tables/`. Import lần lượt theo thứ tự sau để các bảng được tham chiếu đã tồn tại:

1. `users.sql`
2. `friends.sql`
3. `groups.sql`
4. `group_members.sql`
5. `messages.sql`
6. `message_edits.sql`
7. `message_tags.sql`
8. `user_mentions.sql`
9. `pinned_chats.sql`
10. `pinned_messages.sql`
11. `voice_calls.sql`
12. `voice_call_ice.sql`
13. `social_posts.sql`
14. `social_post_reactions.sql`
15. `social_post_comments.sql`
16. `message_likes.sql`
17. `message_reactions.sql`
18. `forum_categories.sql`
19. `forum_posts.sql`
20. `forum_comments.sql`
21. `forum_post_media.sql`
22. `forum_replies.sql`

Sau khi import xong, nhấn **Structure** của database `chatting_system` để kiểm tra các bảng đã được tạo.

## 3. Import dữ liệu mẫu

Sau khi tạo xong các bảng, tiếp tục import hai file dữ liệu mẫu theo thứ tự dưới đây. Vẫn giữ database `chatting_system` được chọn, vào tab **Import**, chọn file rồi nhấn **Import/Go**.

**Import file thứ nhất:** `database/seeds/default_records.sql`

**Import file thứ hai:** `database/seeds/demo_data.sql`

> **Chú ý:** Phải import các bảng forum ở Bước 2 trước khi chạy `default_records.sql`, vì file này thêm dữ liệu mặc định vào `forum_categories`.

## 4. Truy cập ứng dụng

Đặt thư mục dự án trong `htdocs` với tên `chatting-system` (hoặc dùng đúng tên thư mục dự án của bạn).

Mở trình duyệt và truy cập:

[http://localhost/chatting-system/login.php](http://localhost/chatting-system/login.php)

Nếu Apache chạy cổng 81, dùng [http://localhost:81/chatting-system/login.php](http://localhost:81/chatting-system/login.php).
