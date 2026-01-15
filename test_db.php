<!-- test_db.php -->
<?php
require_once 'includes/config/constants.php';
require_once 'includes/classes/Database.php';

echo "<h1>Database Connection Test</h1>";

try {
    $db = Database::getInstance()->getConnection();
    echo "<p style='color: green;'>✓ Database connection successful!</p>";
    
    // Test if tables exist
    $tables = ['super_admins', 'admins', 'teachers', 'students', 'departments'];
    
    foreach ($tables as $table) {
        $stmt = $db->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "<p style='color: green;'>✓ Table '$table' exists</p>";
        } else {
            echo "<p style='color: red;'>✗ Table '$table' NOT found</p>";
        }
    }
    
    // Test if test users exist
    echo "<h2>Test Users:</h2>";
    $testUsers = [
        ['super_admins', 'superadmin'],
        ['admins', 'cs_head'],
        ['teachers', 'T001'],
        ['students', 'UGR13610']
    ];
    
    foreach ($testUsers as $test) {
        $table = $test[0];
        $username = $test[1];
        
        $stmt = $db->prepare("SELECT * FROM $table WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user) {
            echo "<p style='color: green;'>✓ User '$username' found in $table</p>";
        } else {
            echo "<p style='color: red;'>✗ User '$username' NOT found in $table</p>";
        }
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>✗ Database connection failed: " . $e->getMessage() . "</p>";
}
?>