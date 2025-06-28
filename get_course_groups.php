<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
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

$student_id = $_SESSION['user_id'];
$course_id = intval($_POST['course_id'] ?? 0);

// Validate input
if ($course_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid course ID']);
    exit();
}

try {
    require_once 'config.php';
    $conn = getConnection();
    
    // Get groups for the specified course
    $stmt = $conn->prepare("SELECT g.*, c.course_code, c.course_name,
                           (SELECT COUNT(*) FROM group_members WHERE group_id = g.id) as current_members,
                           (SELECT COUNT(*) FROM group_members WHERE group_id = g.id AND student_id = ?) as is_member
                           FROM groups g 
                           JOIN courses c ON g.course_id = c.id 
                           WHERE g.course_id = ? AND g.status = 'active'
                           ORDER BY g.created_at DESC");
    $stmt->bind_param("ii", $student_id, $course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $groups = [];
    while ($row = $result->fetch_assoc()) {
        $groups[] = [
            'id' => $row['id'],
            'group_name' => $row['group_name'],
            'description' => $row['description'],
            'course_code' => $row['course_code'],
            'course_name' => $row['course_name'],
            'max_members' => $row['max_members'],
            'current_members' => $row['current_members'],
            'created_at' => $row['created_at'],
            'is_member' => $row['is_member'] > 0
        ];
    }
    $stmt->close();
    
    echo json_encode([
        'status' => 'success',
        'groups' => $groups
    ]);
    
    $conn->close();
    
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 