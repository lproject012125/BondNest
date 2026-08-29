<?php

require_once 'db_connection.php';
// Make sure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get fresh user info from database if logged in
$profile_picture = '';

if (isset($_SESSION['user_id'])) {
        include 'db_connection.php';
    
    // Get updated user profile picture
    $user_id = $_SESSION['user_id'];
    $sql = "SELECT profile_picture, first_name, last_name FROM users WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    $row = $stmt->fetch();
    
    if ($row) {
        $profile_picture = !empty($row['profile_picture']) ? $row['profile_picture'] : '';
        $_SESSION['profile_picture'] = $profile_picture;
        $_SESSION['first_name'] = $row['first_name'] ?? '';
        $_SESSION['last_name'] = $row['last_name'] ?? '';
    }
}

// Define getInitialsHtml here so it's available before sidebar.php is included
if (!function_exists('getInitialsHtml')) {
    function getInitialsHtml($first, $last, $size = 44) {
        $f = mb_strtoupper(mb_substr(trim($first), 0, 1));
        $l = mb_strtoupper(mb_substr(trim($last), 0, 1));
        $initials = $f . $l;
        $colors = ['#2B9E9E','#3CB5A6','#E67E22','#3498DB','#9B59B6','#E74C3C','#1ABC9C','#2C3E50'];
        $hash = 0;
        $name = trim($first . $last);
        for ($i = 0; $i < mb_strlen($name); $i++) { $hash = ($hash * 31 + mb_ord(mb_substr($name, $i, 1))) & 0x7FFFFFFF; }
        $bg = $colors[$hash % count($colors)];
        return '<div class="initials-avatar" style="width:'.$size.'px;height:'.$size.'px;border-radius:50%;background:'.$bg.';color:#fff;display:flex;align-items:center;justify-content:center;font-weight:600;font-size:'.($size * 0.38).'px;font-family:Poppins,sans-serif;flex-shrink:0;letter-spacing:0.5px;">'.$initials.'</div>';
    }
}
?>

