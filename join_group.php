<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

$student_id = $_SESSION['user_id'];

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

// Get group ID from form data
$group_id = intval($_POST['group_id'] ?? 0);

// Validate input
if ($group_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid group ID']);
    exit();
}

try {
    require_once 'config.php';
    $conn = getConnection();
    
    // Check if group exists and is active
    $stmt = $conn->prepare("SELECT g.*, c.course_code, c.course_name, c.department, 
                           (SELECT COUNT(*) FROM group_members WHERE group_id = g.id) as current_members
                           FROM groups g 
                           JOIN courses c ON g.course_id = c.id 
                           WHERE g.id = ? AND g.status = 'active'");
    $stmt->bind_param("i", $group_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Group not found or inactive']);
        exit();
    }
    
    $group = $result->fetch_assoc();
    $stmt->close();
    
    // Check if student is already a member of this group
    $stmt = $conn->prepare("SELECT id FROM group_members WHERE group_id = ? AND student_id = ?");
    $stmt->bind_param("ii", $group_id, $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'You are already a member of this group']);
        exit();
    }
    $stmt->close();
    
    // Check if group is full
    if ($group['current_members'] >= $group['max_members']) {
        echo json_encode(['status' => 'error', 'message' => 'This group is full']);
        exit();
    }
    
    // Check if student is enrolled in the course (optional validation)
    $stmt = $conn->prepare("SELECT id FROM student_courses WHERE student_id = ? AND course_id = ?");
    $stmt->bind_param("ii", $student_id, $group['course_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // Allow joining even if not enrolled (you can remove this check if you want to be strict)
        // echo json_encode(['status' => 'error', 'message' => 'You must be enrolled in this course to join the group']);
        // exit();
    }
    $stmt->close();
    
    // Add student to group
    $stmt = $conn->prepare("INSERT INTO group_members (group_id, student_id, role) VALUES (?, ?, 'member')");
    $stmt->bind_param("ii", $group_id, $student_id);
    
    if ($stmt->execute()) {
        $stmt->close();
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Successfully joined ' . $group['group_name'] . '!',
            'redirect_url' => 'group_details.php?id=' . $group_id,
            'group' => [
                'id' => $group_id,
                'name' => $group['group_name'],
                'course_code' => $group['course_code'],
                'course_name' => $group['course_name']
            ]
        ]);
    } else {
        $stmt->close();
        echo json_encode(['status' => 'error', 'message' => 'Failed to join group. Please try again.']);
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 