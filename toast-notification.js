/**
 * Toast notification helper
 * Provides a simple toast notification system that can be used across pages
 */

window.showToast = function(message, type = 'info', duration = 5000) {
    // Create toast element
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    
    // Create icon based on type
    let icon = 'info-circle-fill';
    if (type === 'success') icon = 'check-circle-fill';
    if (type === 'error') icon = 'exclamation-triangle-fill';
    if (type === 'warning') icon = 'exclamation-circle-fill';
    
    // Set toast content
    toast.innerHTML = `
        <div class="toast-content">
            <i class="bi bi-${icon}"></i>
            <span>${message}</span>
        </div>
        <button class="toast-close">×</button>
    `;
    
    // Remove any existing toasts with the same message (to prevent duplicates)
    document.querySelectorAll('.toast').forEach(existingToast => {
        if (existingToast.textContent.trim() === message.trim()) {
            existingToast.remove();
        }
    });
    
    // Position upper-right
    toast.style.position = 'fixed';
    toast.style.top = '80px';
    toast.style.right = '24px';
    toast.style.zIndex = '13000';
    
    // Add to document
    document.body.appendChild(toast);
    
    // Show toast with animation
    setTimeout(() => {
        toast.classList.add('show');
    }, 10);
    
    // Add close button functionality
    const closeBtn = toast.querySelector('.toast-close');
    closeBtn.addEventListener('click', () => {
        toast.classList.remove('show');
        setTimeout(() => {
            if (document.body.contains(toast)) {
                document.body.removeChild(toast);
            }
        }, 300);
    });
    
    // Auto remove after specified duration
    setTimeout(() => {
        if (document.body.contains(toast)) {
            toast.classList.remove('show');
            setTimeout(() => {
                if (document.body.contains(toast)) {
                    document.body.removeChild(toast);
                }
            }, 300);
        }
    }, duration);
}; 