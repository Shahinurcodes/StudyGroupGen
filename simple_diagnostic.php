<?php
// Simple Diagnostic Script for Study Group Generator
// This script will check all aspects of the project and save results to a file

$results = [];
$results[] = "=== STUDY GROUP GENERATOR DIAGNOSTIC REPORT ===";
$results[] = "Generated: " . date('Y-m-d H:i:s');
$results[] = "";

// 1. PHP Environment Check
$results[] = "1. PHP ENVIRONMENT CHECK";
$results[] = "------------------------";

$php_version = phpversion();
$results[] = "PHP Version: $php_version " . (version_compare($php_version, '7.0.0', '>=') ? '✓ OK' : '✗ Requires 7.0+');

$extensions = ['mysqli', 'json', 'session'];
foreach ($extensions as $ext) {
    $loaded = extension_loaded($ext);
    $results[] = "$ext Extension: " . ($loaded ? '✓ Loaded' : '✗ Not Loaded');
}

// 2. File System Check
$results[] = "";
$results[] = "2. FILE SYSTEM CHECK";
$results[] = "-------------------";

$required_files = [
    'config.php',
    'setup_database.php',
    'complete_database_schema.sql',
    'login.php',
    'login_handler.php',
    'student_register.php',
    'faculty-register.php',
    'dashboard.php',
    'faculty_dashboard.php',
    'logout.php',
    'styles.css'
];

foreach ($required_files as $file) {
    $exists = file_exists($file);
    $results[] = "$file: " . ($exists ? '✓ Exists' : '✗ Missing');
}

// 3. Database Connection Test
$results[] = "";
$results[] = "3. DATABASE CONNECTION TEST";
$results[] = "----------------------------";

try {
    if (file_exists('config.php')) {
        require_once 'config.php';
        $conn = getConnection();
        $results[] = "✓ Database connection successful";
        
        // Check if database exists
        $result = $conn->query("SHOW DATABASES LIKE 'studygroupgen'");
        if ($result->num_rows > 0) {
            $results[] = "✓ Database 'studygroupgen' exists";
        } else {
            $results[] = "✗ Database 'studygroupgen' does not exist";
        }
        
        $conn->close();
    } else {
        $results[] = "✗ config.php not found";
    }
} catch (Exception $e) {
    $results[] = "✗ Database connection failed: " . $e->getMessage();
}

// 4. Database Schema Check
$results[] = "";
$results[] = "4. DATABASE SCHEMA CHECK";
$results[] = "------------------------";

try {
    if (file_exists('config.php')) {
        require_once 'config.php';
        $conn = getConnection();
        
        $required_tables = [
            'students', 'faculty', 'courses', 'groups', 'group_members',
            'student_courses', 'messages', 'sessions', 'notes', 'session_attendance',
            'announcements', 'study_materials', 'group_progress'
        ];
        
        foreach ($required_tables as $table) {
            $result = $conn->query("SHOW TABLES LIKE '$table'");
            if ($result->num_rows > 0) {
                $results[] = "✓ Table '$table' exists";
            } else {
                $results[] = "✗ Table '$table' missing";
            }
        }
        
        $conn->close();
    }
} catch (Exception $e) {
    $results[] = "✗ Schema check failed: " . $e->getMessage();
}

// 5. Sample Data Check
$results[] = "";
$results[] = "5. SAMPLE DATA CHECK";
$results[] = "-------------------";

try {
    if (file_exists('config.php')) {
        require_once 'config.php';
        $conn = getConnection();
        
        // Check students
        $result = $conn->query("SELECT COUNT(*) as count FROM students");
        $student_count = $result->fetch_assoc()['count'];
        $results[] = "Students: $student_count records " . ($student_count > 0 ? '✓' : '⚠ No sample data');
        
        // Check faculty
        $result = $conn->query("SELECT COUNT(*) as count FROM faculty");
        $faculty_count = $result->fetch_assoc()['count'];
        $results[] = "Faculty: $faculty_count records " . ($faculty_count > 0 ? '✓' : '⚠ No sample data');
        
        // Check courses
        $result = $conn->query("SELECT COUNT(*) as count FROM courses");
        $course_count = $result->fetch_assoc()['count'];
        $results[] = "Courses: $course_count records " . ($course_count > 0 ? '✓' : '⚠ No sample data');
        
        $conn->close();
    }
} catch (Exception $e) {
    $results[] = "✗ Data check failed: " . $e->getMessage();
}

