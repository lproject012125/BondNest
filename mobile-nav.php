<?php
// Get the current page name
$current_page = basename($_SERVER['PHP_SELF']);
?>

<style>
/* Mobile Navigation Styles */
.mobile-nav {
    display: none;
    position: fixed;
    left: 20px;
    top: 50%;
    transform: translateY(-50%);
    z-index: 1000;
    background: var(--color-white);
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    padding: 10px;
}

.mobile-nav .menu-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    width: 60px;
    height: 60px;
    margin: 5px 0;
    border-radius: 8px;
    text-decoration: none;
    transition: all 0.3s ease;
}

.mobile-nav .menu-item i {
    font-size: 1.5rem;
    margin: 0;
    color: var(--color-gray);
}

.mobile-nav .menu-item h3 {
    font-size: 0.7rem;
    margin: 5px 0 0 0;
    color: var(--color-gray);
}

.mobile-nav .menu-item:hover,
.mobile-nav .menu-item.active {
    background: var(--color-primary);
}

.mobile-nav .menu-item:hover i,
.mobile-nav .menu-item:hover h3,
.mobile-nav .menu-item.active i,
.mobile-nav .menu-item.active h3 {
    color: white;
}

@media screen and (max-width: 1200px) {
    .left {
        display: none !important;
    }
    
    .mobile-nav {
        display: block;
    }
    
    .mobile-nav .menu-item {
        width: 50px;
        height: 50px;
    }
    
    .mobile-nav .menu-item i {
        font-size: 1.3rem;
    }
    
    .mobile-nav .menu-item h3 {
        font-size: 0.6rem;
    }
}

@media screen and (max-width: 480px) {
    .mobile-nav {
        left: 10px;
    }
    
    .mobile-nav .menu-item {
        width: 45px;
        height: 45px;
    }
    
    .mobile-nav .menu-item i {
        font-size: 1.2rem;
    }
    
    .mobile-nav .menu-item h3 {
        font-size: 0.5rem;
    }
}
</style>

<!-- Mobile Navigation -->
<div class="mobile-nav">
    <a class="menu-item <?php echo $current_page == 'homepage.php' ? 'active' : ''; ?>" href="homepage.php">
        <i class="bi bi-house"></i>
        <h3>Home</h3>
    </a>
    <a class="menu-item <?php echo $current_page == 'profile-page.php' ? 'active' : ''; ?>" href="profile-page.php">
        <i class="bi bi-person-square"></i>
        <h3>Profile</h3>
    </a>
    <a class="menu-item <?php echo $current_page == 'message.php' ? 'active' : ''; ?>" href="message.php">
        <i class="bi bi-chat-dots"></i>
        <h3>Message</h3>
    </a>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const menuItems = document.querySelectorAll('.mobile-nav .menu-item');
    
    const changeActiveItem = () => {
        menuItems.forEach(item => {
            item.classList.remove('active');
        });
    };

    menuItems.forEach(item => {
        item.addEventListener('click', function() {
            changeActiveItem();
            this.classList.add('active');
        });
    });
});
</script> 