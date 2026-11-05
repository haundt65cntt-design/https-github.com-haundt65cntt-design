# ERD database sau khi tinh gọn

```mermaid
erDiagram
    USERS ||--o{ FRIENDS : user_id
    USERS ||--o{ FRIENDS : friend_id
    USERS ||--o{ GROUPS : creates
    GROUPS ||--o{ GROUP_MEMBERS : contains
    USERS ||--o{ GROUP_MEMBERS : joins
    USERS ||--o{ MESSAGES : sends
    USERS o|--o{ MESSAGES : receives
    GROUPS o|--o{ MESSAGES : contains
    MESSAGES o|--o{ MESSAGES : replies
    MESSAGES o|--o{ MESSAGES : forwards
    MESSAGES ||--o{ MESSAGE_EDITS : edit_history
    MESSAGES ||--o{ MESSAGE_TAGS : tags
    USERS ||--o{ MESSAGE_TAGS : tagged_user
    MESSAGES ||--o{ USER_MENTIONS : mentions
    USERS ||--o{ USER_MENTIONS : mentioned_user
    USERS ||--o{ PINNED_CHATS : pins_chat
    MESSAGES ||--o{ PINNED_MESSAGES : pinned
    USERS ||--o{ PINNED_MESSAGES : pins_message
    USERS ||--o{ VOICE_CALLS : caller
    USERS ||--o{ VOICE_CALLS : callee
    USERS ||--o{ VOICE_CALL_ICE : sends_candidate
    USERS ||--o{ SOCIAL_POSTS : publishes
    SOCIAL_POSTS ||--o{ SOCIAL_POST_REACTIONS : receives
    USERS ||--o{ SOCIAL_POST_REACTIONS : reacts
    SOCIAL_POSTS ||--o{ SOCIAL_POST_COMMENTS : has
    USERS ||--o{ SOCIAL_POST_COMMENTS : comments

    USERS { INT id PK }
    FRIENDS { INT id PK INT user_id FK INT friend_id FK }
    GROUPS { INT id PK INT created_by FK }
    GROUP_MEMBERS { INT id PK INT group_id FK INT user_id FK }
    MESSAGES { INT id PK INT sender_id FK INT receiver_id FK INT group_id FK INT reply_to FK INT forwarded_from FK }
    MESSAGE_EDITS { INT id PK INT message_id FK }
    MESSAGE_TAGS { INT id PK INT message_id FK INT tagged_user_id FK }
    USER_MENTIONS { INT id PK INT message_id FK INT mentioned_user_id FK }
    PINNED_CHATS { INT id PK INT user_id FK INT chat_id }
    PINNED_MESSAGES { INT id PK INT message_id FK INT user_id FK }
    VOICE_CALLS { INT id PK INT caller_id FK INT callee_id FK }
    VOICE_CALL_ICE { INT id PK INT user_id FK VARCHAR room_id }
    SOCIAL_POSTS { INT id PK INT user_id FK }
    SOCIAL_POST_REACTIONS { INT id PK INT post_id FK INT user_id FK }
    SOCIAL_POST_COMMENTS { INT id PK INT post_id FK INT user_id FK }
```

Lưu ý: `PINNED_CHATS.chat_id` có thể là ID người dùng hoặc ID nhóm tùy `chat_type`, do đó không phải khóa ngoại trực tiếp. `VOICE_CALL_ICE.room_id` liên kết logic với `VOICE_CALLS.room_id` nhưng schema không khai báo FK.
