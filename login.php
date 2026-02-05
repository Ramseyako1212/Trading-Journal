<?php
require_once 'config/database.php';
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Trading Journal</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    
    <!-- Google Identity Services -->
    <script src="https://accounts.google.com/gsi/client" async defer></script>
</head>
<body>
    <!-- Animated Background -->
    <div class="bg-animated"></div>
    <div class="grid-overlay"></div>
    
    <div class="auth-container">
        <div class="auth-card">
            <!-- Logo -->
            <div class="auth-logo">
                <a href="index.php" class="brand-logo justify-content-center">
                    <div class="logo-icon">
                        <i class="bi bi-graph-up-arrow"></i>
                    </div>
                    Trading Journal
                </a>
            </div>
            
            <h2 class="auth-title">Welcome Back</h2>
            <p class="auth-subtitle">Enter your credentials to access your trading journal</p>
            
            <!-- Alert for errors -->
            <div id="loginAlert" class="alert-luxury error d-none">
                <i class="bi bi-exclamation-circle me-2"></i>
                <span id="alertMessage"></span>
            </div>
            
            <form id="loginForm" action="api/auth/login.php" method="POST">
                <div class="form-group">
                    <label class="form-label-luxury" for="email">Email Address</label>
                    <div class="position-relative">
                        <input type="email" 
                               id="email" 
                               name="email" 
                               class="form-luxury" 
                               placeholder="trader@example.com"
                               required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label-luxury" for="password">Password</label>
                    <div class="position-relative">
                        <input type="password" 
                               id="password" 
                               name="password" 
                               class="form-luxury" 
                               placeholder="••••••••"
                               required>
                        <button type="button" 
                                class="btn position-absolute end-0 top-50 translate-middle-y text-muted-custom"
                                onclick="togglePassword()">
                            <i class="bi bi-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>
                
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="remember" name="remember">
                        <label class="form-check-label text-secondary" for="remember">Remember me</label>
                    </div>
                    <a href="forgot-password.php" class="text-gold text-decoration-none">Forgot password?</a>
                </div>
                
                <button type="submit" class="btn btn-luxury w-100 py-3" id="loginBtn">
                    <span id="btnText">Sign In</span>
                    <span id="btnLoader" class="d-none">
                        <span class="spinner-border spinner-border-sm me-2"></span>
                        Signing in...
                    </span>
                </button>
            </form>
            
            <div class="divider">
                <span>or continue with</span>
            </div>
            
            <div class="d-flex flex-column gap-3">
                <!-- Google Login Button -->
                <div id="g_id_onload"
                     data-client_id="<?php echo getenv('GOOGLE_CLIENT_ID'); ?>"
                     data-context="signin"
                     data-ux_mode="popup"
                     data-callback="handleGoogleResponse"
                     data-auto_prompt="false"
                     data-auto_select="false"
                     data-itp_support="true"
                     data-use_fedcm_for_prompt="true">
                </div>

                <div class="g_id_signin"
                     data-type="standard"
                     data-shape="pill"
                     data-theme="outline"
                     data-text="signin"
                     data-size="large"
                     data-logo_alignment="left"
                     data-width="360">
                </div>

                <button class="btn btn-outline-luxury flex-fill py-3 mt-2" style="border-radius: 50px;">
                    <i class="bi bi-apple me-2"></i>Apple
                </button>
            </div>
            
            <p class="text-center text-secondary mt-4 mb-0">
                Don't have an account? 
                <a href="register.php" class="text-gold text-decoration-none fw-semibold">Create one</a>
            </p>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('bi-eye');
                toggleIcon.classList.add('bi-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('bi-eye-slash');
                toggleIcon.classList.add('bi-eye');
            }
        }
        
        // Handle Google Login Response
        async function handleGoogleResponse(response) {
            const alert = document.getElementById('loginAlert');
            alert.classList.add('d-none');
            
            try {
                const formData = new FormData();
                formData.append('credential', response.credential);
                
                const res = await fetch('api/auth/google.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await res.json();
                
                if (data.success) {
                    window.location.href = data.redirect;
                } else {
                    alert.classList.remove('success');
                    alert.classList.add('error');
                    document.getElementById('alertMessage').textContent = data.message || 'Google Login failed';
                    alert.classList.remove('d-none');
                }
            } catch (error) {
                console.error('Google Auth Error:', error);
                alert.classList.add('error');
                document.getElementById('alertMessage').textContent = 'An error occurred with Google Login.';
                alert.classList.remove('d-none');
            }
        }
        
        // Form submission
        document.getElementById('loginForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const btn = document.getElementById('loginBtn');
            const btnText = document.getElementById('btnText');
            const btnLoader = document.getElementById('btnLoader');
            const alert = document.getElementById('loginAlert');
            
            // Show loading state
            btnText.classList.add('d-none');
            btnLoader.classList.remove('d-none');
            btn.disabled = true;
            alert.classList.add('d-none');
            
            try {
                const formData = new FormData(this);
                const response = await fetch(this.action, {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Redirect to dashboard
                    window.location.href = 'dashboard.php';
                } else {
                    // Show error
                    document.getElementById('alertMessage').textContent = data.message || 'Invalid credentials';
                    alert.classList.remove('d-none');
                }
            } catch (error) {
                document.getElementById('alertMessage').textContent = 'An error occurred. Please try again.';
                alert.classList.remove('d-none');
            } finally {
                // Reset button
                btnText.classList.remove('d-none');
                btnLoader.classList.add('d-none');
                btn.disabled = false;
            }
        });
    </script>
</body>
</html>
