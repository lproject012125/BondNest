// admin_refresh.js - Handles auto-refreshing all stats cards
document.addEventListener('DOMContentLoaded', function() {
    // Set up automatic stats refresh for sidebar cards
    function refreshAllStats() {
        console.log('Refreshing all stats cards...');
        
        // Add timestamp to prevent caching
        fetch('get_stats.php?t=' + new Date().getTime()) 
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.error) {
                    console.error('Error fetching stats:', data.error);
                    return;
                }
                
                // Get current values to check for changes
                const oldUsers = parseInt(document.getElementById('total-users-value').textContent.replace(/,/g, ''));
                const oldPosts = parseInt(document.getElementById('total-posts-value').textContent.replace(/,/g, ''));
                const oldTodayPosts = parseInt(document.getElementById('today-posts-value').textContent.replace(/,/g, ''));
                const oldTodayActions = parseInt(document.getElementById('today-actions-value').textContent.replace(/,/g, ''));
                
                // Update total users with animation if changed
                const usersEl = document.getElementById('total-users-value');
                const newUsers = parseInt(data.stats.total_users);
                if (newUsers !== oldUsers) {
                    animateValueChange(usersEl, oldUsers, newUsers);
                    console.log('Users updated:', oldUsers, '->', newUsers);
                }
                
                // Update total posts with animation if changed
                const postsEl = document.getElementById('total-posts-value');
                const newPosts = parseInt(data.stats.total_posts);
                if (newPosts !== oldPosts) {
                    animateValueChange(postsEl, oldPosts, newPosts);
                    console.log('Posts updated:', oldPosts, '->', newPosts);
                }
                
                // Update today's posts with animation if changed
                const todayPostsEl = document.getElementById('today-posts-value');
                const newTodayPosts = parseInt(data.stats.today_posts);
                if (newTodayPosts !== oldTodayPosts) {
                    animateValueChange(todayPostsEl, oldTodayPosts, newTodayPosts);
                    console.log('Today\'s posts updated:', oldTodayPosts, '->', newTodayPosts);
                }
                
                // Update today's actions with animation if changed
                const todayActionsEl = document.getElementById('today-actions-value');
                const newTodayActions = parseInt(data.stats.today_actions);
                if (newTodayActions !== oldTodayActions) {
                    animateValueChange(todayActionsEl, oldTodayActions, newTodayActions);
                    console.log('Today\'s actions updated:', oldTodayActions, '->', newTodayActions);
                }
                
                // Update change indicators
                updateChangeIndicator('total-users-change', data.percent_changes.users);
                updateChangeIndicator('total-posts-change', data.percent_changes.posts);
                updateChangeIndicator('today-posts-change', data.percent_changes.today_posts);
                updateChangeIndicator('today-actions-change', data.percent_changes.today_actions);
            })
            .catch(error => {
                console.error('Error refreshing stats:', error);
            });
    }
    
    // Animate value changes
    function animateValueChange(element, oldValue, newValue) {
        if (!element) return;
        
        // First highlight the element 
        element.classList.add('flash-update');
        
        // Find and highlight the parent stat card for better visibility
        const statCard = element.closest('.stat-card');
        if (statCard) {
            statCard.classList.add('updating');
            setTimeout(() => {
                statCard.classList.remove('updating');
            }, 1500);
        }
        
        // Animate from old to new value
        const duration = 1000; // 1 second animation
        const start = performance.now();
        
        function updateNumber(timestamp) {
            const elapsed = timestamp - start;
            const progress = Math.min(elapsed / duration, 1);
            
            // Ease in/out animation function
            const easeProgress = progress < 0.5 
                ? 2 * progress * progress 
                : -1 + (4 - 2 * progress) * progress;
            
            // Calculate current value in the animation
            const currentValue = Math.round(oldValue + (newValue - oldValue) * easeProgress);
            
            // Update the element with formatted number
            element.textContent = numberWithCommas(currentValue);
            
            // Continue animation if not finished
            if (progress < 1) {
                requestAnimationFrame(updateNumber);
            } else {
                // Animation complete, remove highlight
                setTimeout(() => {
                    element.classList.remove('flash-update');
                }, 500);
            }
        }
        
        requestAnimationFrame(updateNumber);
    }
    
    // Format numbers with commas
    function numberWithCommas(x) {
        return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }
    
    // Update change indicators
    function updateChangeIndicator(elementId, percentChange) {
        const element = document.getElementById(elementId);
        if (!element) return;
        
        const isPositive = percentChange >= 0;
        
        // Update class
        element.className = isPositive ? 'change positive' : 'change negative';
        
        // Update icon
        const icon = element.querySelector('i');
        if (icon) {
            icon.className = isPositive ? 'bi bi-arrow-up' : 'bi bi-arrow-down';
        }
        
        // Update text
        const span = element.querySelector('span');
        if (span) {
            span.textContent = Math.abs(percentChange) + '% from ' + 
                (elementId.includes('today') ? 'yesterday' : 'last month');
        }
    }
    
    // Add CSS for animating number changes
    const style = document.createElement('style');
    style.textContent = `
        @keyframes flashUpdate {
            0%, 50%, 100% { color: inherit; }
            25%, 75% { color: var(--success); }
        }
        
        .flash-update {
            animation: flashUpdate 1.5s ease;
        }
        
        .stat-card.updating {
            box-shadow: 0 0 15px rgba(76, 201, 240, 0.3);
            transition: box-shadow 0.3s ease;
        }
    `;
    document.head.appendChild(style);
    
    // Make refreshStats available globally
    window.refreshAdminStats = refreshAllStats;
    
    // *** IMPORTANT: Override the existing refreshStats function ***
    // This ensures all admin actions use our animated version
    window.refreshStats = refreshAllStats;
    
    // Call refreshStats immediately on page load
    refreshAllStats();
    
    // Start periodic stats refresh (every 5 seconds)
    setInterval(refreshAllStats, 5000);
    
    console.log('Admin stats auto-refresh enabled - cards will update every 5 seconds');
    
    // Also trigger refresh when a post gets updated
    document.addEventListener('postUpdated', function() {
        console.log('Post updated event detected, refreshing stats...');
        refreshAllStats();
    });
    
    // Monitor for DOM changes to detect new action buttons being added
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.addedNodes.length) {
                // Check if any added nodes are action buttons or contain them
                mutation.addedNodes.forEach(node => {
                    if (node.nodeType === 1) { // Element node
                        // If the node itself is an action button
                        if (node.classList && node.classList.contains('action-btn')) {
                            attachActionButtonHandler(node);
                        }
                        // If the node contains action buttons
                        const actionButtons = node.querySelectorAll && node.querySelectorAll('.action-btn');
                        if (actionButtons && actionButtons.length) {
                            actionButtons.forEach(attachActionButtonHandler);
                        }
                    }
                });
            }
        });
    });
    
    // Function to attach action button handler
    function attachActionButtonHandler(button) {
        // Only attach if it doesn't already have our handler
        if (!button.dataset.statsHandlerAttached) {
            button.addEventListener('click', function() {
                console.log('Action button clicked:', this.dataset.action);
                // Refresh stats after a delay to allow the server to process the action
                setTimeout(refreshAllStats, 300);
            });
            button.dataset.statsHandlerAttached = 'true';
        }
    }
    
    // Attach handlers to all existing action buttons
    document.querySelectorAll('.action-btn').forEach(attachActionButtonHandler);
    
    // Start observing the document
    observer.observe(document.body, { childList: true, subtree: true });
}); 