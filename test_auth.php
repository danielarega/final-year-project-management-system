<!-- test_auth.php -->
<?php
require_once 'includes/config/constants.php';
require_once 'includes/classes/Database.php';
require_once 'includes/classes/Session.php';
require_once 'includes/classes/Auth.php';
require_once 'includes/classes/UserManager.php';

Session::init();
echo "<h1>Authentication System Test</h1>";
echo "<hr>";

// Test 1: Database Connection
try {
    $db = Database::getInstance()->getConnection();
    echo "<div style='color: green;'>✓ Database connection successful</div>";
} catch (Exception $e) {
    echo "<div style='color: red;'>✗ Database connection failed: " . $e->getMessage() . "</div>";
}

// Test 2: Session Initialization
if (session_status() === PHP_SESSION_ACTIVE) {
    echo "<div style='color: green;'>✓ Session initialized successfully</div>";
} else {
    echo "<div style='color: red;'>✗ Session initialization failed</div>";
}

// Test 3: Auth Class
$auth = new Auth();
echo "<div style='color: green;'>✓ Auth class instantiated</div>";

// Test 4: UserManager Class
$userManager = new UserManager();
echo "<div style='color: green;'>✓ UserManager class instantiated</div>";

// Test 5: Test Users Creation
echo "<h3>Test Users:</h3>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>User Type</th><th>Username</th><th>Password</th><th>Status</th></tr>";

$testUsers = [
    ['superadmin', 'superadmin', 'admin123', 'Should work'],
    ['admin', 'depthead1', 'admin123', 'Should work'],
    ['teacher', 'teacher1', 'teacher123', 'Should work'],
    ['student', 'UGR13610', 'student123', 'Should work'],
    ['student', 'invalid', 'wrongpass', 'Should fail']
];

foreach ($testUsers as $user) {
    echo "<tr>";
    echo "<td>{$user[0]}</td>";
    echo "<td>{$user[1]}</td>";
    echo "<td>{$user[2]}</td>";
    echo "<td>{$user[3]}</td>";
    echo "</tr>";
}
echo "</table>";

// Test 6: Password Hash Test
$testPassword = 'test123';
$hashed = password_hash($testPassword, PASSWORD_BCRYPT);
if (password_verify($testPassword, $hashed)) {
    echo "<div style='color: green;'>✓ Password hashing/verification working</div>";
} else {
    echo "<div style='color: red;'>✗ Password hashing/verification failed</div>";
}

echo "<hr>";
echo "<h3>Test Login URLs:</h3>";
echo "<ul>";
echo "<li><a href='" . BASE_URL . "' target='_blank'>Login Page</a></li>";
echo "<li><a href='" . BASE_URL . "superadmin/dashboard.php' target='_blank'>Super Admin Dashboard</a> (requires login)</li>";
echo "<li><a href='" . BASE_URL . "admin/dashboard.php' target='_blank'>Admin Dashboard</a> (requires login)</li>";
echo "<li><a href='" . BASE_URL . "teacher/dashboard.php' target='_blank'>Teacher Dashboard</a> (requires login)</li>";
echo "<li><a href='" . BASE_URL . "student/dashboard.php' target='_blank'>Student Dashboard</a> (requires login)</li>";
echo "</ul>";

echo "<hr>";
echo "<h3>Next Steps:</h3>";
echo "<ol>";
echo "<li>Login with superadmin/admin123</li>";
echo "<li>Create department heads from super admin panel</li>";
echo "<li>Login as department head to create teachers and students</li>";
echo "<li>Test password reset functionality</li>";
echo "<li>Test session timeout (24 hours)</li>";
echo "</ol>";
?>