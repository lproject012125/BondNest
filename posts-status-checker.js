/**
 * BondNest Post Status Checker - Enhanced Version
 * Provides real-time monitoring of post status changes without page refresh
 */

class PostStatusChecker {
    constructor(options = {}) {
        // Default settings
        this.settings = {
            checkInterval: 3000,  // Check every 3 seconds for faster updates
            postSelector: '.post-item', // CSS selector for post items
            lastCheckTimestamp: 0,
            debug: true,  // Enable debug by default for easier troubleshooting
            onPostHold: null,     // Callback for on-hold posts
            onPostApprove: null,  // Callback for approved posts
            onPostDeleted: null   // Callback for deleted posts
        };

        // Merge user options with defaults
        Object.assign(this.settings, options);

        this.postIds = [];
        this.statusMap = {};
        this.checkCount = 0;
        this.isFirstCheck = true;
        
        // Initialize
        this.init();
    }

    init() {
        console.log('[StatusChecker] Initializing post status checker...');
        
        // Collect all post IDs on the page
        this.collectPostIds();
        
        // Start the interval checker if we have posts to monitor
        if (this.postIds.length > 0) {
            console.log(`[StatusChecker] Starting monitoring for ${this.postIds.length} posts`);
            
            // Do initialization log based on page type
            if (document.body.classList.contains('homepage')) {
                console.log('[StatusChecker] Homepage mode: Will remove on-hold posts');
            } else if (document.body.classList.contains('profile-page')) {
                console.log('[StatusChecker] Profile page mode: Will show notifications for on-hold posts');
                
                // Apply initial status styling for profile page
                this.applyInitialStatus();
            }
            
            this.startChecker();
        } else {
            console.log('[StatusChecker] No posts found to monitor');
        }
    }

    collectPostIds() {
        // Get all post elements on the page
        const postElements = document.querySelectorAll(this.settings.postSelector);
        
        // Extract post IDs and current status
        this.postIds = [];
        postElements.forEach(post => {
            const postId = post.getAttribute('data-post-id');
            if (postId) {
                this.postIds.push(postId);
                // Store current status (default to 'posted' if not specified)
                const status = post.getAttribute('data-status') || 'posted';
                this.statusMap[postId] = status;
                
                if (this.settings.debug) {
                    console.log(`[StatusChecker] Post ${postId} initial status: ${status}`);
                }
            }
        });

        if (this.settings.debug) {
            console.log('[StatusChecker] Collected posts:', this.postIds);
            console.log('[StatusChecker] Initial status map:', this.statusMap);
        }
    }

    applyInitialStatus() {
        // Apply styles for posts that are already on-hold when the page loads
        console.log('[StatusChecker] Applying initial status styling to existing posts');
        
        let onHoldCount = 0;
        
        const postElements = document.querySelectorAll(this.settings.postSelector);
        postElements.forEach(post => {
            const postId = post.getAttribute('data-post-id');
            const status = post.getAttribute('data-status');
            
            if (status === 'on-hold') {
                onHoldCount++;
                console.log(`[StatusChecker] Found existing on-hold post ${postId}, applying styles`);
                
                // Handle differently based on page type
                if (document.body.classList.contains('homepage')) {
                    // On homepage, remove on-hold posts
                    if (this.settings.onPostHold) {
                        this.settings.onPostHold(post, postId);
                    } else {
                        this.fadeOutAndRemovePost(post);
                    }
                } else {
                    // On profile page, show notification
                    this.applyOnHoldStyle(post, 'This post has been placed on hold by administrators. Please check your notifications for details.');
                }
            }
        });
        
        console.log(`[StatusChecker] Applied initial styling to ${onHoldCount} on-hold posts`);
    }

