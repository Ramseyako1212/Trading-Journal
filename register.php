<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account | Trading Journal</title>
    
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
            
            <h2 class="auth-title">Create Account</h2>
            <p class="auth-subtitle">Start your journey to trading mastery</p>
            
            <!-- Alert for errors/success -->
            <div id="registerAlert" class="alert-luxury d-none">
                <i class="bi bi-exclamation-circle me-2"></i>
                <span id="alertMessage"></span>
            </div>
            
            <form id="registerForm" action="api/auth/register.php" method="POST">
                <div class="form-group">
                    <label class="form-label-luxury" for="name">Full Name</label>
                    <input type="text" 
                           id="name" 
                           name="name" 
                           class="form-luxury" 
                           placeholder="John Trader"
                           required>
                </div>
                
                <div class="form-group">
                    <label class="form-label-luxury" for="email">Email Address</label>
                    <input type="email" 
                           id="email" 
                           name="email" 
                           class="form-luxury" 
                           placeholder="trader@example.com"
                           required>
                </div>
                
                <div class="form-group">
                    <label class="form-label-luxury" for="password">Password</label>
                    <div class="position-relative">
                        <input type="password" 
                               id="password" 
                               name="password" 
                               class="form-luxury" 
                               placeholder="••••••••"
                               minlength="8"
                               required>
                        <button type="button" 
                                class="btn position-absolute end-0 top-50 translate-middle-y text-muted-custom"
                                onclick="togglePassword('password', 'toggleIcon1')">
                            <i class="bi bi-eye" id="toggleIcon1"></i>
                        </button>
                    </div>
                    <small class="text-muted-custom mt-1 d-block">Minimum 8 characters</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label-luxury" for="password_confirm">Confirm Password</label>
                    <div class="position-relative">
                        <input type="password" 
                               id="password_confirm" 
                               name="password_confirm" 
                               class="form-luxury" 
                               placeholder="••••••••"
                               required>
                        <button type="button" 
                                class="btn position-absolute end-0 top-50 translate-middle-y text-muted-custom"
                                onclick="togglePassword('password_confirm', 'toggleIcon2')">
                            <i class="bi bi-eye" id="toggleIcon2"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label-luxury" for="trading_focus">Primary Trading Focus</label>
                    <select id="trading_focus" name="trading_focus" class="form-luxury form-select-luxury">
                        <option value="forex">Forex</option>
                        <option value="gold">Gold (XAUUSD)</option>
                        <option value="futures">Futures (General)</option>
                        <option value="stocks">Stocks</option>
                        <option value="crypto">Cryptocurrency</option>
                        <option value="options">Options</option>
                    </select>
                </div>
                
                <div class="form-check mb-4">
                    <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
                    <label class="form-check-label text-secondary" for="terms">
                        I agree to the <a href="#" class="text-gold">Terms of Service</a> and <a href="#" class="text-gold">Privacy Policy</a>
                    </label>
                </div>
                
                <button type="submit" class="btn btn-luxury w-100 py-3" id="registerBtn">
                    <span id="btnText">Create Account</span>
                    <span id="btnLoader" class="d-none">
                        <span class="spinner-border spinner-border-sm me-2"></span>
                        Creating account...
                    </span>
                </button>
            </form>
            
            <div class="divider">
                <span>or sign up with</span>
            </div>
            
            <div class="d-flex flex-column gap-3">
                <!-- Google Login Button -->
                <div id="g_id_onload"
                     data-client_id="54002100824-tnk5rgnp09ha4cu8kfuh5071ii1st4tn.apps.googleusercontent.com"
                     data-context="signup"
                     data-ux_mode="popup"
                     data-callback="handleGoogleResponse"
                     data-auto_prompt="false">
                </div>

                <div class="g_id_signin"
                     data-type="standard"
                     data-shape="pill"
                     data-theme="outline"
                     data-text="signup_with"
                     data-size="large"
                     data-logo_alignment="left"
                     data-width="360">
                </div>

                <button class="btn btn-outline-luxury flex-fill py-3 mt-2" style="border-radius: 50px;">
                    <i class="bi bi-apple me-2"></i>Apple
                </button>
            </div>
            
            <p class="text-center text-secondary mt-4 mb-0">
                Already have an account? 
                <a href="login.php" class="text-gold text-decoration-none fw-semibold">Sign in</a>
            </p>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function togglePassword(inputId, iconId) {
            const passwordInput = document.getElementById(inputId);
            const toggleIcon = document.getElementById(iconId);
            
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
        
        // Handle Google Response
        async function handleGoogleResponse(response) {
            const alert = document.getElementById('registerAlert');
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
                    document.getElementById('alertMessage').textContent = data.message || 'Google Registration failed';
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
        document.getElementById('registerForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const btn = document.getElementById('registerBtn');
            const btnText = document.getElementById('btnText');
            const btnLoader = document.getElementById('btnLoader');
            const alert = document.getElementById('registerAlert');
            
            // Validate passwords match
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('password_confirm').value;
            
            if (password !== confirmPassword) {
                document.getElementById('alertMessage').textContent = 'Passwords do not match';
                alert.classList.remove('d-none');
                alert.classList.add('error');
                return;
            }
            
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
                    // Show success and redirect
                    alert.classList.remove('error');
                    alert.classList.add('success');
                    document.getElementById('alertMessage').textContent = 'Account created successfully! Redirecting...';
                    alert.classList.remove('d-none');
                    
                    setTimeout(() => {
                        window.location.href = 'dashboard.php';
                    }, 1500);
                } else {
                    // Show error
                    alert.classList.remove('success');
                    alert.classList.add('error');
                    document.getElementById('alertMessage').textContent = data.message || 'Registration failed';
                    alert.classList.remove('d-none');
                }
            } catch (error) {
                alert.classList.add('error');
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
