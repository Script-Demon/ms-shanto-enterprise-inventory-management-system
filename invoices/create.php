<?php
require_once __DIR__ . '/../includes/auth.php';

$customers = $pdo->query("SELECT id, name, phone FROM customers ORDER BY name")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<h4 class="mb-3"><?= e(t('inv_new_title')) ?></h4>
<div class="row">
  <div class="col-md-4">
    <div class="card p-3 mb-3">
      <label class="form-label"><?= e(t('inv_label_customer')) ?></label>
      <select id="customerSelect" class="form-select mb-2">
        <option value=""><?= e(t('inv_walkin_option')) ?></option>
        <?php foreach ($customers as $c): ?>
          <option value="<?= $c['id'] ?>"><?= e($c['name']) ?><?= $c['phone'] ? ' (' . e($c['phone']) . ')' : '' ?></option>
        <?php endforeach; ?>
      </select>
      <div id="walkinFields">
        <input type="text" id="walkinName" class="form-control mb-2" placeholder="<?= e(t('inv_walkin_name_placeholder')) ?>">
        <input type="text" id="walkinPhone" class="form-control mb-2" placeholder="<?= e(t('inv_walkin_phone_placeholder')) ?>">
      </div>
      <label class="form-label"><?= e(t('inv_label_date')) ?></label>
      <input type="date" id="invoiceDate" class="form-control" value="<?= date('Y-m-d') ?>">
    </div>
    <div class="card p-3">
      <label class="form-label"><?= e(t('inv_label_search_product')) ?></label>
      <input type="text" id="productSearch" class="form-control" placeholder="<?= e(t('inv_search_placeholder')) ?>">
      <div id="searchResults" class="list-group mt-2"></div>
    </div>
  </div>
  <div class="col-md-8">
    <div class="card p-3">
      <div class="table-responsive">
      <table class="table" id="itemsTable">
        <thead><tr><th><?= e(t('common_product')) ?></th><th style="width:140px"><?= e(t('common_qty')) ?></th><th style="width:110px"><?= e(t('common_stock')) ?></th><th style="width:120px"><?= e(t('common_unit_price')) ?></th><th style="width:120px"><?= e(t('common_line_total')) ?></th><th></th></tr></thead>
        <tbody id="itemsBody"></tbody>
      </table>
      </div>
      <div class="row justify-content-end">
        <div class="col-md-5">
          <div class="d-flex justify-content-between"><span><?= e(t('common_subtotal')) ?></span><strong id="subtotalDisplay">0.00</strong></div>
          <div class="d-flex justify-content-between align-items-center mt-2">
            <span><?= e(t('common_discount')) ?></span>
            <input type="number" id="discountInput" class="form-control form-control-sm" style="width:120px" value="0" step="0.01">
          </div>
          <div class="d-flex justify-content-between mt-2 fs-5"><span><?= e(t('common_total')) ?></span><strong id="totalDisplay">0.00</strong></div>
          <div class="d-flex justify-content-between align-items-center mt-2">
            <span><?= e(t('inv_label_paid_now')) ?></span>
            <input type="number" id="paidInput" class="form-control form-control-sm" style="width:120px" value="0" step="0.01">
          </div>
          <div class="d-flex justify-content-between mt-2"><span><?= e(t('common_due')) ?></span><strong id="dueDisplay" class="text-danger">0.00</strong></div>
        </div>
      </div>
      <div id="formError" class="alert alert-danger mt-3" style="display:none"></div>
      <button id="saveInvoiceBtn" class="btn btn-primary mt-3"><?= e(t('inv_save_button')) ?></button>
    </div>
  </div>
</div>
<script>
window.APP = {
  searchUrl: <?= json_encode(url('api/search_products.php')) ?>,
  saveUrl: <?= json_encode(url('api/save_invoice.php')) ?>,
  viewUrlBase: <?= json_encode(url('invoices/view.php?id=')) ?>,
  csrfToken: <?= json_encode(csrf_token()) ?>,
  currency: <?= json_encode($config['currency'] ?? '') ?>,
  imageIcon: <?= json_encode(icon('image')) ?>,
  i18n: {
    addItemRequired: <?= json_encode(t('js_add_item_required')) ?>,
    notEnoughStock: <?= json_encode(t('js_not_enough_stock')) ?>,
    saving: <?= json_encode(t('js_saving')) ?>,
    saveInvoice: <?= json_encode(t('js_save_invoice')) ?>,
    saveFailed: <?= json_encode(t('js_save_failed')) ?>,
    stockLabel: <?= json_encode(t('stock_stock_label')) ?>,
    sellPriceLabel: <?= json_encode(t('inv_sell_price_label')) ?>,
    qtyLabel: <?= json_encode(t('common_qty')) ?>,
    stockColLabel: <?= json_encode(t('common_stock')) ?>,
    priceLabel: <?= json_encode(t('common_unit_price')) ?>,
    lineTotalLabel: <?= json_encode(t('common_line_total')) ?>
  }
};
</script>
<script src="<?= url('assets/js/invoice.js') ?>"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
