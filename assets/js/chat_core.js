/**
 * Cetusg Plus - Core Chat System (Ultra Premium)
 */

const ChatCore = {
    state: {
        isOpen: false,
        activeUserId: null,
        currentUserId: null,
        isInitialLoad: true
    },

    init(currentUserId) {
        this.state.currentUserId = currentUserId;
        this.applyVisibility();
        setInterval(() => this.sync(), 5000);
        this.sync(true);
        this.bindEvents();
        this.renderEmojiPicker();
    },

    renderEmojiPicker() {
        const emojis = ['😀', '😁', '😂', '🤣', '😃', '😄', '😅', '😆', '😉', '😊', '😋', '😎', '😍', '😘', '🥰', '😗', '😙', '😚', '☺️', '🙂', '🤗', '🤩', '🤔', '🤨', '😐', '😑', '😶', '🙄', '😏', '😣', '😥', '😮', '🤐', '😯', '😪', '😫', '😴', '😌', '😛', '😜', '😝', '🤤', '😒', '😓', '😔', '😕', '🙃', '🤑', '😲', '☹️', '🙁', '😖', '😞', '😟', '😤', '😢', '😭', '😦', '😧', '😨', '😩', '🤯', '😬', '😰', '😱', '🥵', '🥶', '😳', '🤪', '😵', '😡', '😠', '🤬', '😷', '🤒', '🤕', '🤢', '🤮', '🤧', '😇', '🥳', '🥺', '🤠', '🤡', '🥳', '🥴', '🧐', '🤓', '😈', '👿', '👹', '👺', '💀', '👻', '👽', '🤖', '💩', '😺', '😸', '😹', '😻', '😼', '😽', '🙀', '😿', '😾'];
        const picker = document.createElement('div');
        picker.id = 'chatEmojiPicker';
        picker.style = 'display:none; position:absolute; bottom:120px; right:20px; width:250px; height:200px; background:white; border:1px solid #ddd; border-radius:15px; padding:10px; overflow-y:auto; z-index:10002; box-shadow:0 5px 20px rgba(0,0,0,0.2);';
        
        picker.innerHTML = emojis.map(e => `<span onclick="ChatCore.insertEmoji('${e}')" style="cursor:pointer; font-size:1.5rem; padding:5px; display:inline-block;">${e}</span>`).join('');
        document.body.appendChild(picker);
    },

    toggleEmojiPicker() {
        const picker = document.getElementById('chatEmojiPicker');
        if (picker) picker.style.display = picker.style.display === 'none' ? 'block' : 'none';
    },

    insertEmoji(emoji) {
        const input = document.getElementById('chatInput');
        if (input) {
            input.value += emoji;
            input.focus();
            this.toggleEmojiPicker();
        }
    },

    bindEvents() {
        const input = document.getElementById('chatInput');
        if (input) {
            input.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') this.sendMessage();
            });
        }
    },

    toggle() {
        this.state.isOpen = !this.state.isOpen;
        this.applyVisibility();
        if (this.state.isOpen) this.sync(true);
    },

    applyVisibility() {
        const panel = document.getElementById('chatPanel');
        const launcher = document.getElementById('chatPinnedBar');
        if (panel) panel.style.display = this.state.isOpen ? 'flex' : 'none';
        if (launcher) launcher.style.display = 'flex'; // Garantir que o botão flutuante sempre apareça
    },

    async sync(force = false) {
        if (this.state.isOpen || force) {
            await this.loadUserList();
            if (this.state.activeUserId) await this.loadMessages();
        } else {
            const r = await fetch('chat_api.php?action=total_unread');
            const d = await r.json();
            this.updateBadge(d.count);
        }
    },

    async loadUserList() {
        const r = await fetch('chat_api.php?action=list_users');
        const users = await r.json();
        const container = document.getElementById('chatUserList');
        if (!container) return;

        container.innerHTML = users.map(u => {
            const isOnline = parseInt(u.is_online);
            const unread = parseInt(u.unread_count) || 0;
            return `
                <div onclick="ChatCore.openChatWith('${u.id}', '${u.name}', '${u.avatar_url}', ${isOnline})" 
                     class="chat-user-item">
                    <div class="chat-avatar-wrapper">
                        ${u.avatar_url ? `<img src="${u.avatar_url}" class="chat-avatar-img">` : `<div class="chat-avatar-placeholder">${u.name.substring(0, 1).toUpperCase()}</div>`}
                        <div class="status-indicator ${isOnline ? 'online' : 'offline'}"></div>
                    </div>
                    <div style="flex: 1;">
                        <div class="chat-user-name">${u.name}</div>
                        <div style="font-size: 0.7rem; color: #64748b;">${isOnline ? 'Online' : 'Offline'}</div>
                    </div>
                    ${unread > 0 ? `<span class="chat-unread-badge" style="background:#ef4444; color:white; padding:2px 8px; border-radius:10px; font-size:10px;">${unread}</span>` : ''}
                </div>
            `;
        }).join('');
    },

    async openChatWith(id, name, avatar, isOnline) {
        this.state.activeUserId = id;
        const panel = document.getElementById('chatPanel');
        panel.classList.add('convo-active');

        document.getElementById('convoUserName').innerText = name;
        document.getElementById('convoUserStatus').innerText = isOnline ? 'Online' : 'Offline';
        
        const avatarDiv = document.getElementById('convoUserAvatar');
        avatarDiv.innerHTML = avatar ? `<img src="${avatar}" style="width:100%;height:100%;border-radius:50%;object-fit:cover;">` : name.substring(0, 1).toUpperCase();

        await this.loadMessages();
    },

    backToList() {
        document.getElementById('chatPanel').classList.remove('convo-active');
        this.state.activeUserId = null;
    },

    async loadMessages() {
        if (!this.state.activeUserId) return;
        const r = await fetch(`chat_api.php?action=get_messages&other_id=${this.state.activeUserId}`);
        const msgs = await r.json();

        const container = document.getElementById('chatMessages');
        container.innerHTML = msgs.map(m => {
            const isSent = m.sender_id === this.state.currentUserId;
            let content = m.content;
            
            if (m.type === 'image') {
                content = `<img src="uploads/chat_files/${m.content}" style="max-width:100%; border-radius:12px; cursor:pointer;" onclick="window.open('uploads/chat_files/${m.content}')">`;
            } else if (m.type === 'file') {
                content = `<div class="chat-file-msg"><i class="fa-solid fa-file"></i> <a href="uploads/chat_files/${m.content}" target="_blank">${m.content}</a></div>`;
            }

            const time = m.time_formatted || '';
            const isRead = parseInt(m.is_read) === 1;
            const ticks = isSent ? `<span class="message-ticks" style="color: ${isRead ? '#34b7f1' : '#94a3b8'}; margin-left: 5px; font-size: 0.7rem;">
                <i class="fa-solid fa-check-double"></i>
            </span>` : '';

            return `
                <div class="message-wrapper ${isSent ? 'sent' : 'received'}" style="display:flex; flex-direction:column; align-items: ${isSent ? 'flex-end' : 'flex-start'}; margin-bottom: 10px; position: relative;">
                    <div class="message-bubble ${isSent ? 'msg-sent' : 'msg-received'}" style="position: relative; padding-bottom: 20px; min-width: 80px;">
                        ${content}
                        <div class="message-meta" style="position: absolute; bottom: 4px; right: 8px; display: flex; align-items: center; font-size: 0.65rem; opacity: 0.8;">
                            <span class="message-time">${time}</span>
                            ${ticks}
                        </div>
                    </div>
                </div>
            `;
        }).join('');
        container.scrollTop = container.scrollHeight;
    },

    async sendMessage() {
        const input = document.getElementById('chatInput');
        const text = input.value.trim();
        if (!text || !this.state.activeUserId) return;

        const fd = new FormData();
        fd.append('receiver_id', this.state.activeUserId);
        fd.append('content', text);
        
        input.value = '';
        await fetch('chat_api.php?action=send', { method: 'POST', body: fd });
        this.loadMessages();
    },

    triggerFileUpload() {
        document.getElementById('chatFileAnchor').click();
    },

    async handleFileUpload(input) {
        if (!input.files[0] || !this.state.activeUserId) return;
        const fd = new FormData();
        fd.append('receiver_id', this.state.activeUserId);
        fd.append('file', input.files[0]);
        
        await fetch('chat_api.php?action=upload_file', { method: 'POST', body: fd });
        this.loadMessages();
        input.value = '';
    },

    updateBadge(count) {
        const badge = document.getElementById('minimizedBadge');
        if (badge) {
            badge.innerText = count;
            badge.style.display = count > 0 ? 'flex' : 'none';
        }
    }
};
