<!-- index.php -->
<?php
require_once 'includes/config/constants.php';
require_once 'includes/classes/Database.php';
require_once 'includes/classes/Session.php';
require_once 'includes/classes/Auth.php';

Session::init();
$auth = new Auth();

// Redirect if already logged in
if ($auth->isLoggedIn()) {
    $user = $auth->getUser();
    header('Location: ' . BASE_URL . $user['user_type'] . '/dashboard.php');
    exit();
}

$error = '';
$success = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $userType = $_POST['user_type'];
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password';
    } else {
        $result = $auth->login($username, $password, $userType);
        
        if ($result['success']) {
            $user = $auth->getUser();
            header('Location: ' . BASE_URL . $user['user_type'] . '/dashboard.php');
            exit();
        } else {
            $error = $result['message'];
        }
    }
}

// Handle forgot password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['forgot_password'])) {
    $email = trim($_POST['email']);
    $userType = $_POST['user_type'];
    
    if (empty($email)) {
        $error = 'Please enter your email address';
    } else {
        $result = $auth->requestPasswordReset($email, $userType);
        if ($result['success']) {
            $success = $result['message'];
            // In development, show the token
            if (isset($result['test_token'])) {
                $success .= '<br><small>Development Token: ' . $result['test_token'] . '</small>';
            }
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FYPMS - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .login-body {
            padding: 30px;
        }
        .user-type-buttons .btn {
            border-radius: 50px;
            margin: 5px;
            padding: 8px 20px;
        }
        .user-type-buttons .btn.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        .form-control {
            border-radius: 50px;
            padding: 12px 20px;
            border: 1px solid #ddd;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.25rem rgba(102, 126, 234, 0.25);
        }
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 12px;
            border-radius: 50px;
            width: 100%;
            font-weight: 600;
        }
        .btn-login:hover {
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
            color: white;
        }
        .logo {
            max-width: 100px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="login-card">
                    <div class="login-header">
                        <img src="https://arsi.edu.et/wp-content/uploads/2020/10/Arsi-Logo.png" alt="Arsi University Logo" class="logo">
                        <h3>Final Year Project Management System</h3>
                        <p class="mb-0">College of Business and Economics</p>
                    </div>
                    
                    <div class="login-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($error); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <?php echo $success; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Login Form -->
                        <form method="POST" action="" id="loginForm">
                            <div class="mb-4 text-center">
                                <h5>Login as</h5>
                                <div class="user-type-buttons">
                                    <input type="radio" class="btn-check" name="user_type" id="student" value="student" checked>
                                    <label class="btn btn-outline-primary" for="student">
                                        <i class="fas fa-user-graduate"></i> Student
                                    </label>
                                    
                                    <input type="radio" class="btn-check" name="user_type" id="teacher" value="teacher">
                                    <label class="btn btn-outline-primary" for="teacher">
                                        <i class="fas fa-chalkboard-teacher"></i> Teacher
                                    </label>
                                    
                                    <input type="radio" class="btn-check" name="user_type" id="admin" value="admin">
                                    <label class="btn btn-outline-primary" for="admin">
                                        <i class="fas fa-user-tie"></i> Admin
                                    </label>
                                    
                                    <input type="radio" class="btn-check" name="user_type" id="superadmin" value="superadmin">
                                    <label class="btn btn-outline-primary" for="superadmin">
                                        <i class="fas fa-user-shield"></i> Super Admin
                                    </label>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="username" class="form-label">Username or Email</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" class="form-control" id="username" name="username" 
                                           placeholder="Enter username or email" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" 
                                           placeholder="Enter password" required>
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="remember">
                                <label class="form-check-label" for="remember">Remember me</label>
                                <a href="javascript:void(0)" class="float-end" data-bs-toggle="modal" data-bs-target="#forgotPasswordModal">
                                    Forgot Password?
                                </a>
                            </div>
                            
                            <button type="submit" name="login" class="btn btn-login mb-3">
                                <i class="fas fa-sign-in-alt"></i> Login
                            </button>
                            
                            <div class="text-center">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle"></i>
                                    For demo: superadmin/admin123
                                </small>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Forgot Password Modal -->
    <div class="modal fade" id="forgotPasswordModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title">Reset Password</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">User Type</label>
                            <select class="form-control" name="user_type" required>
                                <option value="student">Student</option>
                                <option value="teacher">Teacher</option>
                                <option value="admin">Admin</option>
                                <option value="superadmin">Super Admin</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email Address</label>
                            <input type="email" class="form-control" name="email" 
                                   placeholder="Enter registered email" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="forgot_password" class="btn btn-primary">Send Reset Link</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
        
        // User type button active state
        document.querySelectorAll('.btn-check').forEach(radio => {
            radio.addEventListener('change', function() {
                document.querySelectorAll('.user-type-buttons .btn').forEach(btn => {
                    btn.classList.remove('active');
                });
                document.querySelector(`label[for="${this.id}"]`).classList.add('active');
            });
        });
        
        // Initialize active state
        document.querySelector('.btn-check:checked').dispatchEvent(new Event('change'));
    </script>
</body>
</html>