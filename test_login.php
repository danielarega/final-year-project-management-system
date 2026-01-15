<!-- test_login.php -->
<?php
require_once 'includes/config/constants.php';
require_once 'includes/classes/Database.php';
require_once 'includes/classes/Session.php';
require_once 'includes/classes/Auth.php';

Session::init();
$auth = new Auth();

echo "<h1>Login Test</h1>";
echo "<p><strong>Test Password for all users: 123456</strong></p>";

$testCases = [
    ['superadmin', '123456', 'superadmin', 'Super Admin Login'],
    ['cs_head', '123456', 'admin', 'Admin Login'],
    ['T001', '123456', 'teacher', 'Teacher Login'],
    ['UGR13610', '123456', 'student', 'Student Login'],
];

foreach ($testCases as $test) {
    $username = $test[0];
    $password = $test[1];
    $userType = $test[2];
    $description = $test[3];
    
    echo "<h3>Testing: $description</h3>";
    echo "Username: $username<br>";
    echo "User Type: $userType<br>";
    
    $result = $auth->login($username, $password, $userType);
    
    if ($result['success']) {
        echo "<p style='color: green;'>✓ Login SUCCESSFUL!</p>";
        echo "<pre>" . print_r($result['data'], true) . "</pre>";
        
        // Logout after test
        $auth->logout();
    } else {
        echo "<p style='color: red;'>✗ Login FAILED: " . $result['message'] . "</p>";
    }
    
    echo "<hr>";
}

// Test invalid login
echo "<h3>Testing Invalid Login</h3>";
$result = $auth->login('invalid', 'wrong', 'student');
if (!$result['success']) {
    echo "<p style='color: green;'>✓ Invalid login correctly rejected: " . $result['message'] . "</p>";
} else {
    echo "<p style='color: red;'>✗ Invalid login should have failed</p>";
}
?>