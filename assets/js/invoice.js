(function () {
  let items = [];
  let searchTimer = null;

  const itemsBody = document.getElementById('itemsBody');
  const searchInput = document.getElementById('productSearch');
  const searchResults = document.getElementById('searchResults');
  const customerSelect = document.getElementById('customerSelect');
  const walkinFields = document.getElementById('walkinFields');

  customerSelect.addEventListener('change', () => {
    walkinFields.style.display = customerSelect.value ? 'none' : 'block';
  });

  searchInput.addEventListener('input', () => {
    clearTimeout(searchTimer);
    const q = searchInput.value.trim();
    if (!q) {
      searchResults.innerHTML = '';
      return;
    }
    searchTimer = setTimeout(() => {
      fetch(APP.searchUrl + '?q=' + encodeURIComponent(q))
        .then((r) => r.json())
        .then(renderSearchResults)
        .catch(() => {});
    }, 250);
  });

  function renderSearchResults(products) {
    searchResults.innerHTML = '';
    products.forEach((p) => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'list-group-item list-group-item-action search-result';

      btn.appendChild(buildThumb(p));

      const label = document.createElement('span');
      label.className = 'search-result-text';
      label.textContent = p.name + (p.sku ? ' (' + p.sku + ')' : '') + ' — ' + APP.currency + parseFloat(p.sell_price).toFixed(2) + ' — ' + APP.i18n.stockLabel + ' ' + p.stock_qty + ' ' + p.unit;
      btn.appendChild(label);

      btn.addEventListener('click', () => addItem(p));
      searchResults.appendChild(btn);
    });
  }

  // The product picture when it has one, otherwise a neutral placeholder box so
  // every row keeps the same height and the text stays on one alignment.
  function buildThumb(p) {
    if (p.image_url) {
      const img = document.createElement('img');
      img.className = 'search-result-thumb';
      img.src = p.image_url;
      img.alt = '';
      img.loading = 'lazy';
      return img;
    }
    const span = document.createElement('span');
    span.className = 'search-result-thumb search-result-thumb-empty';
    span.innerHTML = APP.imageIcon;
    return span;
  }

  function addItem(p) {
    const existing = items.find((it) => it.product_id === p.id);
    if (existing) {
      existing.quantity += 1;
    } else {
      items.push({
        product_id: p.id,
        name: p.name,
        unit: p.unit,
        unit_price: parseFloat(p.sell_price),
        sell_price: parseFloat(p.sell_price),
        quantity: 1,
        stock_qty: parseFloat(p.stock_qty),
      });
    }
    searchInput.value = '';
    searchResults.innerHTML = '';
    renderItems();
  }

  function removeItem(index) {
    items.splice(index, 1);
    renderItems();
  }

  function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  function renderItems() {
    itemsBody.innerHTML = '';
    items.forEach((it, idx) => {
      const tr = document.createElement('tr');
      tr.innerHTML =
        '<td>' + escapeHtml(it.name) + '</td>' +
        '<td data-label="' + APP.i18n.qtyLabel + '"><div class="qty-cell"><input type="number" class="form-control form-control-sm qty-input" min="0.01" step="0.01" value="' + it.quantity + '" data-idx="' + idx + '"><span class="unit-label">' + escapeHtml(it.unit) + '</span></div></td>' +
        '<td class="stock-cell" data-label="' + APP.i18n.stockColLabel + '">' + it.stock_qty + ' ' + escapeHtml(it.unit) + '</td>' +
        '<td data-label="' + APP.i18n.priceLabel + '"><div class="price-cell"><input type="number" class="form-control form-control-sm price-input" min="0" step="0.01" value="' + it.unit_price + '" data-idx="' + idx + '"><span class="price-ref">' + APP.i18n.sellPriceLabel + ' ' + APP.currency + it.sell_price.toFixed(2) + '</span></div></td>' +
        '<td class="line-total" data-label="' + APP.i18n.lineTotalLabel + '">' + (it.quantity * it.unit_price).toFixed(2) + '</td>' +
        '<td><button type="button" class="btn btn-sm btn-outline-danger remove-btn" data-idx="' + idx + '">&times;</button></td>';
      itemsBody.appendChild(tr);
    });

    itemsBody.querySelectorAll('.qty-input').forEach((inp) => {
      inp.addEventListener('input', (e) => {
        const idx = e.target.dataset.idx;
        items[idx].quantity = parseFloat(e.target.value) || 0;
        recalcRowAndTotals();
      });
    });
    itemsBody.querySelectorAll('.price-input').forEach((inp) => {
      inp.addEventListener('input', (e) => {
        const idx = e.target.dataset.idx;
        items[idx].unit_price = parseFloat(e.target.value) || 0;
        recalcRowAndTotals();
      });
    });
    itemsBody.querySelectorAll('.remove-btn').forEach((btn) => {
      btn.addEventListener('click', (e) => removeItem(parseInt(e.target.dataset.idx, 10)));
    });

    updateStockWarnings();
    recalcTotals();
  }

  function updateStockWarnings() {
    const rows = itemsBody.querySelectorAll('tr');
    rows.forEach((row, idx) => {
      const overStock = items[idx].quantity > items[idx].stock_qty;
      row.querySelector('.stock-cell').classList.toggle('stock-warning', overStock);
      row.querySelector('.qty-input').classList.toggle('input-warning', overStock);
      const discounted = items[idx].unit_price < items[idx].sell_price;
      row.querySelector('.price-ref').classList.toggle('price-discounted', discounted);
    });
  }

  function recalcRowAndTotals() {
    const rows = itemsBody.querySelectorAll('tr');
    rows.forEach((row, idx) => {
      row.querySelector('.line-total').textContent = (items[idx].quantity * items[idx].unit_price).toFixed(2);
    });
    updateStockWarnings();
    recalcTotals();
  }

  function recalcTotals() {
    const subtotal = items.reduce((s, it) => s + it.quantity * it.unit_price, 0);
    const discount = parseFloat(document.getElementById('discountInput').value) || 0;
    const total = Math.max(subtotal - discount, 0);
    const paid = parseFloat(document.getElementById('paidInput').value) || 0;
    const due = Math.max(total - paid, 0);
    document.getElementById('subtotalDisplay').textContent = subtotal.toFixed(2);
    document.getElementById('totalDisplay').textContent = total.toFixed(2);
    document.getElementById('dueDisplay').textContent = due.toFixed(2);
  }

  document.getElementById('discountInput').addEventListener('input', recalcTotals);
  document.getElementById('paidInput').addEventListener('input', recalcTotals);

  document.getElementById('saveInvoiceBtn').addEventListener('click', () => {
    const errorBox = document.getElementById('formError');
    errorBox.style.display = 'none';

    if (!items.length) {
      errorBox.textContent = APP.i18n.addItemRequired;
      errorBox.style.display = 'block';
      return;
    }
    for (const it of items) {
      if (it.quantity > it.stock_qty) {
        errorBox.textContent = APP.i18n.notEnoughStock.replace('%s', it.name).replace('%s', it.stock_qty);
        errorBox.style.display = 'block';
        return;
      }
    }

    const payload = {
      csrf_token: APP.csrfToken,
      customer_id: customerSelect.value || null,
      walkin_name: document.getElementById('walkinName').value,
      walkin_phone: document.getElementById('walkinPhone').value,
      invoice_date: document.getElementById('invoiceDate').value,
      discount: parseFloat(document.getElementById('discountInput').value) || 0,
      paid_amount: parseFloat(document.getElementById('paidInput').value) || 0,
      items: items.map((it) => ({ product_id: it.product_id, quantity: it.quantity, unit_price: it.unit_price })),
    };

    const btn = document.getElementById('saveInvoiceBtn');
    btn.disabled = true;
    btn.textContent = APP.i18n.saving;

    fetch(APP.saveUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    })
      .then((r) => r.json().then((data) => ({ ok: r.ok, data })))
      .then(({ ok, data }) => {
        if (!ok) throw new Error(data.error || APP.i18n.saveFailed);
        window.location.href = APP.viewUrlBase + data.invoice_id;
      })
      .catch((err) => {
        errorBox.textContent = err.message;
        errorBox.style.display = 'block';
        btn.disabled = false;
        btn.textContent = APP.i18n.saveInvoice;
      });
  });

  renderItems();
})();
