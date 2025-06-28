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
$group_name = trim($_POST['group_name'] ?? '');
$course_id = intval($_POST['course_id'] ?? 0);
$max_members = intval($_POST['max_members'] ?? 6);
$description = trim($_POST['description'] ?? '');

// Validate input
if (empty($group_name)) {
    echo json_encode(['status' => 'error', 'message' => 'Group name is required']);
    exit();
}

if ($course_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Please select a valid course']);
    exit();
}

if ($max_members < 2 || $max_members > 10) {
    echo json_encode(['status' => 'error', 'message' => 'Maximum members must be between 2 and 10']);
    exit();
}

try {
    require_once 'config.php';
    $conn = getConnection();
    
    // Get faculty's department
    $stmt = $conn->prepare("SELECT department FROM faculty WHERE id = ?");
    $stmt->bind_param("i", $faculty_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $faculty = $result->fetch_assoc();
    $stmt->close();
    if (!$faculty) {
        echo json_encode(['status' => 'error', 'message' => 'Faculty not found.']);
        exit();
    }
    $faculty_department = $faculty['department'];

    // Check if faculty is assigned to the course and department matches
    $stmt = $conn->prepare("SELECT id, department FROM courses WHERE id = ? AND faculty_id = ?");
    $stmt->bind_param("ii", $course_id, $faculty_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'You can only create groups for courses you are teaching']);
        exit();
    }
    $course = $result->fetch_assoc();
    $stmt->close();
    if ($course['department'] !== $faculty_department) {
        echo json_encode(['status' => 'error', 'message' => 'You can only create groups for courses in your department']);
        exit();
    }
    
    // Check if group name already exists for this course
    $stmt = $conn->prepare("SELECT id FROM groups WHERE group_name = ? AND course_id = ?");
    $stmt->bind_param("si", $group_name, $course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'A group with this name already exists for this course']);
        exit();
    }
    $stmt->close();
    
    // Insert new group
    $stmt = $conn->prepare("INSERT INTO groups (group_name, course_id, max_members, faculty_mentor_id, status) VALUES (?, ?, ?, ?, 'active')");
    $stmt->bind_param("siii", $group_name, $course_id, $max_members, $faculty_id);
    
    if ($stmt->execute()) {
        $group_id = $conn->insert_id;
        
        // Get course details for response
        $stmt = $conn->prepare("SELECT c.course_code, c.course_name FROM courses c WHERE c.id = ?");
        $stmt->bind_param("i", $course_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $course = $result->fetch_assoc();
        $stmt->close();
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Study group created successfully!',
            'group' => [
                'id' => $group_id,
                'name' => $group_name,
                'course_code' => $course['course_code'],
                'course_name' => $course['course_name'],
                'max_members' => $max_members,
                'current_members' => 0
            ]
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to create group. Please try again.']);
    }
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 