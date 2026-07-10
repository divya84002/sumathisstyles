// ============================================
// admin.html — add Delete button to Orders table row
// Inside renderOrders(), REPLACE the row template with this
// (adds a 🗑️ Delete button as the last <td>):
// ============================================
tb.innerHTML = [...orders].reverse().map(o => `
    <tr>
      <td><b>#${o.id}</b></td><td>${o.name}</td>
      <td>📞 ${o.mobile}</td><td>${o.product}</td>
      <td><b>₹${o.amount}</b></td>
      <td>${badgeHTML(o.status)}</td><td>${o.date}</td>
      <td>
        <select class="status-select" onchange="updateStatus('${o.id}',this.value)">
          <option ${o.status==='Ordered'?'selected':''}>Ordered</option>
          <option ${o.status==='Processing'?'selected':''}>Processing</option>
          <option ${o.status==='Delivered'?'selected':''}>Delivered</option>
          <option ${o.status==='Cancelled'?'selected':''}>Cancelled</option>
          <option ${o.status==='Pending'?'selected':''}>Pending</option>
        </select>
      </td>
      <td><button class="btn btn-wa" style="font-size:11px;padding:4px 10px;" onclick="waNotify('${o.mobile}','${o.name}','${o.product}','${o.status}')">💬</button></td>
      <td><button class="btn btn-danger" style="font-size:11px;padding:4px 10px;" onclick="deleteOrderRow('${o.id}','${o.name}')">🗑️</button></td>
    </tr>`).join('');

// ============================================
// admin.html — NEW function: deletes an order via delete_order.php
// Add this function anywhere in the <script> section (near updateStatus):
// ============================================
async function deleteOrderRow(orderId, customerName) {
    const numericId = orderId.replace('#', '');
    if (!confirm(`Delete order #${numericId} (${customerName})? This cannot be undone.`)) return;
    const formData = new FormData();
    formData.append('id', numericId);
    try {
        const res = await fetch('delete_order.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.status === 'success') {
            await renderOrders();
            refreshDashboard();
            toast('🗑️ ' + data.message);
        } else {
            toast('❌ ' + (data.message || 'Failed to delete order'));
        }
    } catch (e) {
        toast('❌ Server error deleting order');
    }
}

// ============================================
// admin.html — also add a <th>Delete</th> column header
// in the Orders table <thead> (last column, after WhatsApp):
// ============================================
// <thead><tr><th>Order ID</th><th>Customer</th><th>Mobile</th><th>Product</th>
// <th>Amount</th><th>Status</th><th>Date</th><th>Update</th><th>WhatsApp</th><th>Delete</th></tr></thead>