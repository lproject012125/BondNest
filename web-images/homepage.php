<?php
session_start();

// If user is not logged in, redirect to login page
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

require_once __DIR__ . '/../db_connection.php';

// Get user info
$user_id = $_SESSION['user_id'];

$sql = "SELECT first_name, last_name, username, profile_picture FROM users WHERE id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    echo "User not found!";
    exit();
}

// Set default profile picture if not set
$profile_picture = !empty($user['profile_picture']) ? $user['profile_picture'] : './web-images/pfp.jpg';

// Combine full name
$full_name = htmlspecialchars($user['first_name'] . ' ' . $user['last_name']);
$username = htmlspecialchars($user['username']);
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BondNest | Social Media</title>
    <!-- WE USE BOOTSTRAPICON FOR ICONS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">    <!-- LINKING THE CSS FILE -->
    <link rel="stylesheet" href="./style.css">
</head>
<body>
    <nav>
        <div class="container">
            <h2 class="log">
                <img src="./web-images/bn-logo.png" alt="Logo" class="logo-img">
                BondNest
            </h2>
            <div class="search-bar">
                <i class="bi bi-search"></i>
                <input type="search" placeholder="Search for a person, groups, pages, trends and projects...">
            </div>
            <div class="create">
                <a class="menu-item" id="messages-notification">
                    <i class="bi bi-chat">
                        <small class="notification-count"></small>
                    </i>
                </a>
                <a class="menu-item" id="notifications">
                    <i class="bi bi-bell">
                        <small class="notification-count"></small>
                    </i>
                </a>
                <div class="profile_picture">
    <img src="<?php echo !empty($user['profile_picture']) ? $user['profile_picture'] : './web-images/pfp.jpg'; ?>">