    startChecker() {
        // Do an immediate initial check
        this.checkPostStatus();
        
        // Set interval for regular checks
        this.intervalId = setInterval(() => {
            this.checkCount++;
            this.checkPostStatus();
        }, this.settings.checkInterval);

        // Add event listener for page visibility changes to optimize checking
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                // Force immediate check when tab becomes visible again
                this.checkPostStatus();
            }
        });
    }

    stopChecker() {
        if (this.intervalId) {
            clearInterval(this.intervalId);
            this.intervalId = null;
        }
    }

    checkPostStatus() {
        // Skip if no post IDs
        if (this.postIds.length === 0) return;
        
        // Log every 5th check or first check
        if (this.checkCount % 5 === 0 || this.isFirstCheck) {
            console.log(`[StatusChecker] Checking post status (check #${this.checkCount})...`);
            if (document.body.classList.contains('profile-page')) {
                console.log(`[StatusChecker] Profile page: Monitoring ${this.postIds.length} posts`);
            }
            this.isFirstCheck = false;
        }

        // Build the API request URL with cache-busting
        const url = `get_posts_status.php?post_ids=${this.postIds.join(',')}&last_check=${this.settings.lastCheckTimestamp}&_=${Date.now()}`;
        
        // Make the fetch request
        fetch(url)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Network response error: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Update timestamp for future requests
                    this.settings.lastCheckTimestamp = data.timestamp;
                    
                    // Process post status changes if any posts returned
                    if (data.posts && data.posts.length > 0) {
                        if (this.settings.debug) {
                            console.log(`[StatusChecker] Found ${data.posts.length} updated posts:`, data.posts);
                        }
                        this.processStatusUpdates(data.posts);
                    }
                } else {
                    console.error('[StatusChecker] Error from server:', data.error || 'Unknown error');
                }
            })
            .catch(error => {
                console.error('[StatusChecker] Request failed:', error);
            });
    }

    processStatusUpdates(posts) {
        // Skip if no posts or no update
        if (!posts || posts.length === 0) return;
        
        posts.forEach(post => {
            const postId = post.id;
            const newStatus = post.status;
            const oldStatus = this.statusMap[postId];
            const holdMessage = post.hold_message;
            
            // Skip if no real status change
            if (newStatus === oldStatus) {
                if (this.settings.debug) {
                    console.log(`[StatusChecker] Post ${postId} status unchanged: ${newStatus}`);
                }
                return;
            }
            
            console.log(`[StatusChecker] Post ${postId} status changed: ${oldStatus} → ${newStatus}`);
            
            // Update our status map
            this.statusMap[postId] = newStatus;
            
            // Find the post element
            const postElement = document.querySelector(`${this.settings.postSelector}[data-post-id="${postId}"]`);
            
            if (!postElement) {
                console.log(`[StatusChecker] Post element not found for ID ${postId}`);
                return;
            }

            // Force the data-status attribute update to ensure consistent state
            postElement.setAttribute('data-status', newStatus);
            
            // For debugging
            if (document.body.classList.contains('profile-page')) {
                console.log(`[StatusChecker] Applying status change to post ${postId} in profile page`);
            }
            
            // Handle different status changes
            switch (newStatus) {
                case 'on-hold':
                    this.handleOnHold(postElement, postId, holdMessage);
                    break;
                case 'approved':
                    this.handleApproved(postElement, postId);
                    break;
                default:
                    console.log(`[StatusChecker] Unhandled status: ${newStatus}`);
            }
        });
    }
    
    handleOnHold(postElement, postId, message) {
        console.log(`[StatusChecker] Handling "on-hold" status for post ${postId}`);
        
        // If we're on the homepage
        if (document.body.classList.contains('homepage')) {
            // Use custom callback if provided, otherwise fade out and remove
            if (this.settings.onPostHold) {
                this.settings.onPostHold(postElement, postId, message);
            } else {
                this.fadeOutAndRemovePost(postElement);
            }
        } 
        // If we're on the profile page
        else if (document.body.classList.contains('profile-page')) {
            // First remove any existing notification to prevent duplicates
            const existingNotification = postElement.querySelector('.post-status-notification');
            if (existingNotification) {
                existingNotification.remove();
            }
            
            // Show the on-hold notification on the post
            this.applyOnHoldStyle(postElement, message);
        }
        
        // Show notification
        if (window.showToast) {
            window.showToast('A post has been placed on hold by administrators', 'warning');
        }
    }
    
    handleApproved(postElement, postId) {
        console.log(`[StatusChecker] Handling "approved" status for post ${postId}`);
        
        // If custom handler provided, use it
        if (this.settings.onPostApprove) {
            this.settings.onPostApprove(postElement, postId);
            // Let the custom handler show the notification
        } else {
            // Remove any existing status notifications
            const existingNotification = postElement.querySelector('.post-status-notification');
            if (existingNotification) {
                existingNotification.remove();
            }
            
            // Remove on-hold class if present
            postElement.classList.remove('post-on-hold');
            
            // Add approved style if needed
            postElement.classList.add('post-approved');
            
            // Show notification only if no custom handler
            if (window.showToast) {
                window.showToast('A post has been approved by administrators', 'success');
            }
        }
    }
    
    fadeOutAndRemovePost(postElement) {
        // Add transition styles
        postElement.style.transition = 'opacity 0.5s ease, max-height 0.5s ease, margin 0.5s ease, padding 0.5s ease';
        postElement.style.overflow = 'hidden';
        
        // Get the current height for smooth animation
        const height = postElement.offsetHeight;
        postElement.style.maxHeight = height + 'px';
        
        // Start animation
        setTimeout(() => {
            postElement.style.opacity = '0';
            postElement.style.maxHeight = '0';
            postElement.style.marginTop = '0';
            postElement.style.marginBottom = '0';
            postElement.style.paddingTop = '0';
            postElement.style.paddingBottom = '0';
            
            // Remove element after animation completes
            setTimeout(() => {
                if (postElement.parentNode) {
                    postElement.parentNode.removeChild(postElement);
                }
            }, 500);
        }, 10);
    }
    
    applyOnHoldStyle(postElement, message) {
        // Check if notification already exists and remove it to prevent duplicates
        const existingNotification = postElement.querySelector('.post-status-notification');
        if (existingNotification) {
            console.log('[StatusChecker] Removing existing notification to avoid duplication');
            existingNotification.remove();
        }
        
        // Also check for PHP-generated notification and remove it
        const phpNotification = postElement.querySelector('.post-pending-notice');
        if (phpNotification) {
            console.log('[StatusChecker] Removing PHP-generated notification');
            phpNotification.remove();
        }
        
        // Create notification element
        const notification = document.createElement('div');
        notification.className = 'post-status-notification on-hold';
        
        // Insert at the beginning of the post
        postElement.insertBefore(notification, postElement.firstChild);
        
        // Set the standard notification text
        notification.innerHTML = `
            <div class="notification-icon">
                <i class="bi bi-exclamation-triangle-fill"></i>
            </div>
            <div class="notification-content">
                This post has been placed on hold by administrators. Please check your notifications for details.
            </div>
        `;
        
        // Add on-hold class to the post for styling
        postElement.classList.add('post-on-hold');
        
        // Log the action for debugging
        console.log(`[StatusChecker] Applied on-hold style to post ${postElement.getAttribute('data-post-id')}`);
    }
}

