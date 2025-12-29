<?php
require __DIR__ . '/../includes/db.php';
$id = (int)($_GET['id'] ?? 0);
// Order basic
$stmt = $pdo->prepare('SELECT id,user_id,total,payment,created_at FROM orders WHERE id=? LIMIT 1');
$stmt->execute([$id]);
$order = $stmt->fetch();
if (!$order) { echo '<p>Order not found.</p>'; return; }

// Shipment
$s = $pdo->prepare('SELECT tracking_no,current_status,history,updated_at FROM shipments WHERE order_id=? LIMIT 1');
$s->execute([$order['id']]);
$shipment = $s->fetch();
$history = [];
if ($shipment) {
  $history = json_decode($shipment['history'], true) ?: [];
  // Auto-advance demo: randomly add next step if not delivered
  $phases = ['Order Placed','Packed','Shipped','Out for Delivery','Delivered'];
  $current = array_column($history,'status');
  if (count($current) < count($phases)) {
    if (rand(0,1)) {
      $history[] = ['status'=>$phases[count($current)],'time'=>date('c')];
      $nextStatus = $phases[count($current)];
      $upd = $pdo->prepare('UPDATE shipments SET current_status=?, history=? WHERE order_id=?');
      $upd->execute([$nextStatus, json_encode($history), $order['id']]);
      $shipment['current_status'] = $nextStatus;
    }
  }
}
?>
<section>
  <h2 class="text-2xl font-bold mb-2">Order #<?= (int)$order['id'] ?></h2>
  <div class="text-neutral-400 mb-2">Payment: <?= htmlspecialchars(strtoupper($order['payment'])) ?> • Total: ₱<?= number_format($order['total'],2) ?></div>
  <?php if ($shipment): ?>
    <div class="text-neutral-400 mb-6">Tracking: <span class="text-neutral-200 font-mono"><?= htmlspecialchars($shipment['tracking_no']) ?></span> • Status: <span class="text-neutral-200"><?= htmlspecialchars($shipment['current_status']) ?></span></div>
  <?php endif; ?>
  <ol class="space-y-3">
    <?php foreach ($history as $t): ?>
      <li class="rounded-lg border border-neutral-800 bg-neutral-900 p-3 flex items-center justify-between">
        <div class="font-semibold"><?= htmlspecialchars($t['status']) ?></div>
        <div class="text-neutral-400 text-sm"><?= date('M d, Y H:i', strtotime($t['time'])) ?></div>
      </li>
    <?php endforeach; ?>
    <?php if (!$history): ?>
      <li class="rounded-lg border border-neutral-800 bg-neutral-900 p-3">No tracking events yet.</li>
    <?php endif; ?>
  </ol>
</section>
