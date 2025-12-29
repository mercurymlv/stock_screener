<?php
// connect securly to the database
require_once __DIR__ . '/includes/db_connect.php';
?>

<?php
// include header
include __DIR__ . '/includes/header.php';
?>

<h1>Overview</h1>
<?php

// Choose a watchlist to display (example: watch_list_id = 1)
$watchlist_id = 1;

// Fetch watchlist items
$stmt = $pdo->prepare("
    SELECT symbol, notes, created_at
    FROM watchlist_items
    WHERE watch_list_id = ?
    ORDER BY symbol
");
$stmt->execute([$watchlist_id]);
$items = $stmt->fetchAll();
?>

<section>
  <h2>Watchlist Overview</h2>
  <table>
    <thead>
      <tr>
        <th>Symbol</th>
        <th>Notes</th>
        <th>Added</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach($items as $item): ?>
        <tr>
          <td><?= htmlspecialchars($item['symbol']) ?></td>
          <td><?= htmlspecialchars($item['notes']) ?></td>
          <td><?= htmlspecialchars($item['created_at']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>


<?php include __DIR__ . '/includes/footer.php'; ?>