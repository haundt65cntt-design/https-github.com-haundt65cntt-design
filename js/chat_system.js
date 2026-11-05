// Enhanced Chat System JavaScript
class ChatSystem {
    constructor() {
        this.currentChatId = null;
        this.currentChatType = null;
        this.messageInterval = null;
        this.recording = false;
        this.mediaRecorder = null;
        this.audioChunks = [];
        
        this.initializeEventListeners();
        this.startMessagePolling();
    }
    
    initializeEventListeners() {
        // Friend/group selection is handled by navbar.php on user_chatting.php
        if (typeof window.loadMessages !== 'function') {
            document.querySelectorAll('.friend-item').forEach(item => {
                item.addEventListener('click', () => this.selectChat(item, 'user'));
            });
            
            document.querySelectorAll('.group-item').forEach(item => {
                item.addEventListener('click', () => this.selectChat(item, 'group'));
            });
        }
        
        // Message sending - user_chatting.php provides its own handlers
        if (typeof window.loadMessages !== 'function') {
            const sendBtn = document.getElementById('sendBtn');
            const messageInput = document.getElementById('messageInput');
            
            if (sendBtn) {
                sendBtn.addEventListener('click', () => this.sendMessage());
            }
            
            if (messageInput) {
                messageInput.addEventListener('keypress', (e) => {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        this.sendMessage();
                    }
                });
            }
        }
        
        // File upload - handled by user_chatting.php
        // Removed duplicate event handler to prevent double file dialog
        
        // Voice recording
        if (typeof window.loadMessages !== 'function') {
            const voiceBtn = document.getElementById('voiceBtn');
            if (voiceBtn) {
                voiceBtn.addEventListener('click', () => this.toggleVoiceRecording());
            }
        }
        
        // Create group functionality is handled by navbar.php
        
        // Add friend functionality
        this.initializeAddFriendModal();
        
