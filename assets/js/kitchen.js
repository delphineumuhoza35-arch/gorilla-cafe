/**
 * assets/js/kitchen.js
 *
 * Role in system: drives kitchen/dashboard.php - the kitchen-facing
 * half of Specific Objective 3. Polls kitchen/orders_feed.php every
 * 5 seconds to keep the on-screen order queue current, and posts to
 * kitchen/update_status.php when staff advance an order one step.
 */

const STATUS_SEQUENCE = ['received', 'preparing', 'ready', 'served'];
const STATUS_LABELS = {
    received: 'Order Received',
    preparing: 'Preparing',
    ready: 'Ready',
    served: 'Served'
};
// What the button says to move an order INTO this status.
const NEXT_ACTION_LABELS = {
    preparing: 'Start Preparing',
    ready: 'Mark Ready',
    served: 'Mark Served'
};

/**
 * @param {string} current Current order_status
 * @returns {string|null} The next status in the pipeline, or null if
 *   already at the final status ('served').
 */
function nextStatus(current) {
    const idx = STATUS_SEQUENCE.indexOf(current);
    return idx >= 0 && idx < STATUS_SEQUENCE.length - 1 ? STATUS_SEQUENCE[idx + 1] : null;
}

function timeAgo(isoLikeMysqlDatetime) {
    // MySQL DATETIME string "YYYY-MM-DD HH:MM:SS" - make it parseable.
    const then = new Date(isoLikeMysqlDatetime.replace(' ', 'T'));
    const diffMin = Math.max(0, Math.round((Date.now() - then.getTime()) / 60000));
    if (diffMin < 1) return 'just now';
    if (diffMin === 1) return '1 min ago';
    return diffMin + ' min ago';
}

/**
 * Rebuild the entire order queue display from a fresh orders array.
 * @param {Array<Object>} orders Orders as returned by orders_feed.php
 */
function renderOrders(orders) {
    const wrap = document.getElementById('ordersWrap');

    if (orders.length === 0) {
        wrap.innerHTML = '<p class="loading-text">No orders right now.</p>';
        return;
    }

    wrap.innerHTML = '';
    orders.forEach(order => {
        const card = document.createElement('div');
        card.className = 'order-card status-border-' + order.order_status;

        const itemsHtml = order.items
            .map(i => `<li>${i.quantity} × ${escapeHtml(i.name)}</li>`)
            .join('');

        const next = nextStatus(order.order_status);
        const actionBtn = next
            ? `<button class="btn-advance" data-order-id="${order.order_id}" data-next-status="${next}">${NEXT_ACTION_LABELS[next]}</button>`
            : '';

        card.innerHTML = `
            <div class="order-card-header">
                <span class="order-table">Table ${order.table_number}</span>
                <span class="status-pill status-${order.order_status}">${STATUS_LABELS[order.order_status]}</span>
            </div>
            <ul class="order-items-list">${itemsHtml}</ul>
            <div class="order-card-footer">
                <span class="order-time">${timeAgo(order.order_time)}</span>
                <span class="order-total">${Math.round(order.total_amount).toLocaleString()} RWF</span>
            </div>
            ${actionBtn}
        `;
        wrap.appendChild(card);
    });
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

/**
 * Fetch the current order queue from orders_feed.php and re-render
 * it. Called on load, every 5 seconds thereafter, and immediately
 * after any status-advance action.
 */
async function loadOrders() {
    const showServed = document.getElementById('showServed').checked;
    const url = 'orders_feed.php' + (showServed ? '?show_served=1' : '');
    try {
        const res = await fetch(url);
        const data = await res.json();
        if (data.success) {
            renderOrders(data.orders);
        }
    } catch (err) {
        // Leave the last successfully rendered list on screen rather
        // than blanking the dashboard on a dropped connection.
    }
}

document.getElementById('ordersWrap').addEventListener('click', async (e) => {
    if (!e.target.matches('.btn-advance')) return;

    const btn = e.target;
    const orderId = parseInt(btn.dataset.orderId);
    const newStatus = btn.dataset.nextStatus;
    btn.disabled = true;
    btn.textContent = 'Updating...';

    try {
        await fetch('update_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ order_id: orderId, new_status: newStatus })
        });
    } finally {
        loadOrders(); // refresh immediately so the change is visible right away
    }
});

document.getElementById('showServed').addEventListener('change', loadOrders);

loadOrders();
setInterval(loadOrders, 5000);
