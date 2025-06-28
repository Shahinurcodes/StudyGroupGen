<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in and is a student or faculty
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
$title = trim($_POST['title'] ?? '');

// Validate input
if ($group_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid group ID']);
    exit();
}

if (empty($content)) {
    echo json_encode(['status' => 'error', 'message' => 'Note content is required']);
    exit();
}

if (strlen($content) > 5000) {
    echo json_encode(['status' => 'error', 'message' => 'Note too long (max 5000 characters)']);
    exit();
}

if (strlen($title) > 100) {
    echo json_encode(['status' => 'error', 'message' => 'Title too long (max 100 characters)']);
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
    
    // Insert note - properly handle student and faculty notes
    if ($user_type === 'student') {
        $stmt = $conn->prepare("INSERT INTO notes (group_id, student_id, faculty_id, title, content) VALUES (?, ?, NULL, ?, ?)");
        $stmt->bind_param("iiss", $group_id, $user_id, $title, $content);
    } else {
        // For faculty, use faculty_id field
        $stmt = $conn->prepare("INSERT INTO notes (group_id, student_id, faculty_id, title, content) VALUES (?, NULL, ?, ?, ?)");
        $stmt->bind_param("iiss", $group_id, $user_id, $title, $content);
    }
    
    if ($stmt->execute()) {
        $note_id = $conn->insert_id;
        
        // Get the inserted note with author name
        if ($user_type === 'student') {
            $stmt = $conn->prepare("SELECT n.*, s.full_name as author_name
                                   FROM notes n 
                                   JOIN students s ON n.student_id = s.id
                                   WHERE n.id = ?");
        } else {
            $stmt = $conn->prepare("SELECT n.*, CONCAT(f.first_name, ' ', f.last_name) as author_name
                                   FROM notes n 
                                   JOIN faculty f ON n.faculty_id = f.id
                                   WHERE n.id = ?");
        }
        $stmt->bind_param("i", $note_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $note = $result->fetch_assoc();
        $stmt->close();
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Note shared successfully',
            'data' => [
                'id' => $note['id'],
                'title' => $note['title'],
                'content' => $note['content'],
                'author_name' => $note['author_name'],
                'created_at' => $note['created_at']
            ]
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to share note']);
    }
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 