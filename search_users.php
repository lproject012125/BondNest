<?php

require_once 'db_connection.php';
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: process_login.php");
    exit();
}



// Get current user ID
$current_user_id = $_SESSION['user_id'];

// Handle search request
if (isset($_GET['search'])) {
    $search_term = $_GET['search'] . '%';
    
    // Search for users whose name or username matches the search term
    // Exclude Admin users from search results (hardcoded)
    $sql = "SELECT id, first_name, last_name, username, profile_picture FROM users 
            WHERE (first_name LIKE ? OR last_name LIKE ? OR username LIKE ?) 
            AND id != ?
            AND username != 'Admin' 
            AND username != 'admin'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$search_term, $search_term, $search_term, $current_user_id]);
    $search_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return search results as HTML for AJAX requests
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        if (count($search_results) > 0) {
            foreach ($search_results as $user): ?>
                <a href="message.php?user_id=<?php echo $user['id']; ?>" class="navbar-search-result">
                    <img src="<?php echo !empty($user['profile_picture']) ? $user['profile_picture'] : './web-images/default_profile.png'; ?>" class="result-avatar">
                    <div class="result-details">
                        <div class="result-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                        <div class="result-username">@<?php echo htmlspecialchars($user['username']); ?></div>
                    </div>
                </a>
            <?php endforeach;
        } else {
            echo '<div class="no-results">No users found</div>';
        }
        exit;
    }
}

// If not an AJAX request or no search parameter, redirect to homepage
header("Location: homepage.php");
exit();
?>
