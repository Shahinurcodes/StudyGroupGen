<?php
session_start();

// Check if user is logged in and is faculty
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'faculty') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit();
}

$group_id = $_POST['group_id'] ?? null;
$status = $_POST['status'] ?? null;

if (!$group_id || !$status) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required parameters']);
    exit();
}

// Validate status
if (!in_array($status, ['active', 'inactive'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid status']);
    exit();
}

try {
    require_once 'config.php';
    $conn = getConnection();
    
    // Check if faculty owns this group
    $stmt = $conn->prepare("SELECT id FROM groups WHERE id = ? AND faculty_mentor_id = ?");
    $stmt->bind_param("ii", $group_id, $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Group not found or you do not have permission']);
        exit();
    }
    $stmt->close();
    
    // Update group status
    $stmt = $conn->prepare("UPDATE groups SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $group_id);
    
    if ($stmt->execute()) {
        $status_text = $status === 'active' ? 'activated' : 'deactivated';
        echo json_encode(['status' => 'success', 'message' => "Group successfully $status_text"]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update group status']);
    }
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 