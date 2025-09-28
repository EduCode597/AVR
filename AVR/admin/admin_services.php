<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/ui.php';
ensure_admin();
$msg = $_SESSION['flash_msg'] ?? null; unset($_SESSION['flash_msg']);
if($_SERVER['REQUEST_METHOD']==='POST'){
  $id=(int)($_POST['id']??0); $act=$_POST['action']??''; if($id){
    $map=['confirm'=>'Confirmed','done'=>'Done','cancel'=>'Cancelled'];
    if(isset($map[$act])){ $st=$map[$act]; $stmt=db()->prepare('UPDATE services SET status=:s WHERE id=:id'); $stmt->execute([':s'=>$st, ':id'=>$id]); $_SESSION['flash_msg']='Service #'.$id.' updated to '.$st; header('Location: ' . BASE_URL . '/admin/admin_services.php'); exit; }
  }
}
$services = db()->query('SELECT * FROM services ORDER BY created_at DESC')->fetchAll();
admin_layout_start('Services');
?>
  <?php if($msg): ?><div class="card"><?= h($msg); ?></div><?php endif; ?>
<?php
?>
  <div style="overflow:auto"><table class="table rtable"><thead><tr><th>ID</th><th>Name</th><th>Type</th><th>Date</th><th>Description / Concern</th><th>Status</th><th>Actions</th></tr></thead><tbody>
  <?php foreach ($services as $s): ?>
    <tr>
      <td data-label="ID"><?= (int)$s['id']; ?></td>
      <td data-label="Name"><?= h($s['customer_name']); ?></td>
      <td data-label="Type"><?= h($s['service_type']); ?></td>
  <td data-label="Date"><?= h($s['preferred_date']); ?></td>
  <td data-label="Description / Concern" style="max-width:260px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="<?= h($s['description']); ?>"><?= h($s['description']); ?></td>
      <td data-label="Status"><span class="badge"><?= h($s['status']); ?></span></td>
      <td data-label="Actions">
        <div style="display:flex;gap:6px;flex-wrap:wrap">
        <?php if($s['status']==='Pending'): ?>
          <form method="post"><input type="hidden" name="id" value="<?= (int)$s['id']; ?>"><input type="hidden" name="action" value="confirm"><button class="btn">Confirm</button></form>
          <form method="post" onsubmit="return confirm('Cancel this request?');"><input type="hidden" name="id" value="<?= (int)$s['id']; ?>"><input type="hidden" name="action" value="cancel"><button class="btn secondary">Cancel</button></form>
        <?php elseif($s['status']==='Confirmed'): ?>
          <form method="post"><input type="hidden" name="id" value="<?= (int)$s['id']; ?>"><input type="hidden" name="action" value="done"><button class="btn">Mark as Done</button></form>
          <form method="post" onsubmit="return confirm('Cancel this request?');"><input type="hidden" name="id" value="<?= (int)$s['id']; ?>"><input type="hidden" name="action" value="cancel"><button class="btn secondary">Cancel</button></form>
        <?php endif; ?>
        </div>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
<?php admin_layout_end(); ?>
