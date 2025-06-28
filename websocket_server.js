const express = require('express');
const http = require('http');
const socketIo = require('socket.io');
const mysql = require('mysql2/promise');
const cors = require('cors');
const path = require('path');

const app = express();
const server = http.createServer(app);
const io = socketIo(server, {
    cors: {
        origin: "*",
        methods: ["GET", "POST"]
    }
});

// Database configuration
const dbConfig = {
    host: 'localhost',
    user: 'root',
    password: '',
    database: 'studygroupgen'
};

// Store active connections
const activeConnections = new Map();
const typingUsers = new Map();

// Database connection pool
let pool;

async function initializeDatabase() {
    try {
        pool = mysql.createPool(dbConfig);
        console.log('Database connected successfully');
    } catch (error) {
        console.error('Database connection failed:', error);
        process.exit(1);
    }
}

// Socket.IO event handlers
io.on('connection', (socket) => {
    console.log('User connected:', socket.id);
    
    const { userId, userType, groupId, userName } = socket.handshake.query;
    
    // Store connection info
    activeConnections.set(socket.id, {
        userId: parseInt(userId),
        userType,
        groupId: parseInt(groupId),
        userName,
        socket
    });
    
    // Join group room
    socket.join(`group_${groupId}`);
    
    // Notify others that user joined
    socket.to(`group_${groupId}`).emit('user_joined', {
        userId: parseInt(userId),
        userName
    });
    
    // Update online status
    updateOnlineStatus(groupId, parseInt(userId), true);
    
    // Handle message sending
    socket.on('send_message', async (data, callback) => {
        try {
            const connection = await pool.getConnection();
            
            // Insert message into database
            const [result] = await connection.execute(
                'INSERT INTO messages (group_id, sender_id, sender_type, content, sent_at) VALUES (?, ?, ?, ?, NOW())',
                [data.group_id, userId, userType, data.content]
            );
            
            // Get sender name
            let senderName;
            if (userType === 'student') {
                const [studentResult] = await connection.execute(
                    'SELECT full_name FROM students WHERE id = ?',
                    [userId]
                );
                senderName = studentResult[0]?.full_name || 'Unknown';
            } else {
                const [facultyResult] = await connection.execute(
                    'SELECT CONCAT(first_name, " ", last_name) as full_name FROM faculty WHERE id = ?',
                    [userId]
                );
                senderName = facultyResult[0]?.full_name || 'Unknown';
            }
            
            connection.release();
            
            // Prepare message object
            const message = {
                id: result.insertId,
                group_id: data.group_id,
                sender_id: parseInt(userId),
                sender_type: userType,
                sender_name: senderName,
                content: data.content,
                attachment: data.attachment || null,
                sent_at: new Date().toISOString()
            };
            
            // Broadcast to group
            io.to(`group_${data.group_id}`).emit('message', message);
            
            // Send success callback
            if (callback) {
                callback({ success: true, message_id: result.insertId });
            }
            
        } catch (error) {
            console.error('Error sending message:', error);
            if (callback) {
                callback({ success: false, error: error.message });
            }
        }
    });
    
    // Handle typing indicators
    socket.on('typing', (data) => {
        typingUsers.set(socket.id, {
            userId: parseInt(userId),
            userName,
            groupId: data.group_id
        });
        
        socket.to(`group_${data.group_id}`).emit('typing', {
            userId: parseInt(userId),
            userName
        });
    });
    
    socket.on('stop_typing', (data) => {
        typingUsers.delete(socket.id);
        
        socket.to(`group_${data.group_id}`).emit('stop_typing', {
            userId: parseInt(userId),
            userName
        });
    });
    
    // Handle file sharing
    socket.on('share_file', async (data, callback) => {
        try {
            const connection = await pool.getConnection();
            
            // Insert message with file attachment
            const [result] = await connection.execute(
                'INSERT INTO messages (group_id, sender_id, sender_type, content, sent_at) VALUES (?, ?, ?, ?, NOW())',
                [data.group_id, userId, userType, `Shared file: ${data.filename}`]
            );
            
            // Get sender name
            let senderName;
            if (userType === 'student') {
                const [studentResult] = await connection.execute(
                    'SELECT full_name FROM students WHERE id = ?',
                    [userId]
                );
                senderName = studentResult[0]?.full_name || 'Unknown';
            } else {
                const [facultyResult] = await connection.execute(
                    'SELECT CONCAT(first_name, " ", last_name) as full_name FROM faculty WHERE id = ?',
                    [userId]
                );
                senderName = facultyResult[0]?.full_name || 'Unknown';
            }
            
            connection.release();
            
            // Prepare message object with attachment
            const message = {
                id: result.insertId,
                group_id: data.group_id,
                sender_id: parseInt(userId),
                sender_type: userType,
                sender_name: senderName,
                content: `Shared file: ${data.filename}`,
                attachment: {
                    name: data.filename,
                    url: data.file_url,
                    size: data.file_size
                },
                sent_at: new Date().toISOString()
            };
            
            // Broadcast to group
            io.to(`group_${data.group_id}`).emit('message', message);
            
            if (callback) {
                callback({ success: true, message_id: result.insertId });
            }
            
        } catch (error) {
            console.error('Error sharing file:', error);
            if (callback) {
                callback({ success: false, error: error.message });
            }
        }
    });
    
    // Handle disconnection
    socket.on('disconnect', () => {
        console.log('User disconnected:', socket.id);
        
        const connectionInfo = activeConnections.get(socket.id);
        if (connectionInfo) {
            // Notify others that user left
            socket.to(`group_${connectionInfo.groupId}`).emit('user_left', {
                userId: connectionInfo.userId,
                userName: connectionInfo.userName
            });
            
            // Update online status
            updateOnlineStatus(connectionInfo.groupId, connectionInfo.userId, false);
            
            // Remove from active connections
            activeConnections.delete(socket.id);
        }
        
        // Remove from typing users
        typingUsers.delete(socket.id);
    });
});