</div>
            </div>
        </div>
    </nav>
    <!--------------------------------------------------------------MAIN ----------------------------------------------------->
    <main>
        <div class="container">
            <!-- LEFT SECTION -->
            <div class="left">
                <a class="profile">
                <div class="profile_picture">
                <img src="<?php echo (!empty($user['profile_picture'])) ? $user['profile_picture'] : './web-images/default_pfp.jpg'; ?>" alt="Profile Picture">
            </div>
            <div class="handle">
                <h4><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h4>
                <p class="text-muted">@<?php echo htmlspecialchars($user['username']); ?></p>
            </div>

                </a>

                <!------------------------------------------------ SIDEBAR SECTION ------------------------------------------------------->
                <div class="sidebar">
                    <a class="menu-item active">
                        <i class="bi bi-house"></i>
                        <h3>Home</h3>
                    </a>
                    <a class="menu-item">
                        <i class="bi bi-person-square"></i>
                        <h3>Profile</h3>
                    </a>
                    <a class="menu-item">
                        <i class="bi bi-shop-window"></i>
                        <h3>Marketplace</h3>
                    </a>
                    <a class="menu-item" id="theme">
                        <i class="bi bi-sliders"></i>
                        <h3>Theme</h3>
                    </a>
                    <a class="menu-item">
                        <i class="bi bi-gear"></i>
                        <h3>Settings</h3>
                    </a>
                </div>

                <!-- LAST PART OF SIDEBAR -->
                <label for="create_post" class="btn btn-primary">Create Post</label>
            </div>
            <!------------------------------------------------- END ------------------------------------------------------->

            <!--------------------------------------------- MIDDLE SECTION -------------------------------------------------->
            <div class="middle">
                <!-----------------------------------------STORIES SECTION ------------------------------------------------->
                

                
                <!------------------------------------------- END --------------------------------------------->
                <div class="create-post">
                    <div class="input-area">
                    <div class="profile_picture">
                <img src="<?php echo (!empty($user['profile_picture'])) ? $user['profile_picture'] : './web-images/default_pfp.jpg'; ?>" alt="Profile Picture">
            </div>
            <input type="text" placeholder="What's on your mind, <?php echo htmlspecialchars($user['first_name']); ?>?" name="create-post">

                    </div>
                    <div class="options">
                        <div class="option">
                            <i class="bi bi-image" style="color: lightgreen;"></i>
                            <span>Photo/video</span>
                        </div>
                        <div class="option">
                            <i class="bi bi-emoji-smile" style="color: gold;"></i>
                            <span>Feeling/activity</span>
                        </div>
                    </div>
                </div>
                <!------------------------------------------- FEED SECTION -------------------------------------------->
                <div class="feeds">
                     <!------------------------------------------- FEED SECTION 1-------------------------------------------->
                    <div class="feed">
                        <div class="head">
                            <div class="user">
                                <div class="profile_picture">
                                    <img src="./web-images/pfp.jpg">
                                </div>
                                <div class="info">
                                    <h3>Lana Rose</h3>
                                    <small>Dubai, 15 MINUTES AGO</small>
                                </div>
                            </div>
                            <span class="edit">
                                <i class="bi bi-three-dots"></i>
                            </span>
                        </div>

                        <div class="photo">
                            <img src="./web-images/pfp.jpg">
                        </div>

                        <div class="action-buttons">
                            <div class="interaction-buttons">
                                <span><i class="bi bi-heart"></i></span>
                                <span><i class="bi bi-chat-dots"></i></span>
                                <span><i class="bi bi-share"></i></span>
                                
                            </div>
                            <div class="bookmark">
                                <span><i class="bi bi-bookmark"></i></span>
                            </div>
                        </div>

                        <div class="liked-by">
                            <span><img src="./web-images/notif1.jpg"></span>
                            <span><img src="./web-images/notif2.jpg"></span>
                            <span><img src="./web-images/notif3.jpg"></span>
                            <p>Liked by <b>Ernest Achiever</b> and <b>2,230 others</b></p>
                        </div>

                        <div class="caption">
                            <p><b>Lana Rose</b> Lorem impsum dolor sit quisquam eius. <span class="harsh-tag">#lifestyle</span> </p>
                        </div>

                        <div class="comments text-muted">View all 277 comments</div>
                    </div>

                    <!------------------------------------ END OF FEED ------------------------------------------------>
                </div>
                <!--------------------------------------- END OF FEEDS ------------------------------------------------>
            </div>
            <!---------------------------------------- END OF MID SECTION     ------------------------------------------->

            <!------------------------------------ FOR NOTIFICATION DISPLAY --------------------------------------------->
            <div class="notifications-display">
                <div class="heading">
                    <h4>Notifications</h4><i class="bi bi-bell-fill"></i>
                </div>
                <div class="message">
                    <div class="profile_picture">
                        <img src="./web-images/notif1.jpg">
                    </div>
                    <div class="message-body">
                        <h5>Miguel Isles</h5>
                        <p class="text-muted">accepted your friend request</p>
                        <small class="text-muted">1 HOUR AGO</small>
                    </div>
                </div>
                <div class="message">
                    <div class="profile_picture">
                        <img src="./web-images/notif2.jpg">
                    </div>
                    <div class="message-body">
                        <h5>Zakari Cuenca</h5>
                        <p class="text-muted">accepted your friend request</p>
                        <small class="text-muted">1 DAY AGO</small>
                    </div>
                </div>
                <div class="message">
                    <div class="profile_picture">
                        <img src="./web-images/notif3.jpg">
                    </div>
                    <div class="message-body">
                        <h5>Oliver Valenzuela</h5>
                        <p class="text-muted">accepted your friend request</p>
                        <small class="text-muted">3 HOURS AGO</small>
                    </div>
                </div>
            </div>
            

            <!------------------------------------------ RIGHT SECTION --------------------------------------------->
            <div class="right">
                <div class="messages">
                    <div class="heading">
                        <h4>Messages</h4><i class="bi bi-pencil-square"></i>
                    </div>
                    <div class="search-bar">
                        <i class="bi bi-search"></i>
                        <input type="search" placeholder="Search Messages" id="message-search">
                    </div>
                    <div class="category">
                        <h6 class="active">Primary</h6>
                        <h6>General</h6>
                        <h6 class="message-requests">Requests(2)</h6>
                    </div>
                    <div class="message">
                        <div class="profile_picture">
                            <img src="./web-images/notif2.jpg">
                        </div>
                        <div class="message-body">
                            <h5>Zakari Cuenca</h5>
                            <p class="text-muted">Just woke up bruh</p>
                        </div>
                    </div>
                </div>
                <div class="friend-requests-section">
                    <div class="heading">
                        <h4>Requests</h4>
                    </div>
                    <div class="request-card">
                        <div class="user-info">
                            <div class="profile_picture">
                                <img src="./web-images/notif1.jpg" alt="Miguel Isles">
                            </div>
                            <div class="details">
                                <h5>Miguel Isles</h5>
                                <p class="text-muted"><span><img src="./web-images/friend_icon.png" style="width: 1rem; height: 1rem; vertical-align: middle; margin-right: 0.2rem;"></span> 12 mutual friends</p>
                            </div>
                        </div>
                        <div class="actions">
                            <button class="btn btn-primary">Accept</button>
                            <button class="btn">Decline</button>
                        </div>
                    </div>
                </div>
                <div class="contacts-section">
                    <div class="heading">
                        <h4>Contacts</h4>
                        <div class="icons">
                            <i class="bi bi-search"></i>
                            <i class="bi bi-three-dots"></i>
                        </div>
                    </div>
                    <div class="contact-list">
                        <div class="contact-card">
                            <div class="user-info">
                                <div class="profile_picture">
                                    <img src="./web-images/notif1.jpg" alt="KD Kenneth Ace Tolentino">
                                </div>
                                <div class="details">
                                    <h5>KD Kenneth Ace Tolentino</h5>
                                </div>
                            </div>
                        </div>
                        <div class="contact-card">
                            <div class="user-info">
                                <div class="profile_picture">
                                    <img src="./web-images/notif2.jpg" alt="Zakari Cuenca-Ausa">
                                </div>
                                <div class="details">
                                    <h5>Zakari Cuenca-Ausa</h5>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!---------------------------------------- END OF THE RIGHT SECTION ----------------------------------------->
        </div>
    </main>

    <div class="customize-theme">
        <div class="card">
            <h2>Customize Your Preferred View</h2>
            <p class="text-muted" >Adjust the font size, color, and background to your preferences.</p>

            <!----------------------- FOR FONT SIZES ----------------------->
            <div class="font-size">
                <h4>Font Size</h4>
                <div>
                    <h6>Aa</h6>
                <div class="choose-size">
                    <span class="font-size-1"></span>
                    <span class="font-size-2 active"></span>
                    <span class="font-size-3"></span>
                    <span class="font-size-4"></span>
                    <span class="font-size-5"></span>
                </div>
                <h3>Aa</h3>
                </div>
            </div>

            <!--------------------- FOR PRIMARY COLORS ---------------------->
            <div class="color">
                <h4>Color</h4>
                <div class="choose-color">
                    <span class="color-1 active"></span>
                    <span class="color-2"></span>
                    <span class="color-3"></span>
                    <span class="color-4"></span>
                    <span class="color-5"></span>
                </div>
            </div>

            <!---------------------- FOR BACKGROUND COLORS ---------------------->
            <div class="background">
                <h4>Background</h4>
                <div class="choose-bg">
                    <div class="bg-1 active">
                        <span></span>
                        <h5 for="bg-1">Light</h5>
                    </div>
                    <div class="bg-2">
                        <span></span>
                        <h5>Dim</h5>
                    </div>
                    <div class="bg-3">
                        <span></span>
                        <h for="bg-3">Lights Out</h>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="./script.js"></script>
</body>
</html>