<nav class="navbar">
    <div class="navbar-container">
        <div class="navbar-main">
            <div class="navbar-brand">
                <a href="homepage.php" class="navbar-logo">
                    <img src="./web-images/bn-logo.png" class="logo-img" alt="BondNest Logo">
                    <span>BondNest</span>
                </a>
            </div>
            <div class="search-bar">
                <i class="bi bi-search"></i>
                <input type="text" id="navbarSearchInput" placeholder="Search for a person...">
                <div id="navbarSearchResults" class="navbar-search-results"></div>
            </div>
        </div>
        
        <div class="navbar-right">
            <?php
            // Get notification counts
            $notification_count = 0;
            $warning_count = 0;
            $deleted_count = 0;
            
            if (isset($_SESSION['user_id'])) {
                $user_id = $_SESSION['user_id'];
                
                // Count unread warning notifications
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND type = 'post_warning' AND is_read = 0");
                $stmt->execute([$user_id]);
                $row = $stmt->fetch();
                if ($row) {
                    $warning_count = $row['count'];
                }
                
                // Count unread deleted post notifications
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND type = 'post_deleted' AND is_read = 0");
                $stmt->execute([$user_id]);
                $row = $stmt->fetch();
                if ($row) {
                    $deleted_count = $row['count'];
                }
                
                // Total notification count
                $notification_count = $warning_count + $deleted_count;
            }
            ?>
            
            <div class="profile-dropdown">
                <a href="#" class="profile-link" id="profileDropdownToggle">
                    <div class="profile-picture">
                        <?php if (!empty($profile_picture)): ?>
                            <img src="<?php echo $profile_picture; ?>" alt="Profile Picture">
                        <?php else: ?>
                            <?php echo getInitialsHtml($_SESSION['first_name'] ?? '', $_SESSION['last_name'] ?? '', 40); ?>
                        <?php endif; ?>
                    </div>
                </a>
                
                <div class="profile-dropdown-content" id="profileDropdownContent">
                    <a href="profile-page.php" class="profile-dropdown-item">
                        <i class="bi bi-person"></i>
                        <span>Profile</span>
                    </a>
                    <a href="message.php" class="profile-dropdown-item">
                        <i class="bi bi-chat-dots"></i>
                        <span>Message</span>
                    </a>
                    <a href="settings.php" class="profile-dropdown-item">
                        <i class="bi bi-gear"></i>
                        <span>Settings</span>
                    </a>
                    <div class="profile-dropdown-divider"></div>
                    <a href="index.php" class="profile-dropdown-item profile-dropdown-logout" id="dropdownLogoutBtn">
                        <i class="bi bi-box-arrow-right"></i>
                        <span>Log out</span>
                    </a>
                </div>
            </div>
            
            <div class="notification-dropdown">
                <a href="#" class="notification-icon" id="notificationDropdownToggle">
                    <i class="bi bi-bell-fill"></i>
                    <?php if ($notification_count > 0): ?>
                        <span class="notification-badge"><?php echo $notification_count; ?></span>
                    <?php endif; ?>
                </a>
                
                <div class="notification-dropdown-content" id="notificationDropdownContent">
                    <div class="notification-header">
                        <h3>Notifications</h3>
                        <?php if ($notification_count > 0): ?>
                            <span class="notification-count"><?php echo $notification_count; ?> new</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="notification-body">
                        <?php if ($warning_count > 0): ?>
                            <a href="warnings.php" class="notification-item warning">
                                <div class="notification-icon">
                                    <i class="bi bi-exclamation-triangle-fill"></i>
                                </div>
                                <div class="notification-text">
                                    <p>You have <?php echo $warning_count; ?> post warning<?php echo $warning_count > 1 ? 's' : ''; ?>.</p>
                                    <span class="notification-time">Click to view</span>
                                </div>
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($deleted_count > 0): ?>
                            <a href="deleted_posts.php" class="notification-item deleted">
                                <div class="notification-icon">
                                    <i class="bi bi-trash-fill"></i>
                                </div>
                                <div class="notification-text">
                                    <p>You have <?php echo $deleted_count; ?> deleted post notification<?php echo $deleted_count > 1 ? 's' : ''; ?>.</p>
                                    <span class="notification-time">Click to view</span>
                                </div>
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($notification_count == 0): ?>
                            <div class="notification-empty">
                                <p>No new notifications</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="notification-footer">
                        <a href="warnings.php">View all warnings</a>
                        <a href="deleted_posts.php">View all deleted posts</a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="navbar-toggle">
            <span class="navbar-toggle-icon"></span>
        </div>
    </div>
</nav>

<!-- Mobile menu dropdown -->
<div class="mobile-menu-dropdown" id="mobileMenuDropdown">
    <a href="profile-page.php" class="mobile-menu-item">
        <i class="bi bi-person-circle"></i>
        <span>Profile</span>
    </a>
    <a href="#" class="mobile-menu-item" id="mobileNotificationToggle">
        <i class="bi bi-bell-fill"></i>
        <span>Notification</span>
        <?php if ($notification_count > 0): ?>
            <span class="notification-badge"><?php echo $notification_count; ?></span>
        <?php endif; ?>
    </a>
    <a href="index.php" class="mobile-menu-item" id="mobileLogoutButton">
        <i class="bi bi-box-arrow-right"></i>
        <span>Logout</span>
    </a>
</div>

