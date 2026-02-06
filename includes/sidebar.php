<!-- Sidebar -->
<aside class="sidebar-luxury" id="sidebar">
    <div class="sidebar-brand">
        <a href="dashboard.php" class="brand-logo">
            <div class="logo-icon">
                <i class="bi bi-graph-up-arrow"></i>
            </div>
            Trading Journal
        </a>
    </div>
    
    <nav>
        <ul class="sidebar-nav">
            <li class="sidebar-nav-item">
                <a href="dashboard.php" class="sidebar-nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="bi bi-speedometer2"></i>
                    Dashboard
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="journal.php" class="sidebar-nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'journal.php' ? 'active' : ''; ?>">
                    <i class="bi bi-journal-richtext"></i>
                    Trade Journal
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="analytics.php" class="sidebar-nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'analytics.php' ? 'active' : ''; ?>">
                    <i class="bi bi-bar-chart-line"></i>
                    Analytics
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="calendar.php" class="sidebar-nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'calendar.php' ? 'active' : ''; ?>">
                    <i class="bi bi-calendar3"></i>
                    Calendar
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="strategies.php" class="sidebar-nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'strategies.php' ? 'active' : ''; ?>">
                    <i class="bi bi-lightbulb"></i>
                    Strategies
                </a>
            </li>
            
            <li class="mt-4 mb-2">
                <small class="text-muted-custom text-uppercase px-3" style="font-size: 0.7rem; letter-spacing: 0.1em;">Account</small>
            </li>
            
            <li class="sidebar-nav-item">
                <a href="settings.php" class="sidebar-nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>">
                    <i class="bi bi-gear"></i>
                    Settings
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="api/auth/logout.php" class="sidebar-nav-link">
                    <i class="bi bi-box-arrow-left"></i>
                    Logout
                </a>
            </li>
        </ul>
    </nav>
</aside>