// Update online status in database
async function updateOnlineStatus(groupId, userId, isOnline) {
    try {
        const connection = await pool.getConnection();
        
        if (isOnline) {
            await connection.execute(
                'INSERT INTO user_online_status (user_id, group_id, is_online, last_seen) VALUES (?, ?, 1, NOW()) ON DUPLICATE KEY UPDATE is_online = 1, last_seen = NOW()',
                [userId, groupId]
            );
        } else {
            await connection.execute(
                'UPDATE user_online_status SET is_online = 0, last_seen = NOW() WHERE user_id = ? AND group_id = ?',
                [userId, groupId]
            );
        }
        
        connection.release();
    } catch (error) {
        console.error('Error updating online status:', error);
    }
}

// API endpoints
app.use(cors());
app.use(express.json());

// Get chat history
app.get('/api/chat/history/:groupId', async (req, res) => {
    try {
        const { groupId } = req.params;
        const { limit = 50, offset = 0 } = req.query;
        
        const connection = await pool.getConnection();
        
        const [messages] = await connection.execute(`
            SELECT m.*, 
                   CASE 
                       WHEN m.sender_type = 'student' THEN s.full_name
                       WHEN m.sender_type = 'faculty' THEN CONCAT(f.first_name, ' ', f.last_name)
                       ELSE 'Unknown'
                   END as sender_name
            FROM messages m 
            LEFT JOIN students s ON m.sender_id = s.id AND m.sender_type = 'student'
            LEFT JOIN faculty f ON m.sender_id = f.id AND m.sender_type = 'faculty'
            WHERE m.group_id = ?
            ORDER BY m.sent_at DESC
            LIMIT ? OFFSET ?
        `, [groupId, parseInt(limit), parseInt(offset)]);
        
        connection.release();
        
        res.json({
            success: true,
            messages: messages.reverse()
        });
        
    } catch (error) {
        console.error('Error fetching chat history:', error);
        res.status(500).json({
            success: false,
            error: error.message
        });
    }
});

// Get online users for a group
app.get('/api/chat/online/:groupId', async (req, res) => {
    try {
        const { groupId } = req.params;
        
        const connection = await pool.getConnection();
        
        const [onlineUsers] = await connection.execute(`
            SELECT uos.user_id, uos.is_online, uos.last_seen,
                   CASE 
                       WHEN s.id IS NOT NULL THEN s.full_name
                       WHEN f.id IS NOT NULL THEN CONCAT(f.first_name, ' ', f.last_name)
                       ELSE 'Unknown'
                   END as user_name
            FROM user_online_status uos
            LEFT JOIN students s ON uos.user_id = s.id
            LEFT JOIN faculty f ON uos.user_id = f.id
            WHERE uos.group_id = ? AND uos.is_online = 1
        `, [groupId]);
        
        connection.release();
        
        res.json({
            success: true,
            online_users: onlineUsers
        });
        
    } catch (error) {
        console.error('Error fetching online users:', error);
        res.status(500).json({
            success: false,
            error: error.message
        });
    }
});

// Health check endpoint
app.get('/health', (req, res) => {
    res.json({
        status: 'ok',
        timestamp: new Date().toISOString(),
        connections: activeConnections.size,
        typing_users: typingUsers.size
    });
});

// Initialize and start server
async function startServer() {
    await initializeDatabase();
    
    const PORT = process.env.PORT || 3000;
    server.listen(PORT, () => {
        console.log(`WebSocket server running on port ${PORT}`);
    });
}

startServer().catch(console.error);

// Graceful shutdown
process.on('SIGTERM', () => {
    console.log('SIGTERM received, shutting down gracefully');
    server.close(() => {
        console.log('Server closed');
        process.exit(0);
    });
});

process.on('SIGINT', () => {
    console.log('SIGINT received, shutting down gracefully');
    server.close(() => {
        console.log('Server closed');
        process.exit(0);
    });
}); 