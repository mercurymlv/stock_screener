<?php
require_once __DIR__ . '/includes/db_connect.php';
include __DIR__ . '/includes/header.php';

/**
 * 1. Fetch all active watchlists (for dropdown)
 */
$watchlistsStmt = $pdo->query("
    SELECT watch_list_id, name
    FROM watchlists
    WHERE active = 1
    ORDER BY name
");
$watchlists = $watchlistsStmt->fetchAll();

/**
 * 2. Determine selected watchlist
 * Priority:
 *   a) ?watchlist_id from GET
 *   b) primary active watchlist
 *   c) first active watchlist
 */
$selected_watchlist_id = $_GET['watchlist_id'] ?? null;

if (!$selected_watchlist_id) {
    // Try primary
    $stmt = $pdo->query("
        SELECT watch_list_id
        FROM watchlists
        WHERE is_primary = 1
          AND active = 1
        LIMIT 1
    ");
    $selected_watchlist_id = $stmt->fetchColumn();
}

if (!$selected_watchlist_id) {
    // Fallback: first active
    $stmt = $pdo->query("
        SELECT watch_list_id
        FROM watchlists
        WHERE active = 1
        ORDER BY watch_list_id
        LIMIT 1
    ");
    $selected_watchlist_id = $stmt->fetchColumn();
}

/**
 * 3. Fetch watchlist items
 */
$items = [];

if ($selected_watchlist_id) {
    $itemsStmt = $pdo->prepare("
        SELECT symbol, notes, created_at
        FROM watchlist_items
        WHERE watch_list_id = ?
        ORDER BY symbol
    ");
    $itemsStmt->execute([$selected_watchlist_id]);
    $items = $itemsStmt->fetchAll();
}
?>

<!-- Watchlist selector -->
<form method="get">
  <label for="watchlist_id">Select watchlist:</label>
  <select name="watchlist_id" id="watchlist_id" onchange="this.form.submit()">
    <?php foreach ($watchlists as $wl): ?>
      <option value="<?= $wl['watch_list_id'] ?>"
        <?= ($wl['watch_list_id'] == $selected_watchlist_id) ? 'selected' : '' ?>>
        <?= htmlspecialchars($wl['name']) ?>
      </option>
    <?php endforeach; ?>
  </select>
</form>

<?php if ($selected_watchlist_id): ?>
<section>
  <h2>Watchlist Overview</h2>

  <?php if (empty($items)): ?>
    <p>No symbols in this watchlist.</p>
  <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>Symbol</th>
          <th>Notes</th>
          <th>Added</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $item): ?>
          <tr>
            <td><?= htmlspecialchars($item['symbol']) ?></td>
            <td><?= htmlspecialchars($item['notes']) ?></td>
            <td><?= htmlspecialchars($item['created_at']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
