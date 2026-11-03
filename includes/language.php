<?php
// Language support system
class LanguageManager {
    private $currentLanguage;
    private $translations;
    
    public function __construct($language = 'en') {
        $this->currentLanguage = $language;
        $this->loadTranslations();
    }
    
    private function loadTranslations() {
        $this->translations = [
             'en' => [
                 'login' => 'Login',
                 'register' => 'Register',
                 'username' => 'Username',
                 'password' => 'Password',
                 'email' => 'Email',
                 'full_name' => 'Full Name',
                 'login_button' => 'Login',
                 'register_button' => 'Register',
                 'logout' => 'Logout',
                 'chat' => 'Chat',
                 'friends' => 'Friends',
                 'groups' => 'Groups',
                 'forum' => 'Forum',
                 'settings' => 'Settings',
                 'send_message' => 'Send Message',
                 'type_message' => 'Type a message...',
                 'add_friend' => 'Add Friend',
                 'friend_requests' => 'Friend Requests',
                 'create_group' => 'Create Group',
                 'join_group' => 'Join Group',
                 'block_user' => 'Block User',
                 'unblock_user' => 'Unblock User',
                 'reply' => 'Reply',
                 'forward' => 'Forward',
                 'edit' => 'Edit',
                 'delete' => 'Delete',
                 'like' => 'Like',
                 'pin' => 'Pin',
                 'unpin' => 'Unpin',
                 'voice_message' => 'Voice Message',
                 'send_file' => 'Send File',
                 'mention_user' => 'Mention User',
                 'online' => 'Online',
                 'offline' => 'Offline',
                 'away' => 'Away',
                 'last_seen' => 'Last seen',
                 'change_language' => 'Change Language',
                 'english' => 'English',
                 'chinese' => 'Chinese',
                 'vietnamese' => 'Vietnamese'
             ],
             'vi' => [
                 'login' => 'Đăng nhập',
                 'register' => 'Đăng ký',
                 'username' => 'Tên đăng nhập',
                 'password' => 'Mật khẩu',
                 'email' => 'Email',
                 'full_name' => 'Họ và tên',
                 'login_button' => 'Đăng nhập',
                 'register_button' => 'Đăng ký',
                 'logout' => 'Đăng xuất',
                 'chat' => 'Tin nhắn',
                 'friends' => 'Bạn bè',
                 'groups' => 'Nhóm',
                 'forum' => 'Diễn đàn',
                 'settings' => 'Cài đặt',
                 'send_message' => 'Gửi tin nhắn',
                 'type_message' => 'Nhập tin nhắn...',
                 'add_friend' => 'Kết bạn',
                 'friend_requests' => 'Lời mời kết bạn',
                 'create_group' => 'Tạo nhóm',
                 'join_group' => 'Tham gia nhóm',
                 'block_user' => 'Chặn người dùng',
                 'unblock_user' => 'Bỏ chặn người dùng',
                 'reply' => 'Trả lời',
                 'forward' => 'Chuyển tiếp',
                 'edit' => 'Chỉnh sửa',
                 'delete' => 'Xóa',
                 'like' => 'Thích',
                 'pin' => 'Ghim',
                 'unpin' => 'Bỏ ghim',
                 'voice_message' => 'Tin nhắn thoại',
                 'send_file' => 'Gửi tệp',
                 'mention_user' => 'Nhắc đến người dùng',
                 'online' => 'Đang hoạt động',
                 'offline' => 'Ngoại tuyến',
                 'away' => 'Vắng mặt',
                 'last_seen' => 'Truy cập lần cuối',
                 'change_language' => 'Đổi ngôn ngữ',
                 'english' => 'Tiếng Anh',
                 'chinese' => 'Tiếng Trung',
                 'vietnamese' => 'Tiếng Việt'
             ]
        ];
    }
    
    public function get($key) {
        return isset($this->translations[$this->currentLanguage][$key]) 
            ? $this->translations[$this->currentLanguage][$key] 
            : $key;
    }
    
    public function setLanguage($language) {
        $this->currentLanguage = $language;
        $_SESSION['language'] = $language;
    }
    
    public function getCurrentLanguage() {
        return $this->currentLanguage;
    }
    
    public function getAvailableLanguages() {
        return array_keys($this->translations);
    }
}

// Initialize language manager
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$language = isset($_SESSION['language']) ? $_SESSION['language'] : 'en';
// Migrate previously selected Malay sessions to Vietnamese.
if ($language === 'ms') { $language = 'vi'; $_SESSION['language'] = 'vi'; }
if (!in_array($language, ['en', 'vi'], true)) { $language = 'en'; $_SESSION['language'] = 'en'; }
$lang = new LanguageManager($language);

// Handle language change
if (isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'vi'], true)) {
    $lang->setLanguage($_GET['lang']);
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}
?>