        // Message actions
        this.initializeMessageActions();
    }
    
    selectChat(element, type) {
        // Remove active class from all items
        document.querySelectorAll('.friend-item, .group-item').forEach(el => el.classList.remove('active'));
        element.classList.add('active');
        
        const chatId = element.dataset[type === 'user' ? 'userId' : 'groupId'];
        const chatName = element.dataset[type === 'user' ? 'username' : 'groupName'];
        
        if (type === 'user') {
            this.currentChatId = chatId;
            this.currentChatType = type;
            
            this.updateChatInterface(chatName, type);
            this.loadMessages(chatId, type);
        } else if (type === 'group') {
            // For groups, navigate to group page
            window.location.href = `group.php?id=${chatId}`;
        }
    }
    
    updateChatInterface(name, type) {
        const chatTitle = document.getElementById('chatTitle');
        const chatInput = document.getElementById('chatInput');
        
        if (chatTitle) {
            chatTitle.innerHTML = '<h5><i class="fas fa-' + (type === 'user' ? 'user' : 'layer-group') + '"></i> ' + name + '</h5><small class="text-muted">' + (type === 'user' ? 'Private chat' : 'Group chat') + '</small>';
        }
        
        if (chatInput) {
            chatInput.style.display = 'block';
        }
    }
    
    async loadMessages(chatId, type, options) {
        options = options || {};
        if (typeof window.loadMessages === 'function') {
            window.loadMessages(chatId, type, options);
            this.currentChatId = chatId;
            this.currentChatType = type;
            return;
        }
        try {
            let url;
            if (type === 'user') {
                url = 'ajax/chat_handler.php?action=get_messages&receiver_id=' + chatId;
            } else if (type === 'group') {
                url = 'ajax/chat_handler.php?action=get_messages&group_id=' + chatId;
            } else {
                console.error('Invalid chat type:', type);
                return;
            }
            
            const response = await fetch(url);
            const data = await response.json();
            
            if (data.success) {
                this.displayMessages(data.messages, options);
            } else {
                console.error('Error loading messages:', data.error);
            }
        } catch (error) {
            console.error('Error loading messages:', error);
        }
    }
    
    displayMessages(messages, options) {
        options = options || {};
        const messagesContainer = document.getElementById('chatMessages');
        if (!messagesContainer) return;
        
        const preserveScroll = options.silent &&
            (messagesContainer.scrollHeight - messagesContainer.scrollTop - messagesContainer.clientHeight > 80);
        const scrollTop = preserveScroll ? messagesContainer.scrollTop : 0;
        
        messagesContainer.innerHTML = '';
        
        if (messages.length === 0) {
            messagesContainer.innerHTML = '<div class="text-center text-muted"><p>No messages yet. Start the conversation!</p></div>';
            return;
        }
        
        messages.forEach(message => {
            const messageElement = this.createMessageElement(message);
            messagesContainer.appendChild(messageElement);
        });
        
        if (preserveScroll) {
            messagesContainer.scrollTop = scrollTop;
        } else {
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }
    }
    
    createMessageElement(message) {
        const messageDiv = document.createElement('div');
        const isOwnMessage = message.sender_id == window.currentUserId;
        messageDiv.className = 'message ' + (isOwnMessage ? 'sent' : 'received');
        messageDiv.dataset.messageId = message.id;
        
        let content = '';
        
        // Reply indicator
        if (message.reply_to) {
            content += '<div class="reply-indicator bg-light p-2 mb-2 rounded">';
            content += '<small><i class="fas fa-reply"></i> Replying to ' + message.reply_username + '</small>';
            content += '<div class="text-muted">' + (message.reply_message || 'Message') + '</div>';
            content += '</div>';
        }
        
        // Forwarded indicator
        if (message.forwarded_from) {
            content += '<div class="forward-indicator bg-info text-white p-1 mb-2 rounded">';
            content += '<small><i class="fas fa-share"></i> Forwarded from ' + message.forward_username + '</small>';
            content += '</div>';
        }
        
        // Message content
        if (message.message_type === 'text') {
            content += '<div class="message-content">' + this.formatMessage(message.message) + '</div>';
        } else if (message.message_type === 'image') {
            // Fix image path - check both possible locations
            let imagePath = message.file_path;
            if (imagePath.startsWith('uploads/') && !imagePath.startsWith('ajax/')) {
                imagePath = 'ajax/' + imagePath;
            }
            content += '<div class="message-content"><img src="' + imagePath + '" class="img-fluid rounded" style="max-width: 300px;"></div>';
        } else if (message.message_type === 'file') {
            content += '<div class="message-content">';
            content += '<div class="file-message d-flex align-items-center">';
            content += '<i class="fas fa-file fa-2x me-2"></i>';
            content += '<div>';
            content += '<div><strong>' + (message.file_name || 'File') + '</strong></div>';
            // Fix file path - check both possible locations
            let filePath = message.file_path;
            if (filePath.startsWith('uploads/') && !filePath.startsWith('ajax/')) {
                filePath = 'ajax/' + filePath;
            }
            content += '<a href="' + filePath + '" download class="btn btn-sm btn-outline-primary">Download</a>';
            content += '</div>';
            content += '</div>';
            content += '</div>';
        } else if (message.message_type === 'voice') {
            content += '<div class="message-content">';
            content += '<div class="voice-message d-flex align-items-center">';
            content += '<i class="fas fa-microphone fa-2x me-2"></i>';
            content += '<audio controls>';
            // Fix voice file path - files are stored in ajax/uploads/voice/ but DB has uploads/voice/
            let audioPath = message.file_path;
            if (audioPath.startsWith('uploads/voice/')) {
                audioPath = 'ajax/' + audioPath;
            }
            content += '<source src="' + audioPath + '" type="audio/webm">';
            content += '</audio>';
            content += '</div>';
            content += '</div>';
        }
        
        // Message metadata
        const timeAgo = this.getTimeAgo(message.created_at);
        const editedText = message.is_edited ? ' <small>(edited)</small>' : '';
        
        content += '<div class="message-meta">';
        content += '<small class="message-time">' + timeAgo + editedText + '</small>';
        content += '<div class="message-actions">';
        content += this.createMessageActions(message);
        content += '</div>';
        content += '</div>';
        
        messageDiv.innerHTML = content;
        return messageDiv;
    }
    
    createMessageActions(message) {
        const isOwnMessage = message.sender_id == window.currentUserId;
        const canEdit = isOwnMessage && this.canEditMessage(message.created_at);
        
        let actions = '';
        
        actions += '<button class="btn btn-sm btn-outline-secondary" onclick="chatSystem.replyToMessage(' + message.id + ')" title="Reply">';
        actions += '<i class="fas fa-reply"></i>';
        actions += '</button>';
        
        actions += '<button class="btn btn-sm btn-outline-secondary" onclick="chatSystem.forwardMessage(' + message.id + ')" title="Forward">';
        actions += '<i class="fas fa-share"></i>';
        actions += '</button>';
        
        if (canEdit) {
            actions += '<button class="btn btn-sm btn-outline-secondary" onclick="chatSystem.editMessage(' + message.id + ')" title="Edit">';
            actions += '<i class="fas fa-edit"></i>';
            actions += '</button>';
        }
        
        if (isOwnMessage && canEdit) {
            actions += '<button class="btn btn-sm btn-outline-danger" onclick="chatSystem.deleteMessage(' + message.id + ')" title="Delete">';
            actions += '<i class="fas fa-trash"></i>';
            actions += '</button>';
        }
        
        actions += '<button class="btn btn-sm btn-outline-secondary" onclick="chatSystem.pinMessage(' + message.id + ')" title="Pin">';
        actions += '<i class="fas fa-thumbtack"></i>';
        actions += '</button>';
        
        return actions;
    }
    
    formatMessage(message) {
        // Handle @mentions
        message = message.replace(/@(\w+)/g, '<span class="mention">@$1</span>');
        
        // Handle URLs
        message = message.replace(/(https?:\/\/[^\s]+)/g, '<a href="$1" target="_blank">$1</a>');
        
        // Handle line breaks
        message = message.replace(/\n/g, '<br>');
        
        return message;
    }
    
    getTimeAgo(timestamp) {
        const now = new Date();
        const messageTime = new Date(timestamp);
        const diffInSeconds = Math.floor((now - messageTime) / 1000);
        
        if (diffInSeconds < 60) return 'Just now';
        if (diffInSeconds < 3600) return Math.floor(diffInSeconds / 60) + 'm ago';
        if (diffInSeconds < 86400) return Math.floor(diffInSeconds / 3600) + 'h ago';
        return messageTime.toLocaleDateString();
    }
    
    canEditMessage(createdAt) {
        const now = new Date();
        const messageTime = new Date(createdAt);
        const diffInMinutes = (now - messageTime) / (1000 * 60);
        return diffInMinutes <= 2;
    }
    
    async sendMessage() {
        const messageInput = document.getElementById('messageInput');
        if (!messageInput) return;
        
        const message = messageInput.value.trim();
        
        if (!message || !this.currentChatId) return;
        
        try {
            const data = {
                message: message,
                message_type: 'text'
            };
            
            if (this.currentChatType === 'user') {
                data.receiver_id = this.currentChatId;
            } else {
                data.group_id = this.currentChatId;
            }
            
            const response = await fetch('ajax/chat_handler.php?action=send_message', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (result.success) {
                messageInput.value = '';
                this.loadMessages(this.currentChatId, this.currentChatType);
            } else {
                console.error('Error sending message:', result.error);
            }
        } catch (error) {
            console.error('Error sending message:', error);
        }
    }
    
    async replyToMessage(messageId) {
        const messageInput = document.getElementById('messageInput');
        if (!messageInput) return;
        
        messageInput.focus();
        messageInput.placeholder = 'Type your reply...';
        
        // Store reply context
        this.replyingTo = messageId;
        
        // Add visual indicator
        const replyIndicator = document.createElement('div');
        replyIndicator.className = 'reply-indicator bg-light p-2 mb-2 rounded';
        replyIndicator.innerHTML = '<small><i class="fas fa-reply"></i> Replying to message</small><button class="btn btn-sm btn-outline-secondary ms-2" onclick="chatSystem.cancelReply()">Cancel</button>';
        
        const chatInput = document.getElementById('chatInput');
        if (chatInput) {
            chatInput.insertBefore(replyIndicator, chatInput.firstChild);
        }
    }
    
    cancelReply() {
        this.replyingTo = null;
        const messageInput = document.getElementById('messageInput');
        if (messageInput) {
            messageInput.placeholder = 'Type a message...';
        }
        const replyIndicator = document.querySelector('.reply-indicator');
        if (replyIndicator) {
            replyIndicator.remove();
        }
    }
    
    async editMessage(messageId) {
        const messageElement = document.querySelector('[data-message-id="' + messageId + '"]');
        if (!messageElement) return;
        
        const messageContent = messageElement.querySelector('.message-content');
        if (!messageContent) return;
        
        const currentText = messageContent.textContent || '';
        const input = document.createElement('input');
        input.type = 'text';
        input.value = currentText;
        input.className = 'form-control';
        
        messageContent.innerHTML = '';
        messageContent.appendChild(input);
        input.focus();
        input.select();
        
        const saveEdit = async () => {
            const newText = input.value.trim();
            if (newText && newText !== currentText) {
                try {
                    const response = await fetch('ajax/chat_handler.php?action=edit_message', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            message_id: messageId,
                            message: newText
                        })
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        this.loadMessages(this.currentChatId, this.currentChatType);
                    }
                } catch (error) {
                    console.error('Error editing message:', error);
                }
            } else {
                if (messageContent) {
                    messageContent.textContent = currentText;
                }
            }
        };
        
        input.addEventListener('blur', saveEdit);
        input.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                saveEdit();
            } else if (e.key === 'Escape') {
                if (messageContent) {
                    messageContent.textContent = currentText;
                }
            }
        });
    }
    
    async deleteMessage(messageId) {
        if (!confirm('Are you sure you want to delete this message?')) return;
        
        try {
            const response = await fetch('ajax/chat_handler.php?action=delete_message', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    message_id: messageId
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.loadMessages(this.currentChatId, this.currentChatType);
            }
        } catch (error) {
            console.error('Error deleting message:', error);
        }
    }
    
    async pinMessage(messageId) {
        try {
            const response = await fetch('ajax/chat_handler.php?action=pin_message', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    message_id: messageId
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.loadMessages(this.currentChatId, this.currentChatType);
            }
        } catch (error) {
            console.error('Error pinning message:', error);
        }
    }
    
    async forwardMessage(messageId) {
        // Show user selection modal for forwarding
        const modal = this.createUserSelectionModal('Forward Message', async (selectedUserId) => {
            try {
                const data = {
                    message_id: messageId,
                    receiver_id: selectedUserId
                };
                
                const response = await fetch('ajax/chat_handler.php?action=forward_message', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('Message forwarded successfully!');
                }
            } catch (error) {
                console.error('Error forwarding message:', error);
            }
        });
        
        document.body.appendChild(modal);
        new bootstrap.Modal(modal).show();
    }
    
    openFileDialog() {
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/*,application/pdf,text/*';
        
        input.onchange = async (e) => {
            const file = e.target.files[0];
            if (!file) return;
            
            const formData = new FormData();
            formData.append('file', file);
            
            try {
                const response = await fetch('ajax/chat_handler.php?action=upload_file', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    await this.sendFileMessage(result.file_path, file.type, file.name);
                }
            } catch (error) {
                console.error('Error uploading file:', error);
            }
        };
        
        input.click();
    }
    
    async sendFileMessage(filePath, fileType, fileName) {
        const messageType = fileType.startsWith('image/') ? 'image' : 'file';
        
        const data = {
            message: fileName,
            message_type: messageType,
            file_path: filePath
        };
        
        if (this.currentChatType === 'user') {
            data.receiver_id = this.currentChatId;
        } else {
            data.group_id = this.currentChatId;
        }
        
        try {
            const response = await fetch('ajax/chat_handler.php?action=send_message', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.loadMessages(this.currentChatId, this.currentChatType);
            }
        } catch (error) {
            console.error('Error sending file:', error);
        }
    }
    
    async toggleVoiceRecording() {
        if (!this.recording) {
            await this.startVoiceRecording();
        } else {
            this.stopVoiceRecording();
        }
    }
    
    async startVoiceRecording() {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            this.mediaRecorder = new MediaRecorder(stream);
            this.audioChunks = [];
            
            this.mediaRecorder.ondataavailable = (event) => {
                this.audioChunks.push(event.data);
            };
            
            this.mediaRecorder.onstop = async () => {
                const audioBlob = new Blob(this.audioChunks, { type: 'audio/webm' });
                const reader = new FileReader();
                reader.onload = async () => {
                    const base64 = reader.result.split(',')[1];
                    await this.sendVoiceMessage(base64);
                };
                reader.readAsDataURL(audioBlob);
            };
            
            this.mediaRecorder.start();
            this.recording = true;
            
            const voiceBtn = document.getElementById('voiceBtn');
            if (voiceBtn) {
                voiceBtn.innerHTML = '<i class="fas fa-stop"></i>';
                voiceBtn.classList.add('btn-danger');
            }
            
        } catch (error) {
            console.error('Error starting voice recording:', error);
            alert('Could not access microphone. Please check permissions.');
        }
    }
    
    stopVoiceRecording() {
        if (this.mediaRecorder && this.recording) {
            this.mediaRecorder.stop();
            this.recording = false;
            
            const voiceBtn = document.getElementById('voiceBtn');
            if (voiceBtn) {
                voiceBtn.innerHTML = '<i class="fas fa-microphone"></i>';
                voiceBtn.classList.remove('btn-danger');
            }
        }
    }
    
    async sendVoiceMessage(audioData) {
        try {
            const response = await fetch('ajax/chat_handler.php?action=record_voice', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    audio_data: audioData
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                await this.sendFileMessage(result.file_path, 'audio/webm', 'Voice Message');
            }
        } catch (error) {
            console.error('Error sending voice message:', error);
        }
    }
    
    // Create group functionality is handled by navbar.php
    
    async respondToRequest(requestId, action) {
        try {
            const response = await fetch('ajax/chat_handler.php?action=respond_request', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    request_id: requestId,
                    action: action
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                location.reload(); // Refresh to update friend list
            }
        } catch (error) {
            console.error('Error responding to request:', error);
        }
    }
    
    createUserSelectionModal(title, callback) {
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.innerHTML = '<div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">' + title + '</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="mb-3"><input type="text" class="form-control" id="userSearch" placeholder="Search users..."></div><div id="userList"><div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading users...</div></div></div></div></div></div>';
        
        // Load users
        this.loadUsersForSelection(modal, callback);
        
        return modal;
    }
    
    async loadUsersForSelection(modal, callback) {
        try {
            const response = await fetch('ajax/chat_handler.php?action=get_users');
            const result = await response.json();
            
            if (result.success) {
                const userList = modal.querySelector('#userList');
                if (userList) {
                    userList.innerHTML = '';
                    
                    result.users.forEach(user => {
                        const userItem = document.createElement('div');
                        userItem.className = 'user-item p-2 border-bottom cursor-pointer';
                        userItem.innerHTML = '<div class="d-flex align-items-center"><span class="status-indicator status-' + user.status + '"></span><div><strong>' + user.full_name + '</strong><br><small class="text-muted">@' + user.username + '</small></div></div>';
                        
                        userItem.addEventListener('click', () => {
                            callback(user.id);
                            const modalInstance = bootstrap.Modal.getInstance(modal);
                            if (modalInstance) {
                                modalInstance.hide();
                            }
                            modal.remove();
                        });
                        
                        userList.appendChild(userItem);
                    });
                }
            }
        } catch (error) {
            console.error('Error loading users:', error);
        }
    }
    
    initializeAddFriendModal() {
        // Add friend button functionality
        const addFriendBtn = document.createElement('button');
        addFriendBtn.className = 'btn btn-primary btn-sm w-100 mb-2';
        addFriendBtn.innerHTML = '<i class="fas fa-user-plus"></i> Add Friend';
        addFriendBtn.addEventListener('click', () => this.showAddFriendModal());
        
        const friendsTab = document.querySelector('#friends');
        if (friendsTab) {
            friendsTab.insertBefore(addFriendBtn, friendsTab.firstChild);
        }
    }
    
    showAddFriendModal() {
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.innerHTML = '<div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Add Friend</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="mb-3"><label for="friendUsername" class="form-label">Username</label><input type="text" class="form-control" id="friendUsername" placeholder="Enter username"></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-primary" id="sendFriendRequest">Send Request</button></div></div></div></div>';
        
        document.body.appendChild(modal);
        
        const sendBtn = modal.querySelector('#sendFriendRequest');
        if (sendBtn) {
            sendBtn.addEventListener('click', async () => {
                const usernameInput = modal.querySelector('#friendUsername');
                if (!usernameInput) return;
                
                const username = usernameInput.value.trim();
                
                if (username) {
                    try {
                        const response = await fetch('ajax/chat_handler.php?action=add_friend', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                username: username
                            })
                        });
                        
                        const result = await response.json();
                        
                        if (result.success) {
                            alert('Friend request sent!');
                            const modalInstance = bootstrap.Modal.getInstance(modal);
                            if (modalInstance) {
                                modalInstance.hide();
                            }
                            modal.remove();
                        } else {
                            alert('Error: ' + result.error);
                        }
                    } catch (error) {
                        console.error('Error sending friend request:', error);
                    }
                }
            });
        }
        
        new bootstrap.Modal(modal).show();
    }
    
    initializeMessageActions() {
        // Add CSS for message actions
        const style = document.createElement('style');
        style.textContent = '.message-actions { opacity: 0; transition: opacity 0.2s; } .message:hover .message-actions { opacity: 1; } .mention { background-color: rgba(59, 130, 246, 0.12); color: #2563eb; padding: 2px 4px; border-radius: 3px; font-weight: bold; } .reply-indicator { border-left: 3px solid #3b82f6; } .forward-indicator { border-left: 3px solid #28a745; } .user-item:hover { background-color: rgba(59, 130, 246, 0.06); } .cursor-pointer { cursor: pointer; }';
        document.head.appendChild(style);
    }
    
    startMessagePolling() {
        // Poll for new messages every 5 seconds (silent refresh preserves scroll position)
        this.messageInterval = setInterval(() => {
            const chatId = window.currentChatId || this.currentChatId;
            const chatType = window.currentChatType || this.currentChatType;
            if (chatId && chatType) {
                this.loadMessages(chatId, chatType, { silent: true });
            }
        }, 5000);
    }
    
    stopMessagePolling() {
        if (this.messageInterval) {
            clearInterval(this.messageInterval);
        }
    }
}

// Initialize chat system when page loads
let chatSystem;
document.addEventListener('DOMContentLoaded', () => {
    chatSystem = new ChatSystem();
});

// Clean up when page unloads
window.addEventListener('beforeunload', () => {
    if (chatSystem) {
        chatSystem.stopMessagePolling();
    }
});