<style>
    /* Reset some default styles */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: 'Poppins', sans-serif;
    }

    /* Navbar styles */
    .navbar {
        background-color: white;
        color: white;
        padding: 8px 20px;
        position: sticky;
        top: 0;
        z-index: 1000;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 0;
    }

    .navbar-container {
        display: flex;
        justify-content: space-between;
        align-items: center;
        width: 100%;
    }

    .navbar-main {
        display: flex;
        align-items: center;
        gap: 30px;
        flex-grow: 1;
    }

    /* Search bar styles */
    .navbar .navbar-container .navbar-main .search-bar {
        display: flex !important;
        align-items: center !important;
        background-color: #f5f5f5 !important;
        border-radius: 25px !important;
        padding: 12px 20px !important;
        width: 500px !important;
        height: 50px !important;
        margin-left: 0px !important;
        transition: all 0.3s ease !important;
        position: relative !important;
        max-width: none !important;
        flex: none !important;
        left: auto !important;
        right: auto !important;
        transform: none !important;
        float: none !important;
    }

    .navbar .navbar-container .navbar-main .search-bar:focus-within {
        width: 450px !important;
        background-color: #d8d8d8 !important;
    }

    .navbar .navbar-container .navbar-main .search-bar i {
        color: #000000 !important;
        margin-right: 15px !important;
        font-size: 1.2rem !important;
    }

    .navbar .navbar-container .navbar-main .search-bar input {
        border: none !important;
        background: transparent !important;
        outline: none !important;
        width: 100% !important;
        font-family: 'Poppins', sans-serif !important;
        font-size: 1rem !important;
        padding: 5px 0 !important;
        color: #333 !important;
        box-shadow: none !important;
        -webkit-appearance: none !important;
    }

    .navbar-brand {
        display: flex;
        align-items: center;
        z-index: 2;
    }

    .navbar-logo {
        color: #008080;
        text-decoration: none;
        font-size: 1.8rem;
        font-weight: 600;
        transition: color 0.3s ease;
        display: flex;
        align-items: center;
        margin-left: 20px;
    }

    .navbar-logo:hover {
        color: #00cccc;
    }

    .logo-img {
        height: 2em;
        width: auto;
        margin-right: 10px;
    }

    /* Logo text that will be hidden on small screens */
    .navbar-logo span {
        transition: opacity 0.3s ease;
    }

    /* Media queries for responsive design */
    @media (max-width: 1200px) {
        .navbar .navbar-container .navbar-main .search-bar {
            width: 400px !important;
        }
        
        .navbar .navbar-container .navbar-main .search-bar:focus-within {
            width: 350px !important;
        }
    }

    @media (max-width: 992px) {
        .navbar .navbar-container .navbar-main .search-bar {
            width: 300px !important;
        }
        
        .navbar .navbar-container .navbar-main .search-bar:focus-within {
            width: 250px !important;
        }
    }

    @media (max-width: 768px) {
        .navbar .navbar-container .navbar-main .search-bar {
            width: 200px !important;
        }
        
        .navbar .navbar-container .navbar-main .search-bar:focus-within {
            width: 180px !important;
        }

        .navbar-logo span {
            display: none;
        }

        .logo-img {
            margin-right: 0;
        }
    }

    @media (max-width: 576px) {
        .navbar .navbar-container .navbar-main .search-bar {
            width: 150px !important;
        }
        
        .navbar .navbar-container .navbar-main .search-bar:focus-within {
            width: 130px !important;
        }
    }

    /* Right side navigation items */
    .navbar-right {
        display: flex;
        align-items: center;
        gap: 16px;
        margin-left: auto;
        margin-right: 180px;
    }

    .profile-dropdown {
        position: relative;
    }

    .profile-link {
        text-decoration: none;
        display: block;
        transition: transform 0.2s ease;
    }
    
    .profile-link:hover {
        transform: scale(1.08);
    }
    
    .profile-picture {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        overflow: hidden;
        border: 2px solid rgba(0, 128, 128, 0.3);
        transition: box-shadow 0.25s ease, border-color 0.25s ease;
        cursor: pointer;
    }
    
    .profile-link:hover .profile-picture,
    .profile-dropdown:hover .profile-picture {
        box-shadow: 0 0 0 3px rgba(0, 128, 128, 0.2);
        border-color: var(--color-primary, #008080);
    }

    .profile-picture img {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
    }

    /* Profile Dropdown Menu */
    .profile-dropdown-content {
        display: none;
        position: absolute;
        right: 0;
        top: calc(100% + 8px);
        width: 220px;
        background: white;
        border-radius: 12px;
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
        border: 1px solid rgba(0, 0, 0, 0.06);
        z-index: 2000;
        overflow: hidden;
        animation: profileDropIn 0.2s ease;
    }

    .profile-dropdown-content.show {
        display: block;
    }

    @keyframes profileDropIn {
        from { opacity: 0; transform: translateY(-8px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .profile-dropdown-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 18px;
        text-decoration: none;
        color: #3a4a4a;
        font-size: 0.92rem;
        font-weight: 500;
        transition: background 0.18s ease, color 0.18s ease;
    }

    .profile-dropdown-item i {
        font-size: 1.15rem;
        color: #5a6a6a;
        width: 22px;
        text-align: center;
        transition: color 0.18s ease;
    }

    .profile-dropdown-item:hover {
        background: rgba(0, 128, 128, 0.06);
        color: var(--color-primary, #008080);
    }

    .profile-dropdown-item:hover i {
        color: var(--color-primary, #008080);
    }

    .profile-dropdown-divider {
        height: 1px;
        background: #eef2f2;
        margin: 4px 0;
    }

    .profile-dropdown-logout {
        color: #c0392b;
    }

    .profile-dropdown-logout i {
        color: #c0392b;
    }

    .profile-dropdown-logout:hover {
        background: rgba(192, 57, 43, 0.06);
        color: #c0392b;
    }

    .profile-dropdown-logout:hover i {
        color: #c0392b;
    }

    /* Mobile menu toggle */
    .navbar-toggle {
        display: none;
        cursor: pointer;
    }

    .navbar-toggle-icon {
        display: block;
        width: 25px;
        height: 3px;
        background-color: #008080;
        position: relative;
        transition: all 0.3s ease;
    }

    .navbar-toggle-icon::before,
    .navbar-toggle-icon::after {
        content: '';
        position: absolute;
        width: 100%;
        height: 100%;
        background-color: #008080;
        transition: all 0.3s ease;
    }

    .navbar-toggle-icon::before {
        top: -8px;
    }

    .navbar-toggle-icon::after {
        top: 8px;
    }

    @media (max-width: 768px) {
        .search-bar {
            display: none;
        }
        .navbar-toggle {
            display: block;
        }
        .navbar-main {
            gap: 15px;
        }
        .navbar-right {
            display: none;
        }
    }
    
    /* Override any conflicting search bar styles */
    .navbar .navbar-main .search-bar {
        display: flex !important;
        margin-left: 15px !important;
        position: relative !important;
        width: 350px !important;
        max-width: none !important;
    }
    
    @media (min-width: 769px) {
        .search-bar {
            display: flex;
        }
    }

    /* Notification dropdown styles */
    .notification-dropdown {
        position: relative;
    }
    
    .notification-icon {
        display: inline-block;
        position: relative;
        font-size: 1.4rem;
        color: #333;
        cursor: pointer;
        padding: 8px;
        border-radius: 50%;
        transition: background-color 0.25s ease, color 0.25s ease, transform 0.25s ease;
    }

    .notification-icon:hover {
        background-color: var(--color-primary-light, rgba(0, 128, 128, 0.1));
        color: var(--color-primary, #008080);
        transform: scale(1.12);
    }

    .notification-icon:active {
        transform: scale(0.95);
    }
    
    .notification-badge {
        position: absolute;
        top: -5px;
        right: -5px;
        background-color: #f59f0b;
        color: white;
        font-size: 0.7rem;
        font-weight: bold;
        border-radius: 50%;
        width: 18px;
        height: 18px;
        display: flex;
        justify-content: center;
        align-items: center;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        animation: pulse 1.5s infinite;
    }
    
    @keyframes pulse {
        0% {
            transform: scale(1);
            box-shadow: 0 0 0 0 rgba(245, 159, 11, 0.7);
        }
        70% {
            transform: scale(1.1);
            box-shadow: 0 0 0 10px rgba(245, 159, 11, 0);
        }
        100% {
            transform: scale(1);
            box-shadow: 0 0 0 0 rgba(245, 159, 11, 0);
        }
    }
    
    .notification-dropdown-content {
        position: absolute;
        right: 0;
        top: 100%;
        width: 350px;
        background-color: white;
        border-radius: 8px;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
        display: none;
        z-index: 1000;
        overflow: hidden;
    }
    
    .notification-dropdown-content.show {
        display: block;
        animation: fadeIn 0.3s;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .notification-header {
        background-color: #f8f9fa;
        padding: 15px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid #eee;
    }
    
    .notification-header h3 {
        margin: 0;
        font-size: 1.1rem;
        color: #333;
    }
    
    .notification-count {
        background-color: #f59f0b;
        color: white;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 0.8rem;
        font-weight: 600;
    }
    
    .notification-body {
        max-height: 400px;
        overflow-y: auto;
    }
    
    .notification-item {
        display: flex;
        padding: 15px;
        border-bottom: 1px solid #eee;
        text-decoration: none;
        color: #333;
        transition: background-color 0.2s ease;
    }
    
    .notification-item:hover {
        background-color: #f8f9fa;
    }
    
    .notification-item.warning .notification-icon i {
        color: #f59f0b;
    }
    
    .notification-item.deleted .notification-icon i {
        color: #d62839;
    }
    
    .notification-icon {
        margin-right: 15px;
        font-size: 1.2rem;
        color: #777;
    }
    
    .notification-text p {
        margin: 0 0 5px 0;
        font-size: 0.9rem;
    }
    
    .notification-time {
        font-size: 0.8rem;
        color: #777;
    }
    
    .notification-empty {
        padding: 20px;
        text-align: center;
        color: #777;
    }
    
    .notification-footer {
        padding: 12px;
        background-color: #f8f9fa;
        border-top: 1px solid #eee;
        display: flex;
        justify-content: space-around;
    }
    
    .notification-footer a {
        color: #008080;
        text-decoration: none;
        font-size: 0.9rem;
        transition: color 0.2s ease;
    }
    
    .notification-footer a:hover {
        color: #00cccc;
        text-decoration: underline;
    }
    
    /* Navbar search results styles */
    .navbar-search-results {
        position: absolute;
        top: 50px;
        left: 0;
        width: 100%;
        max-height: 300px;
        overflow-y: auto;
        background-color: white;
        border-radius: 8px;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
        display: none;
        z-index: 1000;
        padding: 10px 0;
    }
    
    .navbar-search-result {
        display: flex;
        align-items: center;
        padding: 10px 15px;
        text-decoration: none;
        color: #333;
        transition: background-color 0.2s ease;
    }
    
    .navbar-search-result:hover {
        background-color: #f5f5f5;
    }
    
    .result-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
        margin-right: 12px;
        border: 1px solid #eee;
    }
    
    .result-details {
        flex: 1;
    }
    
    .result-name {
        font-weight: 600;
        font-size: 0.9rem;
        margin-bottom: 3px;
    }
    
    .result-username {
        font-size: 0.8rem;
        color: #666;
    }
    
    .no-results {
        padding: 15px;
        text-align: center;
        color: #666;
        font-size: 0.9rem;
    }

    .mobile-menu-dropdown {
        display: none;
        position: absolute;
        top: 60px;
        left: 10px;
        right: 10px;
        background: linear-gradient(135deg, #f8fafc 60%, #e0e7ef 100%);
        border-radius: 32px;
        box-shadow: 0 8px 32px rgba(0,0,0,0.12);
        z-index: 2000;
        flex-direction: column;
        align-items: stretch;
        padding: 2rem 1rem 1.5rem 1rem;
        font-size: 1.5rem;
        font-weight: 600;
        text-align: center;
        opacity: 0;
        transform: translateY(-20px);
        pointer-events: none;
        transition: opacity 0.25s cubic-bezier(.4,2,.6,1), transform 0.25s cubic-bezier(.4,2,.6,1);
    }
    .mobile-menu-dropdown.active {
        display: flex;
        opacity: 1;
        transform: translateY(0);
        pointer-events: auto;
    }
    .mobile-menu-item {
        display: flex;
        align-items: center;
        justify-content: flex-start;
        gap: 1.2rem;
        padding: 1.1rem 1.2rem;
        color: #222;
        text-decoration: none;
        border-bottom: 1px solid #e3e3e3;
        font-size: 1.25rem;
        border-radius: 18px;
        margin: 0.2rem 0;
        background: transparent;
        transition: background 0.18s, color 0.18s, box-shadow 0.18s;
        box-shadow: none;
        position: relative;
    }
    .mobile-menu-item:last-child {
        border-bottom: none;
    }
    .mobile-menu-item i {
        font-size: 1.7rem;
        color: #008080;
        flex-shrink: 0;
        transition: color 0.18s;
    }
    .mobile-menu-item .notification-badge {
        background: #f59f0b;
        color: #fff;
        border-radius: 50%;
        font-size: 1rem;
        padding: 0.2em 0.6em;
        margin-left: 0.5em;
        vertical-align: middle;
        position: absolute;
        right: 1.5rem;
        top: 50%;
        transform: translateY(-50%);
    }
    .mobile-menu-item:hover, .mobile-menu-item:active {
        background: #e6f7f7;
        color: #008080;
        box-shadow: 0 2px 8px rgba(0,128,128,0.07);
    }
    .mobile-menu-item:hover i, .mobile-menu-item:active i {
        color: #00b3b3;
    }
    @media (max-width: 768px) {
        .mobile-menu-dropdown {
            display: none;
        }
        .mobile-menu-dropdown.active {
            display: flex;
        }
        .navbar-right {
            display: none !important;
        }
    }

    @media (max-width: 1000px) {
        .modal-overlay {
            align-items: stretch !important;
            justify-content: stretch !important;
        }
        .modal-dialog {
            width: 100vw !important;
            max-width: 100vw !important;
            height: 100vh !important;
            max-height: 100vh !important;
            margin: 0 !important;
            border-radius: 0 !important;
            display: flex;
            flex-direction: column;
        }
        .modal-content {
            width: 100vw !important;
            height: 100vh !important;
            max-width: 100vw !important;
            max-height: 100vh !important;
            min-height: 0 !important;
            border-radius: 0 !important;
            aspect-ratio: unset !important;
            padding: 0 !important;
            display: flex;
            flex-direction: column;
        }
        .modal-header {
            flex: 0 0 auto;
            padding: 15px 10px !important;
            background: #f8f9fa;
            z-index: 2;
        }
        .modal-body {
            flex: 1 1 auto;
            min-height: 0;
            overflow-y: auto !important;
            padding: 10px !important;
            z-index: 1;
        }
    }
    @media (max-width: 600px) {
        .modal-header, .modal-body {
            padding-left: 5px !important;
            padding-right: 5px !important;
        }
        .modal-title {
            font-size: 1.1rem !important;
        }
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Navbar toggle functionality for mobile
    const navbarToggle = document.querySelector('.navbar-toggle');
    const navbarRight = document.querySelector('.navbar-right');
    
    if (navbarToggle && navbarRight) {
        navbarToggle.addEventListener('click', function() {
            navbarRight.style.display = navbarRight.style.display === 'flex' ? 'none' : 'flex';
        });
    }
    
    // Profile dropdown functionality
    const profileToggle = document.getElementById('profileDropdownToggle');
    const profileContent = document.getElementById('profileDropdownContent');
    
    if (profileToggle && profileContent) {
        profileToggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            profileContent.classList.toggle('show');
        });

        document.addEventListener('click', function(e) {
            if (!profileToggle.contains(e.target) && !profileContent.contains(e.target)) {
                profileContent.classList.remove('show');
            }
        });

        profileContent.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    }
    
    // Notification dropdown functionality
    const notificationToggle = document.getElementById('notificationDropdownToggle');
    const notificationContent = document.getElementById('notificationDropdownContent');
    
    if (notificationToggle && notificationContent) {
        notificationToggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            notificationContent.classList.toggle('show');
            // Close profile dropdown if open
            if (profileContent) profileContent.classList.remove('show');
        });

        document.addEventListener('click', function(e) {
            if (!notificationToggle.contains(e.target) && !notificationContent.contains(e.target)) {
                notificationContent.classList.remove('show');
            }
        });

        notificationContent.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    }

    // Close both dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        if (profileContent && !profileToggle.contains(e.target) && !profileContent.contains(e.target)) {
            profileContent.classList.remove('show');
        }
        if (notificationContent && !notificationToggle.contains(e.target) && !notificationContent.contains(e.target)) {
            notificationContent.classList.remove('show');
        }
    });
    
    // Search bar functionality
    const searchBar = document.querySelector('.search-bar');
    const searchInput = document.getElementById('navbarSearchInput');
    const searchResults = document.getElementById('navbarSearchResults');
    
    if (searchBar && searchInput && searchResults) {
        searchInput.addEventListener('focus', function() {
            searchBar.style.width = '450px';
            searchBar.style.backgroundColor = '#d8d8d8';
        });
        
        let searchTimeout = null;
        searchInput.addEventListener('input', function() {
            const query = this.value.trim();
            
            if (searchTimeout) {
                clearTimeout(searchTimeout);
            }
            
            if (query.length > 0) {
                searchTimeout = setTimeout(() => {
                    searchResults.innerHTML = '<div class="no-results">Searching...</div>';
                    searchResults.style.display = 'block';
                    
                    fetch('search_users.php?search=' + encodeURIComponent(query), {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => response.text())
                    .then(html => {
                        if (html.trim() === '') {
                            searchResults.innerHTML = '<div class="no-results">No users found</div>';
                        } else {
                            searchResults.innerHTML = html;
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching search results:', error);
                        searchResults.innerHTML = '<div class="no-results">Error searching users</div>';
                    });
                }, 300);
            } else {
                searchResults.innerHTML = '';
                searchResults.style.display = 'none';
            }
        });
        
        document.addEventListener('click', function(e) {
            if (searchResults && !searchInput.contains(e.target) && !searchResults.contains(e.target)) {
                searchResults.style.display = 'none';
                searchBar.style.width = '500px';
                searchBar.style.backgroundColor = '#f5f5f5';
            }
        });
    }
    
    // Mobile menu toggle
    const mobileMenuToggle = document.querySelector('.navbar-toggle');
    const mobileMenuDropdown = document.getElementById('mobileMenuDropdown');
    
    if (mobileMenuToggle && mobileMenuDropdown) {
        mobileMenuToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            mobileMenuDropdown.classList.toggle('active');
        });
        document.addEventListener('click', function(e) {
            if (mobileMenuDropdown.classList.contains('active') && !mobileMenuDropdown.contains(e.target) && !mobileMenuToggle.contains(e.target)) {
                mobileMenuDropdown.classList.remove('active');
            }
        });
    }

    // Mobile logout button
    const mobileLogoutButton = document.getElementById('mobileLogoutButton');
    
    if (mobileLogoutButton) {
        mobileLogoutButton.addEventListener('click', function(e) {
            e.preventDefault();
            mobileMenuDropdown.classList.remove('active');
            window.location.href = 'index.php';
        });
    }

    // Mobile notification bell for mobile
    const mobileNotificationToggle = document.getElementById('mobileNotificationToggle');
    if (mobileNotificationToggle) {
        mobileNotificationToggle.addEventListener('click', function(e) {
            e.preventDefault();
            window.location.href = 'warnings.php';
        });
    }

    // Activity tracking — runs on every page so the user's online status is always current
    (function() {
        function updateMyActivity(status) {
            fetch('update_activity.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ status: status })
            }).catch(function() {});
        }

        // Mark active on page load
        updateMyActivity('active');

        // Heartbeat every 30 seconds
        setInterval(function() {
            if (document.visibilityState === 'visible') {
                updateMyActivity('active');
            }
        }, 30000);

        // Tab focus / blur
        document.addEventListener('visibilitychange', function() {
            if (document.visibilityState === 'visible') {
                updateMyActivity('active');
            } else {
                updateMyActivity('inactive');
            }
        });

        // Mark offline on close
        window.addEventListener('beforeunload', function() {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'update_activity.php', false);
            xhr.setRequestHeader('Content-Type', 'application/json');
            xhr.send(JSON.stringify({ status: 'offline' }));
        });
    })();
});
</script>