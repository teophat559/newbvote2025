// Production-ready notification system to replace alert() calls
class NotificationManager {
    constructor() {
        this.createContainer();
    }

    createContainer() {
        if (document.getElementById('notification-container')) return;
        
        const container = document.createElement('div');
        container.id = 'notification-container';
        container.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            max-width: 400px;
        `;
        document.body.appendChild(container);
    }

    show(message, type = 'info', duration = 5000) {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        
        const colors = {
            success: '#10B981',
            error: '#EF4444', 
            warning: '#F59E0B',
            info: '#3B82F6'
        };
        
        notification.style.cssText = `
            background: ${colors[type] || colors.info};
            color: white;
            padding: 12px 16px;
            margin-bottom: 10px;
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            animation: slideInRight 0.3s ease-out;
            cursor: pointer;
            position: relative;
            transform: translateX(100%);
        `;
        
        notification.innerHTML = `
            <div style="font-weight: 500; margin-bottom: 4px;">
                ${type === 'success' ? '✅' : type === 'error' ? '❌' : type === 'warning' ? '⚠️' : 'ℹ️'}
                ${type.charAt(0).toUpperCase() + type.slice(1)}
            </div>
            <div style="font-size: 14px;">${message}</div>
        `;

        // Add CSS animation
        if (!document.getElementById('notification-styles')) {
            const style = document.createElement('style');
            style.id = 'notification-styles';
            style.textContent = `
                @keyframes slideInRight {
                    from { transform: translateX(100%); }
                    to { transform: translateX(0); }
                }
                @keyframes slideOutRight {
                    from { transform: translateX(0); }
                    to { transform: translateX(100%); }
                }
                .notification { transform: translateX(0) !important; }
                .notification.removing { animation: slideOutRight 0.3s ease-in; }
            `;
            document.head.appendChild(style);
        }

        const container = document.getElementById('notification-container');
        container.appendChild(notification);

        // Trigger animation
        setTimeout(() => {
            notification.style.transform = 'translateX(0)';
        }, 10);

        // Auto remove
        setTimeout(() => this.remove(notification), duration);

        // Manual remove on click
        notification.addEventListener('click', () => this.remove(notification));

        return notification;
    }

    remove(notification) {
        if (!notification || !notification.parentNode) return;
        
        notification.classList.add('removing');
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }

    success(message, duration) {
        return this.show(message, 'success', duration);
    }

    error(message, duration) {
        return this.show(message, 'error', duration);
    }

    warning(message, duration) {
        return this.show(message, 'warning', duration);
    }

    info(message, duration) {
        return this.show(message, 'info', duration);
    }
}

// Create global instance
window.notifications = new NotificationManager();

// Global function to replace alert()
window.showNotification = (message, type = 'info') => {
    return window.notifications.show(message, type);
};

// Backward compatibility functions
window.showSuccess = (message) => window.notifications.success(message);
window.showError = (message) => window.notifications.error(message);
window.showWarning = (message) => window.notifications.warning(message);
window.showInfo = (message) => window.notifications.info(message);