<?php
/* One-time database upgrade page.
   After uploading a new version of the app, open this once in the browser and
   press the button: it adds whatever the newer code expects and the live
   database does not have yet. Every step is written as "create only if
   missing", so running it a second time changes nothing and cannot harm
   existing data — it never drops, alters or rewrites a row.
   Login is required, so a visitor cannot touch the database. */
require_once __DIR__ . '/includes/auth.php';

// Each step: what to look for, and the SQL that supplies it when absent.
$steps = [
    [
        'label'  => t('upg_step_suppliers'),
        'check'  => function (PDO $pdo) { return (bool)$pdo->query("SHOW TABLES LIKE 'suppliers'")->fetchColumn(); },
        'sql'    => "CREATE TABLE IF NOT EXISTS suppliers (
                       id INT AUTO_INCREMENT PRIMARY KEY,
                       name VARCHAR(150) NOT NULL,
                       company VARCHAR(150) DEFAULT NULL,
                       phone VARCHAR(30) DEFAULT NULL,
                       note VARCHAR(255) DEFAULT NULL,
                       created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                       INDEX idx_suppliers_name (name),
                       INDEX idx_suppliers_company (company),
                       INDEX idx_suppliers_phone (phone)
                     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ],
];

$ran = [];
$failed = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    foreach ($steps as $step) {
        if ($step['check']($pdo)) {
            continue;
        }
        try {
            $pdo->exec($step['sql']);
            $ran[] = $step['label'];
        } catch (PDOException $ex) {
            // Report which step failed rather than dropping to the generic
            // error page — the shopkeeper needs to know what to tell support.
            error_log('Upgrade failed on "' . $step['label'] . '": ' . $ex->getMessage());
            $failed = $step['label'];
            break;
        }
    }
}

// Re-read the live state after any work, so the page always shows the truth.
$state = [];
$pending = 0;
foreach ($steps as $step) {
    $ok = $step['check']($pdo);
    $state[] = ['label' => $step['label'], 'ok' => $ok];
    if (!$ok) {
        $pending++;
    }
}

include __DIR__ . '/includes/header.php';
?>
<h4 class="mb-3"><?= e(t('upg_page_title')) ?></h4>

<?php if ($failed !== null): ?>
  <div class="alert alert-danger"><?= e(t('upg_failed', $failed)) ?></div>
<?php elseif ($ran): ?>
  <div class="alert alert-success"><?= e(t('upg_applied', count($ran))) ?></div>
<?php endif; ?>

<div class="card p-4" style="max-width:640px;">
  <p class="text-muted"><?= e(t('upg_intro')) ?></p>

  <table class="table mb-3">
    <thead><tr><th><?= e(t('upg_th_item')) ?></th><th><?= e(t('upg_th_status')) ?></th></tr></thead>
    <tbody>
    <?php foreach ($state as $s): ?>
      <tr>
        <td><?= e($s['label']) ?></td>
        <td data-label="<?= e(t('upg_th_status')) ?>">
          <?php if ($s['ok']): ?>
            <span class="badge bg-success"><?= e(t('upg_status_ready')) ?></span>
          <?php else: ?>
            <span class="text-danger"><?= e(t('upg_status_missing')) ?></span>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <?php if ($pending > 0): ?>
    <form method="post">
      <?= csrf_field() ?>
      <button class="btn btn-primary"><?= e(t('upg_run_button')) ?></button>
    </form>
  <?php else: ?>
    <p class="mb-0"><strong><?= e(t('upg_all_done')) ?></strong></p>
    <p class="text-muted mb-0"><?= e(t('upg_delete_hint')) ?></p>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
