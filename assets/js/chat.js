/**
 * Helpify Chat System Logic
 */

let currentChatBookingId = null;
let currentChatReceiverId = null;
let lastMessageId = 0;
let pollingInterval = null;

function openChat(bookingId, receiverId, receiverName) {
    currentChatBookingId = bookingId;
    currentChatReceiverId = receiverId;
    lastMessageId = 0;

    document.getElementById('chatReceiverName').textContent = receiverName;
    document.getElementById('chatOverlay').style.display = 'flex';
    document.getElementById('chatMessages').innerHTML = '<div style="text-align:center; color:#94a3b8; font-size:0.8rem; margin:1rem 0;">Loading messages...</div>';

    fetchMessages();

    // Start polling
    if (pollingInterval) clearInterval(pollingInterval);
    pollingInterval = setInterval(pollMessages, 3000);
}

function closeChat() {
    document.getElementById('chatOverlay').style.display = 'none';
    if (pollingInterval) clearInterval(pollingInterval);
    currentChatBookingId = null;
}

function fetchMessages() {
    fetch(`api/chat_action.php?action=fetch&booking_id=${currentChatBookingId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const container = document.getElementById('chatMessages');
                container.innerHTML = '';
                data.messages.forEach(msg => {
                    appendMessage(msg);
                    lastMessageId = Math.max(lastMessageId, msg.id);
                });
                scrollToBottom();
            }
        });
}

function pollMessages() {
    if (!currentChatBookingId) return;
    fetch(`api/chat_action.php?action=poll&booking_id=${currentChatBookingId}&last_id=${lastMessageId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.messages.length > 0) {
                data.messages.forEach(msg => {
                    appendMessage(msg);
                    lastMessageId = Math.max(lastMessageId, msg.id);
                });
                scrollToBottom();
            }
        });
}

function sendMessage() {
    const input = document.getElementById('chatInput');
    const message = input.value.trim();
    if (!message || !currentChatBookingId) return;

    const formData = new FormData();
    formData.append('action', 'send');
    formData.append('booking_id', currentChatBookingId);
    formData.append('receiver_id', currentChatReceiverId);
    formData.append('message', message);

    input.value = '';

    fetch('api/chat_action.php', {
        method: 'POST',
        body: formData
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                pollMessages(); // Immediately poll for the sent message
            }
        });
}

function appendMessage(msg) {
    const container = document.getElementById('chatMessages');
    const isSent = msg.sender_id == currentUserId; // currentUserId must be defined in the main page

    const div = document.createElement('div');
    div.className = `message-bubble ${isSent ? 'sent' : 'received'}`;

    const time = new Date(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

    div.innerHTML = `
        ${msg.message}
        <span class="message-time">${time}</span>
    `;

    container.appendChild(div);
}

function scrollToBottom() {
    const container = document.getElementById('chatMessages');
    container.scrollTop = container.scrollHeight;
}

// Handle Enter key in chat input
document.addEventListener('DOMContentLoaded', () => {
    const input = document.getElementById('chatInput');
    if (input) {
        input.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') sendMessage();
        });
    }
});
