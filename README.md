# 📱 Đồ án: Xây dựng mạng xã hội tương tự Zalo

**Khoa Công nghệ Thông tin - Trường Đại học Nha Trang**

## 👥 Thành viên thực hiện (Lớp 65.CNTT-1)
- **Ngô Hoàng** - 65131101
- **Ngô Đình Trí Hậu** - 65130925

## 🎯 1. Tổng quan dự án
Dự án hướng tới việc xây dựng một nền tảng mạng xã hội thu nhỏ có các chức năng giao tiếp cốt lõi tương tự như Zalo. Người dùng có thể tạo tài khoản, kết bạn, nhắn tin thời gian thực (1-1), chia sẻ trạng thái lên bảng tin (feed) và tham gia vào các chủ đề thảo luận chung trên diễn đàn.

## 💻 2. Công nghệ sử dụng
- **Backend:** PHP thuần (Xử lý logic, phiên đăng nhập, kết nối cơ sở dữ liệu).
- **Database:** Hệ quản trị cơ sở dữ liệu quan hệ MySQL.
- **Frontend & Tương tác:** HTML5, CSS3, JavaScript. Sử dụng kỹ thuật AJAX Polling để tải tin nhắn mới mà không cần nạp lại trang (mô phỏng realtime).
- **Quản lý mã nguồn:** Git và nền tảng GitHub theo chuẩn quy trình mã nguồn mở (Open Source Workflow).

## ⚙️ 3. Phân hệ cơ sở dữ liệu
Hệ thống sử dụng cơ sở dữ liệu `chatting_system`. Database được thiết kế với tổng cộng 18 bảng, chia làm 3 phân hệ chính:
1. **Người dùng & Chat:** `users`, `friendships`, `messages`, `conversations` - Quản lý tài khoản, trạng thái online/offline, danh sách bạn bè và lưu trữ nội dung tin nhắn 1-1.
2. **Mạng xã hội:** `social_posts`, `social_likes`, `social_post_comments` - Lưu trữ bài viết trên bảng tin (feed), số lượng thả tim, và nội dung bình luận của người dùng.
3. **Diễn đàn (Forum):** `forum_categories`, `forum_topics`, `forum_posts` - Tổ chức các chuyên mục thảo luận chung, quản lý các chủ đề và các bài đăng phản hồi bên trong chủ đề.

## 🚀 4. Chức năng chính (Các hàm xử lý)
- **Xác thực:** `registerUser`, `loginUser`, `checkUserOnlineStatus`
- **Kết bạn:** `sendFriendRequest`, `acceptFriendRequest`
- **Nhắn tin (Chat realtime):** `sendMessage`, `fetchMessages`
- **Mạng xã hội:** `createPost`

## 🔗 5. Liên kết đính kèm
- **Link GitHub (Public Repo):** https://github.com/hoangngo1404/NgoHoang-TriHau-Do_an_PTPMMNM
- **Video Demo:** [Google Drive](https://drive.google.com/file/d/1dFw37yHr_oMQ5jq1upeCFidHkQJUkWaf/view?usp=sharing)
