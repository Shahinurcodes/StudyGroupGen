<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once 'config.php';

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];
$user_name = $_SESSION['user_name'];
$group_id = isset($_GET['group_id']) ? intval($_GET['group_id']) : 0;

if ($group_id <= 0) {
    header('Location: dashboard.php');
    exit();
}

// Verify user has access to this group
try {
    $conn = getConnection();
    
    if ($user_type === 'student') {
        $stmt = $conn->prepare("
            SELECT g.*, c.course_code, c.course_name
            FROM group_members gm
            JOIN groups g ON gm.group_id = g.id
            JOIN courses c ON g.course_id = c.id
            WHERE gm.student_id = ? AND g.id = ? AND g.status = 'active'
        ");
        $stmt->bind_param("ii", $user_id, $group_id);
    } else {
        $stmt = $conn->prepare("
            SELECT g.*, c.course_code, c.course_name
            FROM groups g
            JOIN courses c ON g.course_id = c.id
            WHERE g.faculty_mentor_id = ? AND g.id = ?
        ");
        $stmt->bind_param("ii", $user_id, $group_id);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        header('Location: dashboard.php');
        exit();
    }
    
    $group = $result->fetch_assoc();
    $stmt->close();
    
    // Get group members
    $stmt = $conn->prepare("
        SELECT s.id, s.full_name, s.student_id, gm.role
        FROM group_members gm
        JOIN students s ON gm.student_id = s.id
        WHERE gm.group_id = ?
        ORDER BY gm.role DESC, s.full_name
    ");
    $stmt->bind_param("i", $group_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $members = [];
    while ($row = $result->fetch_assoc()) {
        $members[] = $row;
    }
    $stmt->close();
    
    $conn->close();
} catch (Exception $e) {
    header('Location: dashboard.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WebSocket Chat | <?php echo htmlspecialchars($group['group_name']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <script src="https://cdn.socket.io/4.7.2/socket.io.min.js"></script>
    <style>
        .chat-container {
            display: flex;
            height: calc(100vh - 120px);
            background: var(--white);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-lg);
        }
        
        .chat-sidebar {
            width: 300px;
            background: var(--gray-50);
            border-right: 1px solid var(--gray-200);
            display: flex;
            flex-direction: column;
        }
        
        .chat-main {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .chat-header {
            padding: var(--spacing-lg);
            border-bottom: 1px solid var(--gray-200);
            background: var(--white);
        }
        
        .chat-title {
            font-size: var(--font-size-xl);
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: var(--spacing-xs);
        }
        
        .chat-subtitle {
            color: var(--gray-600);
            font-size: var(--font-size-sm);
        }
        
        .connection-status {
            display: flex;
            align-items: center;
            gap: var(--spacing-xs);
            font-size: var(--font-size-xs);
            margin-top: var(--spacing-xs);
        }
        
        .status-indicator {
            width: 8px;
            height: 8px;
            border-radius: var(--radius-full);
            background: var(--gray-400);
        }
        
        .status-indicator.connected {
            background: var(--success);
        }
        
        .status-indicator.connecting {
            background: var(--warning);
            animation: pulse 1.5s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .chat-messages {
            flex: 1;
            padding: var(--spacing-lg);
            overflow-y: auto;
            background: var(--gray-50);
        }
        
        .message {
            margin-bottom: var(--spacing-md);
            display: flex;
            align-items: flex-start;
            gap: var(--spacing-md);
            animation: fadeInUp 0.3s ease-out;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .message.own {
            flex-direction: row-reverse;
        }
        
        .message-avatar {
            width: 40px;
            height: 40px;
            border-radius: var(--radius-full);
            background: var(--primary);
            display: flex;
            justify-content: center;
            align-items: center;
            color: var(--white);
            font-weight: 600;
            font-size: var(--font-size-sm);
        }
        
        .message.own .message-avatar {
            background: var(--success);
        }
        
        .message-content {
            max-width: 70%;
        }
        
        .message-header {
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
            margin-bottom: var(--spacing-xs);
        }
        
        .message-sender {
            font-weight: 600;
            color: var(--gray-900);
            font-size: var(--font-size-sm);
        }
        
        .message-time {
            color: var(--gray-500);
            font-size: var(--font-size-xs);
        }
        
        .message-text {
            background: var(--white);
            padding: var(--spacing-md);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            color: var(--gray-800);
            line-height: 1.5;
            word-wrap: break-word;
        }
        
        .message.own .message-text {
            background: var(--success);
            color: var(--white);
        }
        
        .message.typing {
            font-style: italic;
            color: var(--gray-500);
        }
        
        .chat-input {
            padding: var(--spacing-lg);
            border-top: 1px solid var(--gray-200);
            background: var(--white);
        }
        
        .chat-input-form {
            display: flex;
            gap: var(--spacing-md);
        }
        
        .chat-input-field {
            flex: 1;
            padding: var(--spacing-md);
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-lg);
            font-size: var(--font-size-base);
            resize: none;
            min-height: 50px;
            max-height: 120px;
        }
        
        .chat-input-field:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-light);
        }
        
        .chat-send-btn {
            padding: var(--spacing-md) var(--spacing-lg);
            background: var(--primary);
            color: var(--white);
            border: none;
            border-radius: var(--radius-lg);
            cursor: pointer;
            font-size: var(--font-size-base);
            transition: all var(--transition-base);
        }
        
        .chat-send-btn:hover {
            background: var(--primary-dark);
        }
        
        .chat-send-btn:disabled {
            background: var(--gray-400);
            cursor: not-allowed;
        }
        
        .members-list {
            flex: 1;
            padding: var(--spacing-lg);
            overflow-y: auto;
        }
        
        .members-header {
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: var(--spacing-md);
            padding-bottom: var(--spacing-sm);
            border-bottom: 1px solid var(--gray-200);
        }
        
        .member-item {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
            padding: var(--spacing-sm);
            border-radius: var(--radius-md);
            margin-bottom: var(--spacing-sm);
        }
        
        .member-item:hover {
            background: var(--gray-100);
        }
        
        .member-avatar {
            width: 35px;
            height: 35px;
            border-radius: var(--radius-full);
            background: var(--primary);
            display: flex;
            justify-content: center;
            align-items: center;
            color: var(--white);
            font-weight: 600;
            font-size: var(--font-size-sm);
        }
        
        .member-info {
            flex: 1;
        }
        
        .member-name {
            font-weight: 600;
            color: var(--gray-900);
            font-size: var(--font-size-sm);
        }
        
        .member-role {
            color: var(--gray-600);
            font-size: var(--font-size-xs);
        }
        
        .online-indicator {
            width: 10px;
            height: 10px;
            border-radius: var(--radius-full);
            background: var(--success);
        }
        
        .typing-indicator {
            padding: var(--spacing-sm) var(--spacing-md);
            color: var(--gray-500);
            font-style: italic;
            font-size: var(--font-size-sm);
        }
        
        .file-upload-area {
            border: 2px dashed var(--gray-300);
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg);
            text-align: center;
            margin-bottom: var(--spacing-md);
            cursor: pointer;
            transition: all var(--transition-base);
        }
        
        .file-upload-area:hover {
            border-color: var(--primary);
            background: var(--primary-light);
        }
        
        .file-upload-area.dragover {
            border-color: var(--primary);
            background: var(--primary-light);
        }
        
        .message-attachment {
            background: var(--gray-100);
            padding: var(--spacing-sm);
            border-radius: var(--radius-md);
            margin-top: var(--spacing-xs);
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
        }
        
        .attachment-icon {
            color: var(--primary);
        }
        
        .attachment-name {
            flex: 1;
            font-size: var(--font-size-sm);
        }
        
        .attachment-download {
            color: var(--primary);
            cursor: pointer;
        }
        
        @media (max-width: 768px) {
            .chat-container {
                flex-direction: column;
                height: calc(100vh - 80px);
            }
            
            .chat-sidebar {
                width: 100%;
                height: 200px;
                border-right: none;
                border-bottom: 1px solid var(--gray-200);
            }
            
            .message-content {
                max-width: 85%;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <a href="<?php echo $_SESSION['user_type'] === 'student' ? 'dashboard.php' : 'faculty_dashboard.php'; ?>" class="logo">
                <div class="logo-icon">
                    <i class="fas fa-arrow-left"></i>
                </div>
                <span class="logo-text">Back to Dashboard</span>
            </a>
            <div class="user-menu">
                <div class="user-details">
                    <span class="user-name"><?php echo htmlspecialchars($user_name); ?></span>
                    <span class="user-role"><?php echo ucfirst($user_type); ?></span>
                </div>
                <a href="logout.php" class="btn btn-outline">Logout</a>
            </div>
        </div>
    </header>

    <!-- Chat Container -->
    <div class="container" style="padding: var(--spacing-lg) 0;">
        <div class="chat-container">
            <!-- Chat Sidebar -->
            <div class="chat-sidebar">
                <div class="chat-header">
                    <div class="chat-title"><?php echo htmlspecialchars($group['group_name']); ?></div>
                    <div class="chat-subtitle"><?php echo htmlspecialchars($group['course_code'] . ' - ' . $group['course_name']); ?></div>
                    <div class="connection-status">
                        <div class="status-indicator" id="connectionStatus"></div>
                        <span id="connectionText">Connecting...</span>
                    </div>
                </div>
                
                <div class="members-list">
                    <div class="members-header">
                        <i class="fas fa-users"></i> Members (<?php echo count($members); ?>)
                    </div>
                    <?php foreach ($members as $member): ?>
                        <div class="member-item" data-user-id="<?php echo $member['id']; ?>">
                            <div class="member-avatar">
                                <?php echo strtoupper(substr($member['full_name'], 0, 2)); ?>
                            </div>
                            <div class="member-info">
                                <div class="member-name"><?php echo htmlspecialchars($member['full_name']); ?></div>
                                <div class="member-role"><?php echo ucfirst($member['role']); ?></div>
                            </div>
                            <div class="online-indicator" id="online-<?php echo $member['id']; ?>"></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Chat Main -->
            <div class="chat-main">
                <div class="chat-messages" id="chatMessages">
                    <!-- Messages will be loaded here -->
                </div>
                
                <div class="typing-indicator" id="typingIndicator" style="display: none;">
                    Someone is typing...
                </div>
                
                <div class="chat-input">
                    <div class="file-upload-area" id="fileUploadArea">
                        <i class="fas fa-cloud-upload-alt" style="font-size: 2rem; color: var(--gray-400); margin-bottom: var(--spacing-sm);"></i>
                        <p>Drag and drop files here or click to upload</p>
                        <input type="file" id="fileInput" multiple style="display: none;">
                    </div>
                    
                    <form class="chat-input-form" id="chatForm">
                        <textarea 
                            class="chat-input-field" 
                            id="messageInput" 
                            placeholder="Type your message..." 
                            rows="1"
                            maxlength="1000"
                        ></textarea>
                        <button type="submit" class="chat-send-btn" id="sendBtn">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // WebSocket connection
        const socket = io('ws://localhost:3000', {
            query: {
                userId: <?php echo $user_id; ?>,
                userType: '<?php echo $user_type; ?>',
                groupId: <?php echo $group_id; ?>,
                userName: '<?php echo addslashes($user_name); ?>'
            }
        });
        
        let isTyping = false;
        let typingTimeout;
        
        // Connection status
        socket.on('connect', function() {
            updateConnectionStatus('connected', 'Connected');
        });
        
        socket.on('disconnect', function() {
            updateConnectionStatus('disconnected', 'Disconnected');
        });
        
        socket.on('connect_error', function() {
            updateConnectionStatus('error', 'Connection Error');
        });
        
        function updateConnectionStatus(status, text) {
            const indicator = document.getElementById('connectionStatus');
            const textElement = document.getElementById('connectionText');
            
            indicator.className = 'status-indicator ' + status;
            textElement.textContent = text;
        }
        
        // Message handling
        socket.on('message', function(data) {
            addMessageToChat(data);
        });
        
        socket.on('typing', function(data) {
            if (data.userId !== <?php echo $user_id; ?>) {
                showTypingIndicator(data.userName);
            }
        });
        
        socket.on('stop_typing', function(data) {
            if (data.userId !== <?php echo $user_id; ?>) {
                hideTypingIndicator();
            }
        });
        
        socket.on('user_joined', function(data) {
            addSystemMessage(`${data.userName} joined the chat`);
            updateOnlineStatus(data.userId, true);
        });
        
        socket.on('user_left', function(data) {
            addSystemMessage(`${data.userName} left the chat`);
            updateOnlineStatus(data.userId, false);
        });
        
        // Add message to chat
        function addMessageToChat(message) {
            const messagesContainer = document.getElementById('chatMessages');
            const messageDiv = document.createElement('div');
            const isOwnMessage = message.sender_id == <?php echo $user_id; ?>;
            
            messageDiv.className = `message ${isOwnMessage ? 'own' : ''}`;
            
            let attachmentHtml = '';
            if (message.attachment) {
                attachmentHtml = `
                    <div class="message-attachment">
                        <i class="fas fa-paperclip attachment-icon"></i>
                        <span class="attachment-name">${escapeHTML(message.attachment.name)}</span>
                        <i class="fas fa-download attachment-download" onclick="downloadAttachment('${message.attachment.url}', '${message.attachment.name}')"></i>
                    </div>
                `;
            }
            
            messageDiv.innerHTML = `
                <div class="message-avatar">
                    ${message.sender_name.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2)}
                </div>
                <div class="message-content">
                    <div class="message-header">
                        <span class="message-sender">${escapeHTML(message.sender_name)}</span>
                        <span class="message-time">${formatTime(message.sent_at)}</span>
                    </div>
                    <div class="message-text">${escapeHTML(message.content)}</div>
                    ${attachmentHtml}
                </div>
            `;
            
            messagesContainer.appendChild(messageDiv);
            scrollToBottom();
        }
        
        // System messages
        function addSystemMessage(text) {
            const messagesContainer = document.getElementById('chatMessages');
            const messageDiv = document.createElement('div');
            messageDiv.className = 'message typing';
            messageDiv.innerHTML = `<div class="message-text" style="text-align: center; background: var(--gray-100); color: var(--gray-600);">${escapeHTML(text)}</div>`;
            messagesContainer.appendChild(messageDiv);
            scrollToBottom();
        }
        
        // Typing indicators
        function showTypingIndicator(userName) {
            const indicator = document.getElementById('typingIndicator');
            indicator.textContent = `${userName} is typing...`;
            indicator.style.display = 'block';
        }
        
        function hideTypingIndicator() {
            const indicator = document.getElementById('typingIndicator');
            indicator.style.display = 'none';
        }
        
        // Online status
        function updateOnlineStatus(userId, isOnline) {
            const indicator = document.getElementById(`online-${userId}`);
            if (indicator) {
                indicator.style.background = isOnline ? 'var(--success)' : 'var(--gray-400)';
            }
        }
        
        // Handle form submission
        document.getElementById('chatForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const messageInput = document.getElementById('messageInput');
            const message = messageInput.value.trim();
            
            if (message) {
                sendMessage(message);
                messageInput.value = '';
                messageInput.style.height = 'auto';
            }
        });
        
        // Send message
        function sendMessage(message) {
            const sendBtn = document.getElementById('sendBtn');
            sendBtn.disabled = true;
            sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            
            socket.emit('send_message', {
                group_id: <?php echo $group_id; ?>,
                content: message
            }, function(response) {
                sendBtn.disabled = false;
                sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i>';
                
                if (!response.success) {
                    alert('Failed to send message: ' + (response.error || 'Unknown error'));
                }
            });
        }
        
        // Typing detection
        document.getElementById('messageInput').addEventListener('input', function() {
            if (!isTyping) {
                isTyping = true;
                socket.emit('typing', { group_id: <?php echo $group_id; ?> });
            }
            
            clearTimeout(typingTimeout);
            typingTimeout = setTimeout(() => {
                isTyping = false;
                socket.emit('stop_typing', { group_id: <?php echo $group_id; ?> });
            }, 1000);
        });
        
        // File upload
        const fileUploadArea = document.getElementById('fileUploadArea');
        const fileInput = document.getElementById('fileInput');
        
        fileUploadArea.addEventListener('click', () => fileInput.click());
        
        fileUploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            fileUploadArea.classList.add('dragover');
        });
        
        fileUploadArea.addEventListener('dragleave', () => {
            fileUploadArea.classList.remove('dragover');
        });
        
        fileUploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            fileUploadArea.classList.remove('dragover');
            const files = e.dataTransfer.files;
            handleFiles(files);
        });
        
        fileInput.addEventListener('change', (e) => {
            handleFiles(e.target.files);
        });
        
        function handleFiles(files) {
            Array.from(files).forEach(file => {
                if (file.size > 10 * 1024 * 1024) { // 10MB limit
                    alert('File too large. Maximum size is 10MB.');
                    return;
                }
                
                uploadFile(file);
            });
        }
        
        function uploadFile(file) {
            const formData = new FormData();
            formData.append('file', file);
            formData.append('group_id', <?php echo $group_id; ?>);
            
            fetch('upload_file.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    socket.emit('send_message', {
                        group_id: <?php echo $group_id; ?>,
                        content: `Shared file: ${file.name}`,
                        attachment: {
                            name: file.name,
                            url: data.file_url,
                            size: file.size
                        }
                    });
                } else {
                    alert('Failed to upload file: ' + data.error);
                }
            })
            .catch(error => {
                alert('Upload failed: ' + error.message);
            });
        }
        
        function downloadAttachment(url, filename) {
            const link = document.createElement('a');
            link.href = url;
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        
        // Auto-resize textarea
        document.getElementById('messageInput').addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 120) + 'px';
        });
        
        // Scroll to bottom
        function scrollToBottom() {
            const messagesContainer = document.getElementById('chatMessages');
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }
        
        // Utility functions
        function escapeHTML(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function formatTime(timestamp) {
            const date = new Date(timestamp);
            const now = new Date();
            const diff = now - date;
            
            if (diff < 60000) { // Less than 1 minute
                return 'Just now';
            } else if (diff < 3600000) { // Less than 1 hour
                return Math.floor(diff / 60000) + 'm ago';
            } else if (diff < 86400000) { // Less than 1 day
                return Math.floor(diff / 3600000) + 'h ago';
            } else {
                return date.toLocaleDateString();
            }
        }
        
        // Load initial messages
        fetch('get_chat_history.php?group_id=<?php echo $group_id; ?>')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    data.messages.forEach(message => {
                        addMessageToChat(message);
                    });
                    scrollToBottom();
                }
            });
    </script>
</body>
</html> 