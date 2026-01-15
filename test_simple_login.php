<!-- test_simple_login.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FYPMS - Simple Test Login</title>
    <style>
        body { font-family: Arial; padding: 20px; }
        .login-box { max-width: 400px; margin: 0 auto; }
        .user-type { display: flex; gap: 10px; margin-bottom: 20px; }
        .user-type button { padding: 10px; flex: 1; }
        .user-type button.active { background: #007bff; color: white; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; }
        input, select { width: 100%; padding: 8px; }
        button[type="submit"] { background: #007bff; color: white; padding: 10px; width: 100%; }
        .test-info { background: #f8f9fa; padding: 15px; margin: 20px 0; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>FYPMS Test Login</h2>
        
        <div class="test-info">
            <h4>Test Credentials:</h4>
            <p><strong>Password for all: 123456</strong></p>
            <table border="1" cellpadding="5" style="width:100%">
                <tr>
                    <th>User Type</th>
                    <th>Username</th>
                    <th>Redirects To</th>
                </tr>
                <tr>
                    <td>Super Admin</td>
                    <td>superadmin</td>
                    <td>/superadmin/dashboard.php</td>
                </tr>
                <tr>
                    <td>Admin</td>
                    <td>cs_head</td>
                    <td>/admin/dashboard.php</td>
                </tr>
                <tr>
                    <td>Teacher</td>
                    <td>T001</td>
                    <td>/teacher/dashboard.php</td>
                </tr>
                <tr>
                    <td>Student</td>
                    <td>UGR13610</td>
                    <td>/student/dashboard.php</td>
                </tr>
            </table>
        </div>
        
        <form action="index.php" method="POST">
            <div class="form-group">
                <label>Username:</label>
                <input type="text" name="username" placeholder="Enter username" required>
            </div>
            
            <div class="form-group">
                <label>Password:</label>
                <input type="password" name="password" value="123456" required>
            </div>
            
            <div class="form-group">
                <label>User Type:</label>
                <select name="user_type" required>
                    <option value="superadmin">Super Admin</option>
                    <option value="admin">Admin</option>
                    <option value="teacher">Teacher</option>
                    <option value="student" selected>Student</option>
                </select>
            </div>
            
            <button type="submit" name="login">Login</button>
        </form>
    </div>
</body>
</html>