<?php
require_once __DIR__ . '/../includes/auth.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT i.*, c.name AS customer_name, c.phone AS customer_phone, c.address AS customer_address
                       FROM invoices i LEFT JOIN customers c ON c.id = i.customer_id WHERE i.id = ?");
$stmt->execute([$id]);
$invoice = $stmt->fetch();
if (!$invoice) {
    die('Invoice not found.');
}
$itemsStmt = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
$itemsStmt->execute([$id]);
$items = $itemsStmt->fetchAll();

$phone = $invoice['customer_phone'] ?? $invoice['walkin_phone'] ?? '';
$address = $invoice['customer_address'] ?? '';
$customerName = $invoice['customer_name'] ?? $invoice['walkin_name'] ?? t('print_walkin_customer');
$logo = $config['logo_file'] ?? '';
$hasLogo = $logo !== '' && is_file(logo_dir() . '/' . $logo);
$statusClass = $invoice['status'] === 'paid' ? 'paid' : ($invoice['status'] === 'partial' ? 'partial' : 'due');
?>
<!DOCTYPE html>
<html lang="<?= e($_SESSION['lang'] ?? 'bn') ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(t('inv_view_title', $invoice['invoice_no'])) ?></title>
<style>
  :root {
    --ink: #14161f;
    --soft: #5b6070;
    --muted: #8b90a0;
    --line: #e4e6ee;
    --accent: #4f46e5;
    --paid-bg: #ecfdf3; --paid-fg: #15803d;
    --partial-bg: #fffaeb; --partial-fg: #b45309;
    --due-bg: #fef2f2; --due-fg: #dc2626;
  }
  * { box-sizing: border-box; }
  body {
    margin: 0;
    padding: 28px 20px 48px;
    background: #f4f5f9;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "Noto Sans Bengali",
      "Hind Siliguri", Roboto, Helvetica, Arial, sans-serif;
    color: var(--ink);
    font-size: 14px;
    line-height: 1.5;
    -webkit-font-smoothing: antialiased;
  }
  .sheet {
    max-width: 790px;
    margin: 0 auto;
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 4px 24px rgba(20, 22, 31, .08);
    padding: 40px 44px 34px;
  }

  /* ---- masthead ---- */
  .masthead { display: flex; justify-content: space-between; align-items: flex-start; gap: 28px; }
  .shop-logo { max-height: 56px; max-width: 190px; object-fit: contain; margin-bottom: 10px; display: block; }
  .shop-name { font-size: 20px; font-weight: 700; letter-spacing: -.015em; margin: 0 0 4px; }
  .shop-meta { color: var(--soft); font-size: 12.5px; line-height: 1.65; }
  .doc { text-align: right; flex: none; }
  .doc-label {
    font-size: 11px; font-weight: 700; letter-spacing: .16em;
    text-transform: uppercase; color: var(--accent); margin-bottom: 6px;
  }
  .doc-no { font-size: 16px; font-weight: 700; letter-spacing: -.01em; }
  .doc-date { color: var(--soft); font-size: 12.5px; margin-top: 3px; }

  .rule { height: 3px; background: var(--accent); border-radius: 2px; margin: 18px 0 22px; }

  .voided-banner {
    background: var(--due-bg); color: var(--due-fg);
    border: 1px solid #fecaca; border-radius: 8px;
    padding: 9px 14px; font-weight: 700; letter-spacing: .1em;
    text-transform: uppercase; text-align: center; margin-bottom: 18px; font-size: 12px;
  }

  /* ---- parties ---- */
  .parties { display: flex; justify-content: space-between; gap: 28px; margin-bottom: 26px; }
  .field-label {
    font-size: 10.5px; font-weight: 700; letter-spacing: .09em;
    text-transform: uppercase; color: var(--muted); margin-bottom: 6px;
  }
  .party-name { font-weight: 650; font-size: 15px; }
  .party-meta { color: var(--soft); font-size: 12.5px; line-height: 1.6; }
  .status-wrap { text-align: right; flex: none; }
  .status {
    display: inline-block; padding: 5px 14px; border-radius: 999px;
    font-size: 12px; font-weight: 700; letter-spacing: .02em;
  }
  .status.paid { background: var(--paid-bg); color: var(--paid-fg); }
  .status.partial { background: var(--partial-bg); color: var(--partial-fg); }
  .status.due { background: var(--due-bg); color: var(--due-fg); }

  /* ---- items ---- */
  table { width: 100%; border-collapse: collapse; }
  thead th {
    font-size: 10.5px; font-weight: 700; letter-spacing: .09em; text-transform: uppercase;
    color: var(--muted); text-align: left; padding: 0 10px 9px;
    border-bottom: 1.5px solid var(--line);
  }
  tbody td {
    padding: 11px 10px; border-bottom: 1px solid var(--line); vertical-align: top;
    font-variant-numeric: tabular-nums;
  }
  tbody tr:last-child td { border-bottom: 1.5px solid var(--line); }
  .col-no { width: 34px; color: var(--muted); }
  .col-qty { width: 92px; }
  .col-price, .col-total { width: 112px; text-align: right; }
  th.col-price, th.col-qty, th.col-total { text-align: right; }
  th.col-qty { text-align: left; }
  .item-name { font-weight: 600; }

  /* ---- totals ---- */
  .totals-row { display: flex; justify-content: flex-end; margin-top: 20px; }
  .totals { width: 290px; font-variant-numeric: tabular-nums; }
  .totals .line { display: flex; justify-content: space-between; padding: 5px 0; font-size: 13.5px; color: var(--soft); }
  .totals .grand {
    display: flex; justify-content: space-between;
    padding: 11px 0 10px; margin-top: 6px; border-top: 1.5px solid var(--ink);
    font-size: 16.5px; font-weight: 700; color: var(--ink);
  }
  .due-box {
    display: flex; justify-content: space-between; align-items: center;
    margin-top: 10px; padding: 11px 14px; border-radius: 9px;
    background: var(--due-bg); color: var(--due-fg);
    font-weight: 700; font-size: 14.5px;
  }
  .settled-box {
    margin-top: 10px; padding: 11px 14px; border-radius: 9px;
    background: var(--paid-bg); color: var(--paid-fg);
    font-weight: 700; font-size: 13.5px; text-align: center;
  }

  /* ---- footer ---- */
  .foot { display: flex; justify-content: space-between; align-items: flex-end; gap: 30px; margin-top: 46px; }
  .thanks { color: var(--soft); font-size: 12.5px; max-width: 330px; }
  .sign { text-align: center; flex: none; }
  .sign-line { width: 190px; border-top: 1px solid var(--ink); margin-bottom: 6px; }
  .sign-label { font-size: 11.5px; color: var(--soft); }

  /* ---- controls (screen only) ---- */
  .bar { max-width: 790px; margin: 0 auto 16px; display: flex; gap: 10px; justify-content: flex-end; }
  .bar button, .bar a {
    font-family: inherit; font-size: 13.5px; font-weight: 600;
    padding: 9px 18px; border-radius: 8px; cursor: pointer; text-decoration: none;
    border: 1px solid var(--line); background: #fff; color: var(--ink);
  }
  .bar .primary { background: var(--accent); border-color: var(--accent); color: #fff; }

  @media (max-width: 620px) {
    body { padding: 14px 10px 30px; }
    .sheet { padding: 24px 20px; border-radius: 10px; }
    .masthead, .parties, .foot { flex-direction: column; gap: 16px; }
    .doc, .status-wrap, .sign { text-align: left; }
    .totals { width: 100%; }
    .sign-line { width: 170px; }
  }

  @page { size: A4; margin: 14mm; }
  @media print {
    * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    body { background: #fff; padding: 0; font-size: 12.5px; }
    .sheet { box-shadow: none; border-radius: 0; padding: 0; max-width: none; }
    .bar { display: none !important; }
    thead { display: table-header-group; }   /* repeat headers across pages */
    tbody tr { page-break-inside: avoid; }
    .foot { page-break-inside: avoid; }
  }
</style>
</head>
<body>

<div class="bar">
  <a href="<?= url('invoices/view.php?id=' . $id) ?>">&larr; <?= e(t('print_back')) ?></a>
  <button class="primary" onclick="window.print()"><?= e(t('print_button')) ?></button>
</div>

<?php if (isset($_GET['auto'])): ?>
<script>
/* Arrived here from the invoice's Print button, so go straight to the print
   dialog instead of making the user press Print a second time. Waits for the
   load event so the logo is in place before the sheet is captured. Opening the
   page any other way (a bookmark, the browser's back button) shows the sheet
   quietly — only the button above prints then. */
window.addEventListener('load', function () { window.print(); });
</script>
<?php endif; ?>

<div class="sheet">
  <?php if ($invoice['voided']): ?>
    <div class="voided-banner"><?= e(t('inv_voided_badge')) ?></div>
  <?php endif; ?>

  <div class="masthead">
    <div>
      <?php if ($hasLogo): ?>
        <img class="shop-logo" src="<?= e(url('assets/uploads/' . $logo)) ?>" alt="<?= e($config['shop_name'] ?? '') ?>">
      <?php endif; ?>
      <h1 class="shop-name"><?= e($config['shop_name'] ?? '') ?></h1>
      <div class="shop-meta">
        <?php if (!empty($config['shop_address'])): ?><?= e($config['shop_address']) ?><br><?php endif; ?>
        <?php if (!empty($config['shop_phone'])): ?><?= e($config['shop_phone']) ?><?php endif; ?>
      </div>
    </div>
    <div class="doc">
      <div class="doc-label"><?= e(t('print_invoice_label')) ?></div>
      <div class="doc-no"><?= e($invoice['invoice_no']) ?></div>
      <div class="doc-date"><?= e(date('j M Y', strtotime($invoice['invoice_date']))) ?></div>
    </div>
  </div>

  <div class="rule"></div>

  <div class="parties">
    <div>
      <div class="field-label"><?= e(rtrim(t('print_bill_to'), ':')) ?></div>
      <div class="party-name"><?= e($customerName) ?></div>
      <div class="party-meta">
        <?php if ($phone !== ''): ?><?= e($phone) ?><br><?php endif; ?>
        <?php if ($address !== ''): ?><?= e($address) ?><?php endif; ?>
      </div>
    </div>
    <div class="status-wrap">
      <div class="field-label"><?= e(rtrim(t('inv_label_status_colon'), ':')) ?></div>
      <span class="status <?= e($statusClass) ?>"><?= e(t('status_' . $invoice['status'])) ?></span>
    </div>
  </div>

  <table>
    <thead>
      <tr>
        <th class="col-no"><?= e(t('print_th_no')) ?></th>
        <th><?= e(t('common_product')) ?></th>
        <th class="col-qty"><?= e(t('common_qty')) ?></th>
        <th class="col-price"><?= e(t('common_unit_price')) ?></th>
        <th class="col-total"><?= e(t('common_line_total')) ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($items as $i => $it): ?>
        <tr>
          <td class="col-no"><?= $i + 1 ?></td>
          <td class="item-name"><?= e($it['product_name']) ?></td>
          <td class="col-qty"><?= qty($it['quantity']) ?> <?= e($it['unit']) ?></td>
          <td class="col-price"><?= money($it['unit_price']) ?></td>
          <td class="col-total"><?= money($it['line_total']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div class="totals-row">
    <div class="totals">
      <div class="line"><span><?= e(t('common_subtotal')) ?></span><span><?= money($invoice['subtotal']) ?></span></div>
      <?php if ((float)$invoice['discount'] > 0): ?>
        <div class="line"><span><?= e(t('common_discount')) ?></span><span>− <?= money($invoice['discount']) ?></span></div>
      <?php endif; ?>
      <div class="grand"><span><?= e(t('common_total')) ?></span><span><?= money($invoice['total']) ?></span></div>
      <div class="line"><span><?= e(t('common_paid')) ?></span><span><?= money($invoice['paid_amount']) ?></span></div>

      <?php if ((float)$invoice['due_amount'] > 0): ?>
        <div class="due-box"><span><?= e(t('print_amount_due')) ?></span><span><?= money($invoice['due_amount']) ?></span></div>
      <?php else: ?>
        <div class="settled-box"><?= e(t('print_fully_paid')) ?></div>
      <?php endif; ?>
    </div>
  </div>

  <div class="foot">
    <div class="thanks"><?= e(t('print_thank_you')) ?></div>
    <div class="sign">
      <div class="sign-line"></div>
      <div class="sign-label"><?= e(t('print_signature')) ?></div>
    </div>
  </div>
</div>

</body>
</html>
