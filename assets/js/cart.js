/**
 * assets/js/cart.js
 *
 * Role in system: client-side cart for customer/menu.php - Specific
 * Objective 2 (electronic ordering). Loaded only on the menu page,
 * which defines the TABLE_ID/TABLE_NUMBER globals this file depends
 * on. The cart itself is a convenience only: the server independently
 * re-validates every price and item on submission (see
 * customer/submit_order.php), so nothing here is trusted as-is.
 */

// Cart is kept in localStorage, scoped per table, so a re-scan of the
// same table's QR code (or a page refresh) doesn't lose the cart.
const CART_KEY = 'gorilla_cart_table_' + TABLE_NUMBER;

/**
 * @returns {Object<string, {name: string, price: number, qty: number}>}
 *   The current cart, keyed by item_id as a string.
 */
function getCart() {
    const raw = localStorage.getItem(CART_KEY);
    return raw ? JSON.parse(raw) : {}; // { itemId: {name, price, qty} }
}

/** @param {Object} cart Cart object to persist to localStorage. */
function saveCart(cart) {
    localStorage.setItem(CART_KEY, JSON.stringify(cart));
}

/**
 * Set (or remove, if qty <= 0) one item's quantity in the cart, then
 * re-render every on-screen cart control to match.
 * @param {string} id Item ID
 * @param {string} name Item name (for display)
 * @param {number} price Unit price
 * @param {number} qty New quantity
 */
function setQty(id, name, price, qty) {
    const cart = getCart();
    if (qty <= 0) {
        delete cart[id];
    } else {
        cart[id] = { name, price, qty };
    }
    saveCart(cart);
    renderAll();
}

function cartCount(cart) {
    return Object.values(cart).reduce((sum, i) => sum + i.qty, 0);
}

function cartTotal(cart) {
    return Object.values(cart).reduce((sum, i) => sum + i.qty * i.price, 0);
}

// Update every "Add" button / stepper on the menu to reflect current cart state.
function renderMenuControls() {
    const cart = getCart();
    document.querySelectorAll('.item-controls').forEach(control => {
        const id = control.dataset.itemId;
        const qty = cart[id] ? cart[id].qty : 0;
        const addBtn = control.querySelector('.btn-add');
        const stepper = control.querySelector('.qty-stepper');
        const qtyValue = control.querySelector('.qty-value');

        if (qty > 0) {
            addBtn.hidden = true;
            stepper.hidden = false;
            qtyValue.textContent = qty;
        } else {
            addBtn.hidden = false;
            stepper.hidden = true;
        }
    });
}

// Update the sticky bar and the full cart panel.
function renderCartBarAndPanel() {
    const cart = getCart();
    const count = cartCount(cart);
    const total = cartTotal(cart);

    const bar = document.getElementById('cartBar');
    document.getElementById('cartBarCount').textContent = count + (count === 1 ? ' item' : ' items');
    document.getElementById('cartBarTotal').textContent = formatPrice(total);
    bar.hidden = count === 0;

    const itemsWrap = document.getElementById('cartItems');
    itemsWrap.innerHTML = '';
    Object.entries(cart).forEach(([id, item]) => {
        const row = document.createElement('div');
        row.className = 'cart-row';
        row.innerHTML = `
            <span class="cart-row-name">${item.name}</span>
            <div class="cart-row-controls">
                <button class="qty-minus" data-id="${id}">-</button>
                <span>${item.qty}</span>
                <button class="qty-plus" data-id="${id}">+</button>
            </div>
            <span class="cart-row-subtotal">${formatPrice(item.qty * item.price)}</span>
        `;
        itemsWrap.appendChild(row);
    });
    document.getElementById('cartTotal').textContent = formatPrice(total);
}

function formatPrice(n) {
    return Math.round(n).toLocaleString() + ' RWF';
}

function renderAll() {
    renderMenuControls();
    renderCartBarAndPanel();
}

// --- Event wiring -------------------------------------------------

document.addEventListener('click', (e) => {
    if (e.target.matches('.btn-add')) {
        const { id, name, price } = e.target.dataset;
        setQty(id, name, parseFloat(price), 1);
    }
    if (e.target.matches('.qty-plus')) {
        const id = e.target.dataset.id;
        const cart = getCart();
        const current = cart[id];
        if (current) {
            setQty(id, current.name, current.price, current.qty + 1);
        } else {
            // plus button clicked from a menu card that has no name/price data
            const control = e.target.closest('.item-controls');
            const addBtn = control.querySelector('.btn-add');
            setQty(id, addBtn.dataset.name, parseFloat(addBtn.dataset.price), 1);
        }
    }
    if (e.target.matches('.qty-minus')) {
        const id = e.target.dataset.id;
        const cart = getCart();
        const current = cart[id];
        if (current) {
            setQty(id, current.name, current.price, current.qty - 1);
        }
    }
});

document.getElementById('cartBarOpen').addEventListener('click', () => {
    document.getElementById('cartPanel').hidden = false;
});
document.getElementById('cartClose').addEventListener('click', () => {
    document.getElementById('cartPanel').hidden = true;
});

/**
 * Submit the current cart to customer/submit_order.php. On success,
 * clears this table's cart and redirects to the order status page;
 * on failure, shows the server's error message inline.
 */
document.getElementById('placeOrderBtn').addEventListener('click', async () => {
    const cart = getCart();
    const errorEl = document.getElementById('orderError');
    errorEl.hidden = true;

    if (Object.keys(cart).length === 0) {
        errorEl.textContent = 'Your cart is empty.';
        errorEl.hidden = false;
        return;
    }

    const items = Object.entries(cart).map(([id, item]) => ({
        item_id: parseInt(id),
        quantity: item.qty
    }));

    const payload = {
        table_id: TABLE_ID,
        customer_phone: document.getElementById('customerPhone').value.trim() || null,
        items: items
    };

    const placeBtn = document.getElementById('placeOrderBtn');
    placeBtn.disabled = true;
    placeBtn.textContent = 'Placing order...';

    try {
        const res = await fetch('submit_order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();

        if (data.success) {
            localStorage.removeItem(CART_KEY); // order placed - clear this table's cart
            window.location.href = 'order_status.php?order_id=' + data.order_id;
        } else {
            errorEl.textContent = data.message || 'Could not place order. Please try again.';
            errorEl.hidden = false;
            placeBtn.disabled = false;
            placeBtn.textContent = 'Place Order';
        }
    } catch (err) {
        errorEl.textContent = 'Network error. Please check your connection and try again.';
        errorEl.hidden = false;
        placeBtn.disabled = false;
        placeBtn.textContent = 'Place Order';
    }
});

renderAll();