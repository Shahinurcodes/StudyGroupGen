<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['student', 'faculty'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

// Get form data
$group_id = intval($_POST['group_id'] ?? 0);
$content = trim($_POST['content'] ?? '');

// Validate input
if ($group_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid group ID']);
    exit();
}

if (empty($content)) {
    echo json_encode(['status' => 'error', 'message' => 'Message content is required']);
    exit();
}

if (strlen($content) > 1000) {
    echo json_encode(['status' => 'error', 'message' => 'Message too long (max 1000 characters)']);
    exit();
}

try {
    require_once 'config.php';
    $conn = getConnection();
    
    // Check if user is a member of the group (for students) or faculty mentor (for faculty)
    if ($user_type === 'student') {
        $stmt = $conn->prepare("SELECT id FROM group_members WHERE group_id = ? AND student_id = ?");
        $stmt->bind_param("ii", $group_id, $user_id);
    } else {
        $stmt = $conn->prepare("SELECT id FROM groups WHERE id = ? AND faculty_mentor_id = ?");
        $stmt->bind_param("ii", $group_id, $user_id);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'You are not a member of this group']);
        exit();
    }
    $stmt->close();
    
    // Insert message
    $stmt = $conn->prepare("INSERT INTO messages (group_id, sender_id, sender_type, content) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiss", $group_id, $user_id, $user_type, $content);
    
    if ($stmt->execute()) {
        $message_id = $conn->insert_id;
        
        // Get the inserted message with sender name
        $stmt = $conn->prepare("SELECT m.*, 
                               CASE 
                                   WHEN m.sender_type = 'student' THEN s.full_name
                                   WHEN m.sender_type = 'faculty' THEN CONCAT(f.first_name, ' ', f.last_name)
                                   ELSE 'Unknown'
                               END as sender_name
                               FROM messages m 
                               LEFT JOIN students s ON m.sender_id = s.id AND m.sender_type = 'student'
                               LEFT JOIN faculty f ON m.sender_id = f.id AND m.sender_type = 'faculty'
                               WHERE m.id = ?");
        $stmt->bind_param("i", $message_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $message = $result->fetch_assoc();
        $stmt->close();
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Message sent successfully',
            'data' => [
                'id' => $message['id'],
                'content' => $message['content'],
                'sender_name' => $message['sender_name'],
                'sent_at' => $message['sent_at'],
                'sender_type' => $message['sender_type']
            ]
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to send message']);
    }
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 