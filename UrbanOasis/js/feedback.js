/**
 * Enhanced User Feedback System
 * Provides toast notifications, loading states, and better error handling
 */

class FeedbackManager {
    constructor() {
        this.init();
    }

    init() {
        this.createToastContainer();
        this.setupGlobalErrorHandling();
    }

    createToastContainer() {
        if (document.getElementById('toast-container')) return;
        
        const container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'toast-container position-fixed top-0 end-0 p-3';
        container.style.zIndex = '9999';
        document.body.appendChild(container);
    }

    /**
     * Show a toast notification
     * @param {string} message - The message to display
     * @param {string} type - Type of toast: success, error, warning, info
     * @param {number} duration - Auto-hide duration in milliseconds (0 = no auto-hide)
     * @param {object} options - Additional options
     */
    showToast(message, type = 'info', duration = 5000, options = {}) {
        const toastId = `toast-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
        
        const icons = {
            success: 'fas fa-check-circle',
            error: 'fas fa-exclamation-triangle',
            warning: 'fas fa-exclamation-circle',
            info: 'fas fa-info-circle'
        };

        const colors = {
            success: 'text-success',
            error: 'text-danger',
            warning: 'text-warning',
            info: 'text-primary'
        };

        const title = options.title || type.charAt(0).toUpperCase() + type.slice(1);
        const showProgressBar = duration > 0;

        const toastHTML = `
            <div class="toast show" id="${toastId}" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="toast-header">
                    <i class="${icons[type]} ${colors[type]} me-2"></i>
                    <strong class="me-auto">${title}</strong>
                    <small class="text-muted">just now</small>
                    <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
                <div class="toast-body">
                    ${message}
                    ${options.actionButtons ? this.createActionButtons(options.actionButtons) : ''}
                    ${showProgressBar ? `<div class="progress mt-2" style="height: 3px;"><div class="progress-bar" role="progressbar" style="width: 100%; transition: width ${duration}ms linear;"></div></div>` : ''}
                </div>
            </div>
        `;

        const container = document.getElementById('toast-container');
        container.insertAdjacentHTML('beforeend', toastHTML);

        const toastElement = document.getElementById(toastId);
        
        // Initialize Bootstrap toast
        const toast = new bootstrap.Toast(toastElement, {
            autohide: duration > 0,
            delay: duration
        });

        // Show progress bar animation
        if (showProgressBar) {
            const progressBar = toastElement.querySelector('.progress-bar');
            setTimeout(() => {
                progressBar.style.width = '0%';
            }, 100);
        }

        // Auto-remove from DOM after hiding
        toastElement.addEventListener('hidden.bs.toast', () => {
            toastElement.remove();
        });

        toast.show();
        return toastId;
    }

    createActionButtons(buttons) {
        return `<div class="mt-2">${buttons.map(btn => 
            `<button class="btn btn-sm ${btn.class || 'btn-outline-secondary'} me-2" onclick="${btn.onclick}">${btn.text}</button>`
        ).join('')}</div>`;
    }

    /**
     * Show success message
     */
    showSuccess(message, options = {}) {
        return this.showToast(message, 'success', 4000, options);
    }

    /**
     * Show error message
     */
    showError(message, options = {}) {
        return this.showToast(message, 'error', 8000, options);
    }

    /**
     * Show warning message
     */
    showWarning(message, options = {}) {
        return this.showToast(message, 'warning', 6000, options);
    }

    /**
     * Show info message
     */
    showInfo(message, options = {}) {
        return this.showToast(message, 'info', 5000, options);
    }

    /**
     * Show loading state with progress
     */
    showLoading(message = 'Loading...', options = {}) {
        const loadingId = `loading-${Date.now()}`;
        
        const loadingHTML = `
            <div class="toast show" id="${loadingId}" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="toast-header">
                    <div class="spinner-border spinner-border-sm text-primary me-2" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <strong class="me-auto">Processing</strong>
                    ${!options.hideClose ? '<button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>' : ''}
                </div>
                <div class="toast-body">
                    ${message}
                    ${options.showProgress ? '<div class="progress mt-2"><div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 100%"></div></div>' : ''}
                </div>
            </div>
        `;

        const container = document.getElementById('toast-container');
        container.insertAdjacentHTML('beforeend', loadingHTML);

        const toastElement = document.getElementById(loadingId);
        const toast = new bootstrap.Toast(toastElement, { autohide: false });
        toast.show();

        return {
            id: loadingId,
            update: (newMessage) => {
                const bodyElement = toastElement.querySelector('.toast-body');
                bodyElement.innerHTML = newMessage + (options.showProgress ? '<div class="progress mt-2"><div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 100%"></div></div>' : '');
            },
            hide: () => {
                toast.hide();
                setTimeout(() => toastElement.remove(), 500);
            }
        };
    }

    /**
     * Setup global error handling
     */
    setupGlobalErrorHandling() {
        window.addEventListener('unhandledrejection', (event) => {
            console.error('Unhandled promise rejection:', event.reason);
            this.showError('An unexpected error occurred. Please try again.');
        });

        window.addEventListener('error', (event) => {
            console.error('JavaScript error:', event.error);
            if (event.error && event.error.name !== 'NetworkError') {
                this.showError('An error occurred while processing your request.');
            }
        });
    }

    /**
     * Enhanced form submission with feedback
     */
    enhanceForm(formSelector, options = {}) {
        const form = document.querySelector(formSelector);
        if (!form) return;

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const loading = this.showLoading(options.loadingMessage || 'Submitting form...');
            
            try {
                const formData = new FormData(form);
                
                if (options.beforeSubmit) {
                    const proceed = await options.beforeSubmit(formData);
                    if (!proceed) {
                        loading.hide();
                        return;
                    }
                }

                const response = await fetch(form.action || window.location.href, {
                    method: form.method || 'POST',
                    body: formData
                });

                if (response.ok) {
                    loading.hide();
                    this.showSuccess(options.successMessage || 'Form submitted successfully!');
                    
                    if (options.onSuccess) {
                        options.onSuccess(response);
                    } else if (options.redirectOnSuccess) {
                        setTimeout(() => {
                            window.location.href = options.redirectOnSuccess;
                        }, 2000);
                    }
                } else {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
            } catch (error) {
                loading.hide();
                this.showError(options.errorMessage || 'Failed to submit form. Please try again.');
                
                if (options.onError) {
                    options.onError(error);
                }
            }
        });
    }

    /**
     * Show confirmation dialog
     */
    showConfirmation(message, options = {}) {
        return new Promise((resolve) => {
            const confirmId = `confirm-${Date.now()}`;
            
            const confirmHTML = `
                <div class="toast show" id="${confirmId}" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="toast-header">
                        <i class="fas fa-question-circle text-warning me-2"></i>
                        <strong class="me-auto">${options.title || 'Confirmation'}</strong>
                    </div>
                    <div class="toast-body">
                        ${message}
                        <div class="mt-3">
                            <button class="btn btn-sm btn-primary me-2" onclick="window.feedbackManager.resolveConfirm('${confirmId}', true)">
                                ${options.confirmText || 'Yes'}
                            </button>
                            <button class="btn btn-sm btn-secondary" onclick="window.feedbackManager.resolveConfirm('${confirmId}', false)">
                                ${options.cancelText || 'Cancel'}
                            </button>
                        </div>
                    </div>
                </div>
            `;

            const container = document.getElementById('toast-container');
            container.insertAdjacentHTML('beforeend', confirmHTML);

            this.confirmResolvers = this.confirmResolvers || {};
            this.confirmResolvers[confirmId] = resolve;
        });
    }

    resolveConfirm(confirmId, result) {
        const toastElement = document.getElementById(confirmId);
        const toast = bootstrap.Toast.getInstance(toastElement);
        
        if (this.confirmResolvers && this.confirmResolvers[confirmId]) {
            this.confirmResolvers[confirmId](result);
            delete this.confirmResolvers[confirmId];
        }
        
        toast.hide();
        setTimeout(() => toastElement.remove(), 500);
    }

    /**
     * Clear all toasts
     */
    clearAll() {
        const container = document.getElementById('toast-container');
        container.innerHTML = '';
    }
}

// Initialize global feedback manager
window.feedbackManager = new FeedbackManager();

// Convenience global functions
window.showSuccess = (message, options) => window.feedbackManager.showSuccess(message, options);
window.showError = (message, options) => window.feedbackManager.showError(message, options);
window.showWarning = (message, options) => window.feedbackManager.showWarning(message, options);
window.showInfo = (message, options) => window.feedbackManager.showInfo(message, options);
window.showLoading = (message, options) => window.feedbackManager.showLoading(message, options);
window.showConfirmation = (message, options) => window.feedbackManager.showConfirmation(message, options);
