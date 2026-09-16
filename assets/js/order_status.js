/**
 * assets/js/order_status.js
 *
 * Role in system: drives customer/order_status.php - the customer-
 * facing half of Specific Objective 3. Polls
 * customer/status_check.php every 5 seconds so the status badge and
 * step indicator update on their own as kitchen staff advance the
 * order, without the customer refreshing the page. Depends on the
 * ORDER_ID global defined inline by order_status.php.
 */

const STATUS_LABELS = {
    received: 'Order Received',
    preparing: 'Preparing',
    ready: 'Ready',
    served: 'Served',
    cancelled: 'Cancelled'
};
const STATUS_SEQUENCE = ['received', 'preparing', 'ready', 'served'];

/**
 * Update the status badge text/style and the step indicator row to
 * reflect a given order_status.
 * @param {string} status One of STATUS_SEQUENCE
 */
function applyStatus(status) {
    const badge = document.getElementById('statusBadge');
    badge.textContent = STATUS_LABELS[status] || status;
    badge.className = 'status-badge status-' + status;

    const currentIndex = STATUS_SEQUENCE.indexOf(status);
    document.querySelectorAll('#statusSteps .step').forEach(stepEl => {
        const stepIndex = STATUS_SEQUENCE.indexOf(stepEl.dataset.step);
        stepEl.classList.toggle('step-done', stepIndex <= currentIndex);
        stepEl.classList.toggle('step-current', stepIndex === currentIndex);
    });
}

/**
 * Fetch this order's current status and apply it. Stops its own
 * polling interval once the order reaches 'served', since no further
 * change is possible.
 */
async function pollStatus() {
    try {
        const res = await fetch('status_check.php?order_id=' + ORDER_ID);
        const data = await res.json();
        if (data.success) {
            applyStatus(data.order_status);
            // Once served or cancelled, there's nothing more to poll for.
            if (data.order_status === 'served' || data.order_status === 'cancelled') {
                clearInterval(pollHandle);
            }
        }
    } catch (err) {
        // Silently retry on next interval - a dropped connection
        // shouldn't interrupt the customer's view of their order.
    }
}

// Set the initial state from the page's own markup, then poll every 5s.
applyStatus(document.getElementById('statusBadge').className.replace('status-badge status-', ''));
const pollHandle = setInterval(pollStatus, 5000);
