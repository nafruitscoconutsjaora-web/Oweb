/**
 * ==============================================================================
 * Verifex SMS Platform - Vanilla JavaScript Client
 * Mobile navigation, live countdown timer, status polling, clipboard utilities
 * ==============================================================================
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Mobile Menu Toggle
    const mobileMenuBtn = document.getElementById('mobile-menu-btn');
    const mobileMenu = document.getElementById('mobile-menu');

    if (mobileMenuBtn && mobileMenu) {
        mobileMenuBtn.addEventListener('click', () => {
            const isHidden = mobileMenu.classList.contains('hidden');
            if (isHidden) {
                mobileMenu.classList.remove('hidden');
            } else {
                mobileMenu.classList.add('hidden');
            }
        });
    }

    // 2. Clipboard Copy Helper
    document.querySelectorAll('[data-copy]').forEach(button => {
        button.addEventListener('click', (e) => {
            e.preventDefault();
            const textToCopy = button.getAttribute('data-copy');
            if (!textToCopy) return;

            navigator.clipboard.writeText(textToCopy).then(() => {
                showToast('Copied to clipboard!', 'success');
                const originalHtml = button.innerHTML;
                button.innerHTML = '<span class="text-xs text-green-600 font-semibold">Copied!</span>';
                setTimeout(() => {
                    button.innerHTML = originalHtml;
                }, 2000);
            }).catch(() => {
                showToast('Failed to copy', 'error');
            });
        });
    });

    // 3. Live Activation Countdown Timer & Polling Engine
    const activationContainer = document.getElementById('live-activation-container');
    if (activationContainer) {
        initLiveActivation(activationContainer);
    }
});

/**
 * Toast Notification Dispenser
 */
function showToast(message, type = 'info') {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    const bgClass = type === 'success' ? 'bg-green-600 text-white' : (type === 'error' ? 'bg-red-600 text-white' : 'bg-gray-900 text-white');
    toast.className = `${bgClass} px-4 py-3 rounded-xl shadow-lg text-sm font-medium transition-all duration-300 transform translate-y-2 opacity-0 flex items-center gap-2`;
    toast.textContent = message;

    container.appendChild(toast);

    requestAnimationFrame(() => {
        toast.classList.remove('translate-y-2', 'opacity-0');
    });

    setTimeout(() => {
        toast.classList.add('opacity-0', 'translate-y-2');
        setTimeout(() => toast.remove(), 300);
    }, 3500);
}

/**
 * Live Activation Polling & Countdown Timer Engine
 */
function initLiveActivation(container) {
    const activationId = container.getAttribute('data-activation-id');
    const expiresAtStr = container.getAttribute('data-expires-at');
    const timerDisplay = document.getElementById('activation-timer');
    const statusDisplay = document.getElementById('activation-status-badge');
    const smsBox = document.getElementById('activation-sms-box');
    const codeDisplay = document.getElementById('activation-code-display');

    if (!activationId || !expiresAtStr) return;

    const expiresAt = new Date(expiresAtStr).getTime();
    let isTerminated = false;
    let pollInterval = null;

    // Countdown Timer Loop
    function updateTimer() {
        if (isTerminated) return;

        const now = Date.now();
        const diffMs = expiresAt - now;

        if (diffMs <= 0) {
            if (timerDisplay) timerDisplay.textContent = '00:00 (Expired)';
            if (statusDisplay) {
                statusDisplay.className = 'badge-danger';
                statusDisplay.textContent = 'Expired';
            }
            clearInterval(pollInterval);
            isTerminated = true;
            return;
        }

        const totalSecs = Math.floor(diffMs / 1000);
        const mins = Math.floor(totalSecs / 60);
        const secs = totalSecs % 60;

        if (timerDisplay) {
            timerDisplay.textContent = `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
        }
    }

    updateTimer();
    const timerInterval = setInterval(updateTimer, 1000);

    // Status Polling Loop (Polled every 5 seconds)
    function pollStatus() {
        if (isTerminated) return;

        fetch(`/api/poll-status.php?activation_id=${encodeURIComponent(activationId)}`, {
            headers: { 'Accept': 'application/json' }
        })
        .then(res => res.json())
        .then(data => {
            if (!data || !data.success) return;

            const status = data.status;

            if (status === 'sms_received' || data.sms_code) {
                isTerminated = true;
                clearInterval(pollInterval);
                clearInterval(timerInterval);

                if (statusDisplay) {
                    statusDisplay.className = 'badge-success';
                    statusDisplay.textContent = 'SMS Received';
                }

                if (smsBox) smsBox.classList.remove('hidden');
                if (codeDisplay) codeDisplay.textContent = data.sms_code || 'Received';

                showToast('SMS verification code received!', 'success');
            } else if (status === 'cancelled' || status === 'refunded' || status === 'expired') {
                isTerminated = true;
                clearInterval(pollInterval);
                clearInterval(timerInterval);

                if (statusDisplay) {
                    statusDisplay.className = 'badge-warning';
                    statusDisplay.textContent = status.charAt(0).toUpperCase() + status.slice(1);
                }
            }
        })
        .catch(err => {
            console.error('Polling network error:', err);
        });
    }

    // Start polling every 4.5 seconds
    pollInterval = setInterval(pollStatus, 4500);
}
