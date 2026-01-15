<!-- logout.php -->
<?php
require_once 'includes/config/constants.php';
require_once 'includes/classes/Database.php';
require_once 'includes/classes/Session.php';
require_once 'includes/classes/Auth.php';

Session::init();
$auth = new Auth();

// Logout the user
$auth->logout();

// Redirect to login page
header('Location: ' . BASE_URL);
exit();
?>