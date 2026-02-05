<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trading Journal | Elite Trade Tracking</title>
    
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
    
    <!-- Animate On Scroll -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
</head>
<body>
    <!-- Animated Background -->
    <div class="bg-animated"></div>
    <div class="grid-overlay"></div>
    
    <!-- 2026 Background Blobs -->
    <div class="spatial-blob blob-gold"></div>
    <div class="spatial-blob blob-cyan"></div>
    
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-luxury fixed-top" id="mainNav">
        <div class="container">
            <a class="brand-logo" href="index.php">
                <div class="logo-icon">
                    <i class="bi bi-graph-up-arrow"></i>
                </div>
                Trading Journal
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item">
                        <a class="nav-link nav-link-luxury active" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link nav-link-luxury" href="#features">Features</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link nav-link-luxury" href="#analytics">Analytics</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link nav-link-luxury" href="#pricing">Pricing</a>
                    </li>
                    <li class="nav-item ms-lg-3">
                        <a class="btn btn-outline-luxury" href="login.php">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Login
                        </a>
                    </li>
                    <li class="nav-item ms-2">
                        <a class="btn btn-luxury" href="register.php">
                            Get Started <i class="bi bi-arrow-right ms-2"></i>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- Hero Section -->
    <section class="hero-section">
        <!-- Floating Elements -->
        <div class="floating-element floating-chart d-none d-lg-block">
            <div class="d-flex align-items-end gap-1" style="height: 60px;">
                <div style="width: 12px; height: 30%; background: linear-gradient(to top, #10B981, #34D399); border-radius: 3px;"></div>
                <div style="width: 12px; height: 60%; background: linear-gradient(to top, #10B981, #34D399); border-radius: 3px;"></div>
                <div style="width: 12px; height: 40%; background: linear-gradient(to top, #EF4444, #F87171); border-radius: 3px;"></div>
                <div style="width: 12px; height: 80%; background: linear-gradient(to top, #10B981, #34D399); border-radius: 3px;"></div>
                <div style="width: 12px; height: 55%; background: linear-gradient(to top, #10B981, #34D399); border-radius: 3px;"></div>
                <div style="width: 12px; height: 45%; background: linear-gradient(to top, #EF4444, #F87171); border-radius: 3px;"></div>
            </div>
            <small class="text-muted-custom d-block mt-2">Weekly P&L</small>
        </div>
        
        <div class="floating-element floating-stats d-none d-lg-block">
            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="badge-long">XAUUSD</span>
                <span class="text-gold fw-bold">+$2,450</span>
            </div>
            <small class="text-muted-custom">Gold / US Dollar</small>
            <div class="mt-2">
                <small class="text-green"><i class="bi bi-arrow-up"></i> 85% Win Rate</small>
            </div>
        </div>
        
        <div class="container">
            <div class="hero-content">
                <div class="hero-subtitle">
                    <span>Premium Trading Analytics</span>
                </div>
                
                <h1 class="display-luxury">
                    Master Your<br>Trading Journey
                </h1>
                
                <p class="hero-description">
                    The ultimate luxury trading journal designed for serious traders. 
                    Track crude oil futures, analyze performance, and elevate your trading 
                    with AI-powered insights and stunning visualizations.
                </p>
                
                <div class="hero-cta">
                    <a href="register.php" class="btn btn-luxury btn-lg">
                        <i class="bi bi-rocket-takeoff me-2"></i>
                        Start Free Trial
                    </a>
                    <a href="#features" class="btn btn-outline-luxury btn-lg">
                        <i class="bi bi-play-circle me-2"></i>
                        Watch Demo
                    </a>
                </div>
                
                <!-- Stats Row -->
                <div class="row g-4 mt-5 pt-4">
                    <div class="col-6 col-md-3">
                        <div class="stat-card">
                            <div class="stat-value">50K+</div>
                            <div class="stat-label">Trades Logged</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-card">
                            <div class="stat-value">98%</div>
                            <div class="stat-label">Satisfaction</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-card">
                            <div class="stat-value">2.5x</div>
                            <div class="stat-label">Avg. Performance</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-card">
                            <div class="stat-value">24/7</div>
                            <div class="stat-label">Live Support</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Features Section -->
    <section class="features-section py-6" id="features">
        <div class="container">
            <div class="text-center mb-5" data-aos="fade-up">
                <h6 class="text-cyan text-uppercase fw-semibold mb-3" style="letter-spacing: 0.2em;">Features</h6>
                <h2 class="display-5 fw-bold mb-4">Everything You Need to <span class="text-gold">Excel</span></h2>
                <p class="text-secondary mx-auto" style="max-width: 600px;">
                    Powerful tools designed specifically for crude oil and futures traders.
                </p>
            </div>
            
            <div class="bento-grid">
                <!-- Large Card -->
                <div class="bento-item large" data-aos="fade-right" data-aos-delay="100">
                    <div class="feature-icon mb-4" style="font-size: 2.5rem; color: var(--gold);">
                        <i class="bi bi-journal-richtext"></i>
                    </div>
                    <h3 class="fw-bold mb-3">Smart Journaling</h3>
                    <p class="text-secondary">
                        Log every trade with detailed entry/exit prices, position sizing, execution quality, and emotional state tracking. Our smart engine automatically calculates R-multiples and commissions.
                    </p>
                    <div class="mt-4 p-3 bg-glass rounded-4 border-glass d-inline-flex gap-3 align-items-center">
                        <div class="text-green small"><i class="bi bi-check-circle-fill me-1"></i> Auto P&L</div>
                        <div class="text-cyan small"><i class="bi bi-check-circle-fill me-1"></i> Risk Analysis</div>
                    </div>
                </div>
                
                <!-- Medium Card -->
                <div class="bento-item medium" data-aos="fade-left" data-aos-delay="200">
                    <div class="feature-icon mb-3" style="font-size: 2rem; color: var(--cyan);">
                        <i class="bi bi-bar-chart-line"></i>
                    </div>
                    <h4 class="fw-bold">Advanced Analytics</h4>
                    <p class="text-secondary small">
                        Interactive charts showing win rate, expectancy, drawdown, and performance metrics across different time sessions.
                    </p>
                </div>
                
                <!-- Tall Card -->
                <div class="bento-item tall" data-aos="fade-up" data-aos-delay="300">
                    <div class="feature-icon mb-3" style="font-size: 2rem; color: var(--gold);">
                        <i class="bi bi-calendar-week"></i>
                    </div>
                    <h4 class="fw-bold">Performance Calendar</h4>
                    <p class="text-secondary small">
                        Track your daily consistency with a visual heat map. Instantly see your best trading days and win/loss patterns.
                    </p>
                    <div class="mt-auto pt-4 text-center opacity-50">
                        <i class="bi bi-grid-3x3-gap" style="font-size: 3rem;"></i>
                    </div>
                </div>
                
                <!-- Small Cards -->
                <div class="bento-item" data-aos="fade-up" data-aos-delay="400">
                    <div class="feature-icon mb-3" style="font-size: 1.5rem; color: var(--cyan);">
                        <i class="bi bi-camera"></i>
                    </div>
                    <h5 class="fw-bold smaller">Screenshots</h5>
                    <p class="text-secondary smaller">Automatic gallery of your executions.</p>
                </div>
                
                <div class="bento-item" data-aos="fade-up" data-aos-delay="500">
                    <div class="feature-icon mb-3" style="font-size: 1.5rem; color: var(--gold);">
                        <i class="bi bi-lightning-charge"></i>
                    </div>
                    <h5 class="fw-bold smaller">Speed Sync</h5>
                    <p class="text-secondary smaller">Real-time MT5 integration.</p>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Analytics Preview Section -->
    <section class="py-6" id="analytics">
        <div class="container">
            <div class="row align-items-center g-5">
                <div class="col-lg-6">
                    <h6 class="text-cyan text-uppercase fw-semibold mb-3" style="letter-spacing: 0.2em;">Analytics</h6>
                    <h2 class="display-5 fw-bold mb-4">Data-Driven <span class="text-gold">Insights</span></h2>
                    <p class="text-secondary mb-4">
                        Transform your trading data into actionable insights. Our analytics engine 
                        processes every trade to reveal patterns, weaknesses, and opportunities.
                    </p>
                    
                    <div class="row g-3 mb-4">
                        <div class="col-6">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle bg-success bg-opacity-25 p-2">
                                    <i class="bi bi-check-lg text-green"></i>
                                </div>
                                <span>Win Rate Analysis</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle bg-success bg-opacity-25 p-2">
                                    <i class="bi bi-check-lg text-green"></i>
                                </div>
                                <span>Equity Curves</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle bg-success bg-opacity-25 p-2">
                                    <i class="bi bi-check-lg text-green"></i>
                                </div>
                                <span>Drawdown Tracking</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle bg-success bg-opacity-25 p-2">
                                    <i class="bi bi-check-lg text-green"></i>
                                </div>
                                <span>Time Analysis</span>
                            </div>
                        </div>
                    </div>
                    
                    <a href="register.php" class="btn btn-luxury">
                        Try Analytics Free <i class="bi bi-arrow-right ms-2"></i>
                    </a>
                </div>
                
                <div class="col-lg-6">
                    <div class="glass-card p-4">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="mb-0"><i class="bi bi-graph-up text-gold me-2"></i>Performance Overview</h5>
                            <select class="form-select-luxury" style="width: auto; padding: 0.5rem 2rem 0.5rem 1rem;">
                                <option>This Month</option>
                                <option>This Week</option>
                                <option>This Year</option>
                            </select>
                        </div>
                        
                        <!-- Mock Chart -->
                        <div class="position-relative" style="height: 200px; background: linear-gradient(180deg, rgba(16, 185, 129, 0.1) 0%, transparent 100%); border-radius: 12px; overflow: hidden;">
                            <svg width="100%" height="100%" preserveAspectRatio="none">
                                <defs>
                                    <linearGradient id="lineGradient" x1="0%" y1="0%" x2="100%" y2="0%">
                                        <stop offset="0%" style="stop-color:#FFD700"/>
                                        <stop offset="100%" style="stop-color:#00D4FF"/>
                                    </linearGradient>
                                </defs>
                                <path d="M0,150 Q50,140 100,120 T200,100 T300,80 T400,60 T500,50 T600,30" 
                                      fill="none" stroke="url(#lineGradient)" stroke-width="3"/>
                            </svg>
                            <div class="position-absolute top-0 end-0 p-3">
                                <span class="badge bg-success bg-opacity-25 text-green">
                                    <i class="bi bi-arrow-up"></i> +24.5%
                                </span>
                            </div>
                        </div>
                        
                        <div class="row g-3 mt-3">
                            <div class="col-4">
                                <div class="text-center">
                                    <div class="fs-4 fw-bold text-gold">68%</div>
                                    <small class="text-muted-custom">Win Rate</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="text-center">
                                    <div class="fs-4 fw-bold text-green">2.4R</div>
                                    <small class="text-muted-custom">Avg Winner</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="text-center">
                                    <div class="fs-4 fw-bold text-cyan">1.8</div>
                                    <small class="text-muted-custom">Profit Factor</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- CTA Section -->
    <section class="py-6">
        <div class="container">
            <div class="glass-card text-center py-5">
                <h2 class="display-5 fw-bold mb-4">Ready to Transform Your Trading?</h2>
                <p class="text-secondary mb-4 mx-auto" style="max-width: 500px;">
                    Join thousands of traders who have elevated their performance with Trading Journal.
                </p>
                <div class="d-flex gap-3 justify-content-center flex-wrap">
                    <a href="register.php" class="btn btn-luxury btn-lg">
                        <i class="bi bi-rocket-takeoff me-2"></i>
                        Start Free Trial
                    </a>
                    <a href="login.php" class="btn btn-outline-luxury btn-lg">
                        Already have an account?
                    </a>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Footer -->
    <footer class="footer-luxury">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="footer-brand">Trading Journal</div>
                    <p class="text-secondary mb-4">
                        The premium trading journal for serious futures traders. 
                        Track, analyze, and master your trading.
                    </p>
                    <div class="d-flex gap-3">
                        <a href="#" class="btn btn-outline-luxury btn-sm rounded-circle" style="width: 40px; height: 40px; padding: 0; display: flex; align-items: center; justify-content: center;">
                            <i class="bi bi-twitter-x"></i>
                        </a>
                        <a href="#" class="btn btn-outline-luxury btn-sm rounded-circle" style="width: 40px; height: 40px; padding: 0; display: flex; align-items: center; justify-content: center;">
                            <i class="bi bi-discord"></i>
                        </a>
                        <a href="#" class="btn btn-outline-luxury btn-sm rounded-circle" style="width: 40px; height: 40px; padding: 0; display: flex; align-items: center; justify-content: center;">
                            <i class="bi bi-youtube"></i>
                        </a>
                    </div>
                </div>
                <div class="col-6 col-lg-2">
                    <h6 class="text-gold mb-3">Product</h6>
                    <ul class="list-unstyled footer-links">
                        <li class="mb-2"><a href="#">Features</a></li>
                        <li class="mb-2"><a href="#">Analytics</a></li>
                        <li class="mb-2"><a href="#">Pricing</a></li>
                        <li class="mb-2"><a href="#">Changelog</a></li>
                    </ul>
                </div>
                <div class="col-6 col-lg-2">
                    <h6 class="text-gold mb-3">Resources</h6>
                    <ul class="list-unstyled footer-links">
                        <li class="mb-2"><a href="#">Documentation</a></li>
                        <li class="mb-2"><a href="#">Tutorials</a></li>
                        <li class="mb-2"><a href="#">Blog</a></li>
                        <li class="mb-2"><a href="#">Support</a></li>
                    </ul>
                </div>
                <div class="col-6 col-lg-2">
                    <h6 class="text-gold mb-3">Company</h6>
                    <ul class="list-unstyled footer-links">
                        <li class="mb-2"><a href="#">About</a></li>
                        <li class="mb-2"><a href="#">Careers</a></li>
                        <li class="mb-2"><a href="#">Contact</a></li>
                        <li class="mb-2"><a href="#">Press</a></li>
                    </ul>
                </div>
                <div class="col-6 col-lg-2">
                    <h6 class="text-gold mb-3">Legal</h6>
                    <ul class="list-unstyled footer-links">
                        <li class="mb-2"><a href="#">Privacy</a></li>
                        <li class="mb-2"><a href="#">Terms</a></li>
                        <li class="mb-2"><a href="#">Cookies</a></li>
                    </ul>
                </div>
            </div>
            
            <hr class="border-secondary my-4">
            
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
                <p class="text-muted-custom mb-0">
                    &copy; 2026 Trading Journal. All rights reserved.
                </p>
                <p class="text-muted-custom mb-0">
                    Made with <i class="bi bi-heart-fill text-red"></i> for traders
                </p>
            </div>
        </div>
    </footer>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Animate On Scroll JS -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    
    <!-- Custom JS -->
    <script>
        // Initialize AOS
        AOS.init({
            duration: 1000,
            once: true,
            easing: 'ease-out-cubic'
        });

        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.getElementById('mainNav');
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });
        
        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    </script>
</body>
</html>
