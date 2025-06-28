<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in and is faculty
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'faculty') {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

$faculty_id = $_SESSION['user_id'];

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

// Get form data
$course_id = intval($_POST['course_id'] ?? 0);
$action = trim($_POST['action'] ?? '');

// Validate input
if ($course_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid course ID']);
    exit();
}

if (!in_array($action, ['assign', 'unassign'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    exit();
}

try {
    require_once 'config.php';
    $conn = getConnection();
    
    if ($action === 'assign') {
        // Check if course exists and is available for assignment
        $stmt = $conn->prepare("SELECT c.*, f.department as faculty_dept 
                               FROM courses c 
                               JOIN faculty f ON f.id = ? 
                               WHERE c.id = ? AND c.faculty_id IS NULL AND c.department = f.department AND c.is_active = 1");
        $stmt->bind_param("ii", $faculty_id, $course_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            echo json_encode(['status' => 'error', 'message' => 'Course not found or not available for assignment']);
            exit();
        }
        
        $course = $result->fetch_assoc();
        $stmt->close();
        
        // Assign faculty to course
        $stmt = $conn->prepare("UPDATE courses SET faculty_id = ? WHERE id = ?");
        $stmt->bind_param("ii", $faculty_id, $course_id);
        
        if ($stmt->execute()) {
            echo json_encode([
                'status' => 'success',
                'message' => 'Successfully assigned to ' . $course['course_code'] . ' - ' . $course['course_name']
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to assign course. Please try again.']);
        }
        $stmt->close();
        
    } else { // unassign
        // Check if faculty is currently assigned to this course
        $stmt = $conn->prepare("SELECT c.* FROM courses c WHERE c.id = ? AND c.faculty_id = ?");
        $stmt->bind_param("ii", $course_id, $faculty_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            echo json_encode(['status' => 'error', 'message' => 'Course not found or you are not assigned to it']);
            exit();
        }
        
        $course = $result->fetch_assoc();
        $stmt->close();
        
        // Check if there are active groups for this course
        $stmt = $conn->prepare("SELECT COUNT(*) as group_count FROM groups WHERE course_id = ? AND status = 'active'");
        $stmt->bind_param("i", $course_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $group_count = $result->fetch_assoc()['group_count'];
        $stmt->close();
        
        if ($group_count > 0) {
            echo json_encode(['status' => 'error', 'message' => 'Cannot unassign from course with active study groups. Please deactivate all groups first.']);
            exit();
        }
        
        // Unassign faculty from course
        $stmt = $conn->prepare("UPDATE courses SET faculty_id = NULL WHERE id = ?");
        $stmt->bind_param("i", $course_id);
        
        if ($stmt->execute()) {
            echo json_encode([
                'status' => 'success',
                'message' => 'Successfully unassigned from ' . $course['course_code'] . ' - ' . $course['course_name']
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to unassign course. Please try again.']);
        }
        $stmt->close();
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 