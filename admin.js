// ══ UPLOAD PRODUCT (PHP version) ══
function uploadProduct() {
  const name    = document.getElementById('p-name').value.trim();
  const cat     = document.getElementById('p-cat').value;
  const price   = document.getElementById('p-price').value.trim();
  const desc    = document.getElementById('p-desc').value.trim();
  const stock   = document.getElementById('p-stock').value;
  const visible = document.getElementById('p-visible').value;

  if (!name || !cat || !price) { toast('⚠️ Name, Category & Price are required!'); return; }

  const highlights = Array.from(document.querySelectorAll('#highlights-list input'))
    .map(i => i.value.trim()).filter(Boolean).join('||');

  const priceTags = Array.from(document.querySelectorAll('#price-tags-list .price-tag-row')).map(row => {
    const inp = row.querySelectorAll('input');
    return { label: inp[0].value.trim(), price: inp[1].value.trim() };
  }).filter(pt => pt.label && pt.price);

  const formData = new FormData();
  formData.append('name', name);
  formData.append('cat', cat);
  formData.append('price', price);
  formData.append('description', desc);
  formData.append('stock', stock);
  formData.append('visible', visible);
  formData.append('highlights', highlights);
  formData.append('price_tags', JSON.stringify(priceTags));

  // Send first photo as base64
  if (uploadedPhotos.length > 0) {
    formData.append('photo', uploadedPhotos[0]);
  }

  toast('⏳ Uploading...');

  fetch('save_product.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
      if (data.status === 'success') {
        toast('✅ Product uploaded to database!');
        resetUploadForm();
      } else {
        toast('❌ Error: ' + data.message);
      }
    })
    .catch(() => toast('❌ Server error! Check PHP files.'));
}

// ══ RENDER PRODUCTS (PHP version) ══
function renderProducts() {
  const search = (document.getElementById('prod-search') || {}).value || '';
  const catF   = (document.getElementById('prod-cat-filter') || {}).value || '';
  const stockF = (document.getElementById('prod-stock-filter') || {}).value || '';
  const grid   = document.getElementById('products-grid');
  grid.innerHTML = '<div class="empty"><div class="e-icon">⏳</div><p>Loading...</p></div>';

  fetch('get_products.php?visible=all')
    .then(res => res.json())
    .then(data => {
      let products = data.products || [];

      if (search) products = products.filter(p =>
        p.name.toLowerCase().includes(search.toLowerCase()) ||
        p.cat.toLowerCase().includes(search.toLowerCase()));
      if (catF)   products = products.filter(p => p.cat === catF);
      if (stockF) products = products.filter(p => p.stock === stockF);

      if (!products.length) {
        grid.innerHTML = '<div class="empty"><div class="e-icon">📦</div><p>No products found</p></div>';
        return;
      }

      grid.innerHTML = products.map(p => `
        <div class="product-card" id="pc-${p.id}">
          ${p.photo
            ? `<img src="${p.photo}"/>`
            : `<div style="height:150px;background:var(--teal-light);display:flex;align-items:center;justify-content:center;font-size:36px">👗</div>`}
          <div class="pc-body">
            <div class="pc-cat">${p.cat}</div>
            <div class="pc-name">${p.name}</div>
            <div class="pc-price">₹${p.price}</div>
            ${p.highlights_arr && p.highlights_arr.length
              ? `<ul style="margin-top:5px;padding-left:14px;font-size:11px;color:var(--muted)">${p.highlights_arr.map(h => `<li>${h}</li>`).join('')}</ul>`
              : ''}
            ${p.price_tags_arr && p.price_tags_arr.length
              ? `<div style="margin-top:6px;display:flex;flex-wrap:wrap;gap:3px">${p.price_tags_arr.map(t => `<span style="background:var(--copper-light);color:var(--copper);border-radius:4px;padding:2px 6px;font-size:10px;font-weight:700">${t.label}:₹${t.price}</span>`).join('')}</div>`
              : ''}
            <div style="margin-top:5px;font-size:11px;font-weight:600;color:${p.stock === 'Available' ? 'var(--success)' : p.stock === 'Limited' ? 'var(--warning)' : 'var(--danger)'}">${p.stock}</div>
            <div style="margin-top:3px;font-size:11px;color:var(--muted)">Website: ${p.visible === 'yes' ? '✅ Visible' : '❌ Hidden'}</div>
            <div class="pc-actions">
              <button class="btn-vis" onclick="toggleVis(${p.id},'${p.visible === 'yes' ? 'no' : 'yes'}')">${p.visible === 'yes' ? '🙈 Hide' : '👁 Show'}</button>
              <button class="btn-del" onclick="deleteProduct(${p.id})">🗑️ Delete</button>
            </div>
          </div>
        </div>`).join('');

      // Also update dashboard stat
      document.getElementById('stat-products').textContent = products.length;
    })
    .catch(() => {
      grid.innerHTML = '<div class="empty"><div class="e-icon">❌</div><p>Server error! Check PHP</p></div>';
    });
}

// ══ TOGGLE VISIBILITY (PHP version) ══
function toggleVis(id, newVisible) {
  const formData = new FormData();
  formData.append('id', id);
  formData.append('visible', newVisible);

  fetch('toggle_visible.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
      if (data.status === 'success') { renderProducts(); toast('✅ Visibility updated'); }
      else toast('❌ ' + data.message);
    });
}

// ══ DELETE PRODUCT (PHP version) ══
function deleteProduct(id) {
  if (!confirm('Delete this product?')) return;
  const formData = new FormData();
  formData.append('id', id);

  fetch('delete_product.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
      if (data.status === 'success') { renderProducts(); toast('🗑️ Product deleted'); }
      else toast('❌ ' + data.message);
    });
}