<?php
session_start();
require_once '../config/database.php';

// If already logged in, redirect to dashboard
if (isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    try {
        // Check if admin exists
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE email = ? AND status = 'active'");
        $stmt->execute([$email]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($admin && $password === $admin['password']) {
            // Login successful
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_name'] = $admin['name'];
            $_SESSION['admin_email'] = $admin['email'];
            
            // Update last login
            $stmt = $pdo->prepare("UPDATE admins SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$admin['id']]);
            
            // Log login activity
            $stmt = $pdo->prepare("
                INSERT INTO admin_activity_logs 
                (admin_id, action, description, ip_address, user_agent)
                VALUES (?, 'login', 'Admin logged in', ?, ?)
            ");
            $stmt->execute([
                $admin['id'],
                $_SERVER['REMOTE_ADDR'],
                $_SERVER['HTTP_USER_AGENT']
            ]);
            
            header("Location: index.php");
            exit();
        } else {
            $error = "Invalid email or password";
        }
    } catch (Exception $e) {
        $error = "Login error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GearGuard | Admin Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #6366f1;
            --primary-light: #818cf8;
            --primary-dark: #4f46e5;
            --dark: #0f172a;
            --sidebar: #1e293b;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-600: #475569;
            --gray-700: #334155;
            --radius-lg: 16px;
            --radius-md: 12px;
            --radius-sm: 8px;
            --shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
            --shadow-lg: 0 20px 25px -5px rgba(0,0,0,0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        body {
            background-color: #f8fafc;
            color: var(--dark);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background-image: 
                radial-gradient(circle at 10% 20%, rgba(99, 102, 241, 0.05) 0%, transparent 20%),
                radial-gradient(circle at 90% 80%, rgba(99, 102, 241, 0.05) 0%, transparent 20%);
        }

        .login-container {
            display: flex;
            width: 100%;
            max-width: 1000px;
            min-height: 600px;
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-lg);
            background: white;
        }

        .login-left {
            flex: 1;
            background: linear-gradient(135deg, var(--sidebar) 0%, #0f172a 100%);
            color: white;
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .login-left::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 200%;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.1) 0%, transparent 70%);
        }

        .login-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 2.5rem;
            position: relative;
            z-index: 1;
        }

        .login-brand .logo-icon {
            font-size: 2.5rem;
            background: rgba(99, 102, 241, 0.2);
            width: 60px;
            height: 60px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-brand h1 {
            font-size: 2rem;
            font-weight: 800;
            letter-spacing: -0.5px;
        }

        .login-brand h1 span {
            color: var(--primary-light);
        }

        .login-features {
            margin-top: 3rem;
            position: relative;
            z-index: 1;
        }

        .feature {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            margin-bottom: 2rem;
        }

        .feature-icon {
            background: rgba(255, 255, 255, 0.1);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .feature-text h3 {
            font-size: 1.1rem;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .feature-text p {
            color: #94a3b8;
            font-size: 0.9rem;
            line-height: 1.5;
        }

        .login-right {
            flex: 1;
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .login-header {
            margin-bottom: 2.5rem;
        }

        .login-header h2 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .login-header p {
            color: var(--gray-600);
            font-size: 1rem;
        }

        .login-form {
            width: 100%;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--gray-700);
            font-size: 0.95rem;
        }

        .input-with-icon {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-400);
            font-size: 1.1rem;
        }

        .form-input {
            width: 100%;
            padding: 14px 16px 14px 48px;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-md);
            font-size: 1rem;
            transition: all 0.3s ease;
            background-color: white;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .form-input::placeholder {
            color: var(--gray-300);
        }

        .password-toggle {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--gray-400);
            cursor: pointer;
            font-size: 1.1rem;
        }

        .password-toggle:hover {
            color: var(--gray-600);
        }

        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--gray-600);
        }

        .remember-checkbox {
            width: 18px;
            height: 18px;
            accent-color: var(--primary);
            cursor: pointer;
        }

        .forgot-link {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .forgot-link:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        .login-button {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            border: none;
            border-radius: var(--radius-md);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .login-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -3px rgba(99, 102, 241, 0.3);
        }

        .login-button:active {
            transform: translateY(0);
        }

        .error-message {
            background-color: #fee2e2;
            color: #991b1b;
            padding: 12px 16px;
            border-radius: var(--radius-sm);
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            border: 1px solid #fecaca;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .error-message i {
            font-size: 1rem;
        }

        .login-footer {
            margin-top: 2rem;
            text-align: center;
            color: var(--gray-600);
            font-size: 0.9rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--gray-200);
        }

        .login-footer a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }

        .login-footer a:hover {
            text-decoration: underline;
        }

        /* Responsive Design */
        @media (max-width: 900px) {
            .login-container {
                flex-direction: column;
                max-width: 500px;
            }
            
            .login-left {
                padding: 2rem;
            }
            
            .login-right {
                padding: 2rem;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 10px;
            }
            
            .login-container {
                min-height: auto;
            }
            
            .login-left, .login-right {
                padding: 1.5rem;
            }
            
            .login-brand {
                flex-direction: column;
                text-align: center;
                gap: 20px;
            }
            
            .feature {
                flex-direction: column;
                text-align: center;
                align-items: center;
            }
            
            .form-options {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
        }

        /* Animation */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .login-right {
            animation: fadeIn 0.6s ease-out;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- Left Panel - Brand & Features -->
        <div class="login-left">
            <div class="login-brand">
                <div class="logo-icon">
                    🛡️
                </div>
                <h1>Gear<span>Guard</span></h1>
            </div>
            
            <div class="login-features">
                <div class="feature">
                    <div class="feature-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div class="feature-text">
                        <h3>Enterprise Security</h3>
                        <p>Military-grade encryption and security protocols to protect your assets</p>
                    </div>
                </div>
                
                <div class="feature">
                    <div class="feature-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="feature-text">
                        <h3>Real-time Analytics</h3>
                        <p>Monitor platform performance with comprehensive dashboards and reports</p>
                    </div>
                </div>
                
                <div class="feature">
                    <div class="feature-icon">
                        <i class="fas fa-users-cog"></i>
                    </div>
                    <div class="feature-text">
                        <h3>Admin Control</h3>
                        <p>Complete control over companies, managers, and platform settings</p>
                    </div>
                </div>
            </div>
            
            <div style="margin-top: auto; position: relative; z-index: 1;">
                <p style="color: #94a3b8; font-size: 0.85rem; text-align: center;">
                    © 2024 GearGuard. All rights reserved.
                </p>
            </div>
        </div>
        
        <!-- Right Panel - Login Form -->
        <div class="login-right">
            <div class="login-header">
                <h2>Super Admin Portal</h2>
                <p>Enter your credentials to access the dashboard</p>
            </div>
            
            <?php if (isset($error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="login.php" class="login-form">
                <div class="form-group">
                    <label for="email" class="form-label">Email Address</label>
                    <div class="input-with-icon">
                        <i class="fas fa-envelope input-icon"></i>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            class="form-input" 
                            placeholder="admin@gearguard.com" 
                            required
                            value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                        >
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-with-icon">
                        <i class="fas fa-lock input-icon"></i>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            class="form-input" 
                            placeholder="••••••••" 
                            required
                        >
                        <button type="button" class="password-toggle" id="togglePassword">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-options">
                    <label class="remember-me">
                        <input type="checkbox" class="remember-checkbox" name="remember">
                        <span>Remember me</span>
                    </label>
                    <a href="#" class="forgot-link">Forgot password?</a>
                </div>
                
                <button type="submit" class="login-button">
                    <i class="fas fa-sign-in-alt"></i>
                    <span>Sign In to Dashboard</span>
                </button>
            </form>
            
            <div class="login-footer">
                <p>Need help? <a href="mailto:support@gearguard.com">Contact support</a></p>
                <p style="margin-top: 10px; font-size: 0.8rem; color: var(--gray-400);">
                    <i class="fas fa-lock"></i> Your credentials are encrypted and secured
                </p>
            </div>
        </div>
    </div>

    <script>
        // Password toggle visibility
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        const eyeIcon = togglePassword.querySelector('i');
        
        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            // Toggle eye icon
            eyeIcon.classList.toggle('fa-eye');
            eyeIcon.classList.toggle('fa-eye-slash');
        });
        
        // Form validation
        const loginForm = document.querySelector('.login-form');
        loginForm.addEventListener('submit', function(event) {
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            
            if (!email || !password) {
                event.preventDefault();
                alert('Please fill in all fields');
                return false;
            }
            
            // Add loading state to button
            const submitBtn = this.querySelector('.login-button');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Signing in...</span>';
            submitBtn.disabled = true;
        });
        
        // Add focus effects to inputs
        const inputs = document.querySelectorAll('.form-input');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.style.transform = 'scale(1.01)';
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'scale(1)';
            });
        });
        
        // Auto-focus email field
        document.getElementById('email').focus();
    </script>
</body>
</html>