// Initialize on DOMContentLoaded
document.addEventListener('DOMContentLoaded', function() {
    console.log('[StatusChecker] DOM loaded, checking page type...');
    
    if (document.body.classList.contains('homepage')) {
        console.log('[StatusChecker] Initializing for homepage');
        new PostStatusChecker({
            onPostHold: function(postElement, postId) {
                // Fade out and remove post from homepage
                postElement.style.transition = 'opacity 0.5s, max-height 0.5s';
                postElement.style.opacity = '0';
                postElement.style.maxHeight = '0';
                postElement.style.overflow = 'hidden';
                
                // Remove from DOM after animation
                setTimeout(() => {
                    if (postElement.parentNode) {
                        postElement.parentNode.removeChild(postElement);
                    }
                }, 500);
                
                // Show toast notification
                if (window.showToast) {
                    window.showToast('A post has been placed on hold by administrators', 'warning');
                }
            }
        });
    }
    else if (document.body.classList.contains('profile-page')) {
        console.log('[StatusChecker] Initializing for profile page with default handler');
        // Use the default behavior for profile page which will add the notification banner
        new PostStatusChecker({
            debug: true,
            // The default applyOnHoldStyle will be used automatically
            onPostApprove: function(postElement, postId) {
                // Remove any existing status notifications
                const existingNotification = postElement.querySelector('.post-status-notification');
                if (existingNotification) {
                    existingNotification.remove();
                }
                
                // Remove on-hold class if present
                postElement.classList.remove('post-on-hold');
                
                // Show toast notification
                if (window.showToast) {
                    window.showToast('Your post has been approved by administrators', 'success');
                }
            }
        });
    }
    else {
        console.log('[StatusChecker] Unknown page type, not initializing status checker');
    }
}); 