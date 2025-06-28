<?php
// Diagnostic Script for Study Group Generator
echo "<h1>Study Group Generator - Diagnostic Report</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
    .section { margin: 20px 0; padding: 20px; background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .success { border-left: 4px solid #28a745; }
    .error { border-left: 4px solid #dc3545; }
    .warning { border-left: 4px solid #ffc107; }
    .info { border-left: 4px solid #17a2b8; }
    .status { font-weight: bold; padding: 5px 10px; border-radius: 4px; color: white; }
    .status.success { background: #28a745; }
    .status.error { background: #dc3545; }
    .status.warning { background: #ffc107; color: #333; }
    .status.info { background: #17a2b8; }
    pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; }
</style>";

// 1. Check PHP Version and Extensions
echo "<div class='section info'>";
echo "<h2>1. PHP Environment</h2>";
echo "<p><strong>PHP Version:</strong> " . phpversion() . "</p>";

$required_extensions = ['mysqli', 'json', 'session'];
$missing_extensions = [];

foreach ($required_extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "<p>✓ <strong>$ext</strong> extension loaded</p>";
    } else {
        echo "<p class='error'>✗ <strong>$ext</strong> extension missing</p>";
        $missing_extensions[] = $ext;
    }
}

if (empty($missing_extensions)) {
    echo "<span class='status success'>PHP Environment: OK</span>";
} else {
    echo "<span class='status error'>PHP Environment: ISSUES FOUND</span>";
}
echo "</div>";

// 2. Check Database Connection
echo "<div class='section info'>";
echo "<h2>2. Database Connection</h2>";

try {
    require_once 'config.php';
    $conn = getConnection();
    echo "<p>✓ Database connection successful</p>";
    
    $result = $conn->query("SELECT DATABASE() as current_db");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "<p><strong>Current Database:</strong> " . $row['current_db'] . "</p>";
    }
    
    $conn->close();
    echo "<span class='status success'>Database Connection: OK</span>";
} catch (Exception $e) {
    echo "<p class='error'>✗ Database connection failed: " . $e->getMessage() . "</p>";
    echo "<span class='status error'>Database Connection: FAILED</span>";
}
echo "</div>";

// 3. Check Database Tables
echo "<div class='section info'>";
echo "<h2>3. Database Tables</h2>";

try {
    $conn = getConnection();
    $required_tables = ['students', 'faculty', 'courses', 'groups', 'group_members', 'messages', 'sessions', 'notes'];
    $missing_tables = [];
    $existing_tables = [];
    
    foreach ($required_tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result && $result->num_rows > 0) {
            echo "<p>✓ Table <strong>$table</strong> exists</p>";
            $existing_tables[] = $table;
        } else {
            echo "<p class='error'>✗ Table <strong>$table</strong> missing</p>";
            $missing_tables[] = $table;
        }
    }
    
    if (empty($missing_tables)) {
        echo "<span class='status success'>Database Tables: OK</span>";
    } else {
        echo "<span class='status error'>Database Tables: MISSING TABLES</span>";
        echo "<p><strong>Missing tables:</strong> " . implode(', ', $missing_tables) . "</p>";
        echo "<p><a href='setup_database.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;'>Run Database Setup</a></p>";
    }
    
    $conn->close();
} catch (Exception $e) {
    echo "<p class='error'>✗ Table check failed: " . $e->getMessage() . "</p>";
    echo "<span class='status error'>Database Tables: CHECK FAILED</span>";
}
echo "</div>";

// 4. Check Sample Data
echo "<div class='section info'>";
echo "<h2>4. Sample Data</h2>";

try {
    $conn = getConnection();
    
    $result = $conn->query("SELECT COUNT(*) as count FROM students");
    $student_count = $result->fetch_assoc()['count'];
    echo "<p><strong>Students:</strong> $student_count records</p>";
    
    $result = $conn->query("SELECT COUNT(*) as count FROM faculty");
    $faculty_count = $result->fetch_assoc()['count'];
    echo "<p><strong>Faculty:</strong> $faculty_count records</p>";
    
    $result = $conn->query("SELECT COUNT(*) as count FROM courses");
    $course_count = $result->fetch_assoc()['count'];
    echo "<p><strong>Courses:</strong> $course_count records</p>";
    
    if ($student_count > 0 && $faculty_count > 0) {
        echo "<span class='status success'>Sample Data: AVAILABLE</span>";
        echo "<p><strong>Test Accounts:</strong></p>";
        echo "<ul>";
        echo "<li>Student: alham.dina@student.edu / password</li>";
        echo "<li>Faculty: john.smith@university.edu / password</li>";
        echo "</ul>";
    } else {
        echo "<span class='status warning'>Sample Data: MISSING</span>";
        echo "<p>No sample data found. Run database setup to create test accounts.</p>";
    }
    
    $conn->close();
} catch (Exception $e) {
    echo "<p class='error'>✗ Sample data check failed: " . $e->getMessage() . "</p>";
    echo "<span class='status error'>Sample Data: CHECK FAILED</span>";
}
echo "</div>";

// 5. Check File Permissions and Existence
echo "<div class='section info'>";
echo "<h2>5. File System</h2>";

$required_files = [
    'index.php',
    'login.php',
    'login_handler.php',
    'student_register.php',
    'faculty-register.php',
    'faculty_login.php',
    'dashboard.php',
    'faculty_dashboard.php',
    'logout.php',
    'config.php',
    'styles.css',
    'complete_database_schema.sql'
];

$missing_files = [];
$existing_files = [];

foreach ($required_files as $file) {
    if (file_exists($file)) {
        echo "<p>✓ File <strong>$file</strong> exists</p>";
        $existing_files[] = $file;
    } else {
        echo "<p class='error'>✗ File <strong>$file</strong> missing</p>";
        $missing_files[] = $file;
    }
}

if (empty($missing_files)) {
    echo "<span class='status success'>File System: OK</span>";
} else {
    echo "<span class='status error'>File System: MISSING FILES</span>";
    echo "<p><strong>Missing files:</strong> " . implode(', ', $missing_files) . "</p>";
}
echo "</div>";

// 6. Test Login System
echo "<div class='section info'>";
echo "<h2>6. Login System Test</h2>";

try {
    $conn = getConnection();
    
    // Test student login
    $test_email = 'alham.dina@student.edu';
    $test_password = 'password';
    
    $stmt = $conn->prepare("SELECT id, full_name, password FROM students WHERE email = ?");
    $stmt->bind_param("s", $test_email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($test_password, $user['password'])) {
            echo "<p>✓ Student login test successful</p>";
        } else {
            echo "<p class='error'>✗ Student password verification failed</p>";
        }
    } else {
        echo "<p class='warning'>⚠ Test student not found: $test_email</p>";
    }
    $stmt->close();
    
    // Test faculty login
    $test_email = 'john.smith@university.edu';
    $test_password = 'password';
    
    $stmt = $conn->prepare("SELECT id, first_name, last_name, password FROM faculty WHERE email = ?");
    $stmt->bind_param("s", $test_email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($test_password, $user['password'])) {
            echo "<p>✓ Faculty login test successful</p>";
        } else {
            echo "<p class='error'>✗ Faculty password verification failed</p>";
        }
    } else {
        echo "<p class='warning'>⚠ Test faculty not found: $test_email</p>";
    }
    $stmt->close();
    
    $conn->close();
    echo "<span class='status success'>Login System: OK</span>";
} catch (Exception $e) {
    echo "<p class='error'>✗ Login test failed: " . $e->getMessage() . "</p>";
    echo "<span class='status error'>Login System: FAILED</span>";
}
echo "</div>";

// 7. Check Session Management
echo "<div class='section info'>";
echo "<h2>7. Session Management</h2>";

session_start();
if (session_status() === PHP_SESSION_ACTIVE) {
    echo "<p>✓ Sessions are working</p>";
    echo "<span class='status success'>Session Management: OK</span>";
} else {
    echo "<p class='error'>✗ Sessions are not working</p>";
    echo "<span class='status error'>Session Management: FAILED</span>";
}
session_destroy();
echo "</div>";

// 8. Recommendations
echo "<div class='section'>";
echo "<h2>8. Recommendations & Next Steps</h2>";

echo "<h3>If Database Tables Are Missing:</h3>";
echo "<ol>";
echo "<li>Click <a href='setup_database.php' style='color: #007bff;'>Run Database Setup</a> to create tables and sample data</li>";
echo "<li>Wait for the setup to complete</li>";
echo "<li>Refresh this page to verify everything is working</li>";
echo "</ol>";

echo "<h3>If Everything Looks Good:</h3>";
echo "<ol>";
echo "<li><a href='index.php' style='color: #007bff;'>Go to Home Page</a></li>";
echo "<li><a href='login.php' style='color: #007bff;'>Test Student Login</a></li>";
echo "<li><a href='faculty_login.php' style='color: #007bff;'>Test Faculty Login</a></li>";
echo "<li><a href='student_register.php' style='color: #007bff;'>Test Student Registration</a></li>";
echo "<li><a href='faculty-register.php' style='color: #007bff;'>Test Faculty Registration</a></li>";
echo "</ol>";

echo "<h3>Common Issues & Solutions:</h3>";
echo "<ul>";
echo "<li><strong>XAMPP not running:</strong> Start Apache and MySQL in XAMPP Control Panel</li>";
echo "<li><strong>Database connection failed:</strong> Check if MySQL is running and credentials in config.php</li>";
echo "<li><strong>Tables missing:</strong> Run the database setup script</li>";
echo "<li><strong>Login not working:</strong> Make sure sample data exists</li>";
echo "</ul>";
echo "</div>";
?> 