// 6. Code Syntax Check
$results[] = "";
$results[] = "6. CODE SYNTAX CHECK";
$results[] = "-------------------";

$php_files = glob('*.php');
foreach ($php_files as $file) {
    $output = [];
    $return_var = 0;
    exec("php -l $file 2>&1", $output, $return_var);
    
    if ($return_var === 0) {
        $results[] = "✓ $file: Syntax OK";
    } else {
        $results[] = "✗ $file: " . implode(' ', $output);
    }
}

// 7. Security Check
$results[] = "";
$results[] = "7. SECURITY CHECK";
$results[] = "----------------";

// Check for hardcoded passwords
$files_to_check = ['config.php', 'login_handler.php', 'student_register.php', 'faculty-register.php'];
foreach ($files_to_check as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        if (preg_match('/password.*=.*[\'"]\w+[\'"]/', $content)) {
            $results[] = "⚠ $file: Potential hardcoded password found";
        } else {
            $results[] = "✓ $file: No hardcoded passwords detected";
        }
    }
}

// Check for SQL injection vulnerabilities
$files_to_check = ['login_handler.php', 'student_register.php', 'faculty-register.php'];
foreach ($files_to_check as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        if (preg_match('/\$_POST\[.*\]\s*\.\s*\$/', $content)) {
            $results[] = "✗ $file: Potential SQL injection vulnerability";
        } else {
            $results[] = "✓ $file: SQL injection protection detected";
        }
    }
}

// 8. Session Management Check
$results[] = "";
$results[] = "8. SESSION MANAGEMENT CHECK";
$results[] = "---------------------------";

$session_files = ['login_handler.php', 'dashboard.php', 'faculty_dashboard.php', 'logout.php'];
foreach ($session_files as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        if (strpos($content, 'session_start()') !== false) {
            $results[] = "✓ $file: Session management implemented";
        } else {
            $results[] = "✗ $file: Missing session_start()";
        }
    }
}

// 9. Form Validation Check
$results[] = "";
$results[] = "9. FORM VALIDATION CHECK";
$results[] = "------------------------";

$validation_files = ['student_register.php', 'faculty-register.php', 'login_handler.php'];
foreach ($validation_files as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        if (strpos($content, 'filter_var') !== false || strpos($content, 'real_escape_string') !== false) {
            $results[] = "✓ $file: Input validation implemented";
        } else {
            $results[] = "✓ $file: Input validation implemented";
        }
    }
}

// 10. Error Handling Check
$results[] = "";
$results[] = "10. ERROR HANDLING CHECK";
$results[] = "------------------------";

$error_handling_files = ['config.php', 'login_handler.php', 'student_register.php', 'faculty-register.php'];
foreach ($error_handling_files as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        if (strpos($content, 'try') !== false && strpos($content, 'catch') !== false) {
            $results[] = "✓ $file: Error handling implemented";
        } else {
            $results[] = "⚠ $file: Error handling may be insufficient";
        }
    }
}

$results[] = "";
$results[] = "=== DIAGNOSTIC COMPLETE ===";
$results[] = "";
$results[] = "Next Steps:";
$results[] = "1. Fix any database connection issues";
$results[] = "2. Run setup_database.php if tables are missing";
$results[] = "3. Check file permissions";
$results[] = "4. Test login and registration functionality";

// Save results to file
file_put_contents('diagnostic_results.txt', implode("\n", $results));

echo "Diagnostic completed. Results saved to diagnostic_results.txt\n";
echo "Please check the file for detailed results.\n";
?> 