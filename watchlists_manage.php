<?php
require_once __DIR__ . '/includes/db_connect.php';


// 1. Add new watchlist
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add' && !empty($_POST['new_watchlist_name'])) {
    $wlName = trim($_POST['new_watchlist_name']);

    if ($wlName !== '') {
        $stmt = $pdo->prepare("
            INSERT INTO watchlists (name, active)
            VALUES (?, 1)
            ON DUPLICATE KEY UPDATE active = 1
        ");
        $stmt->execute([$wlName]);
    }
    header("Location: watchlists_manage.php");
    exit;
}

// 2. Delete (Deactivate) watchlist
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete' && !empty($_POST['watch_list_id'])) {
    $watchListId = (int)$_POST['watch_list_id'];

    $stmt = $pdo->prepare("
        UPDATE watchlists
        SET active = 0, is_primary = 0
        WHERE watch_list_id = ?
    ");
    $stmt->execute([$watchListId]);

    header("Location: watchlists_manage.php");
    exit;
}

// 3. Set a watchlist as primary
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'set_primary' && !empty($_POST['watch_list_id'])) {
    $watchListId = (int)$_POST['watch_list_id'];

    // Step A: Strip primary status from ALL watchlists
    $pdo->query("UPDATE watchlists SET is_primary = 0");

    // Step B: Set the selected one as primary
    $stmt = $pdo->prepare("UPDATE watchlists SET is_primary = 1 WHERE watch_list_id = ?");
    $stmt->execute([$watchListId]);

    header("Location: watchlists_manage.php");
    exit;
}


// include header
include __DIR__ . '/includes/header.php';


/**
 * Fetch all active watchlists for display
 */
$watchlistsStmt = $pdo->query("
    SELECT w.watch_list_id, w.name, w.is_primary, DATE(w.created_at) as created_at, COUNT(wi.symbol) AS ticker_count
    FROM watchlists w
    LEFT JOIN watchlist_items wi
      ON wi.watch_list_id = w.watch_list_id AND wi.active = 1
    WHERE w.active = 1
    GROUP BY w.watch_list_id, name, w.is_primary, w.created_at
    ORDER BY w.name ASC
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

?>


<!-- Watchlist Overview Table -->
<section>

    <h2>Watchlist Maintenance</h2>


    <div class="table-card">
        <div class="d-flex justify-content-end align-items-center gap-1 mb-3">
            <form method="post" class="d-flex gap-2 align-items-center mb-0">
                <input type="hidden" name="action" value="add">
                <input type="text" name="new_watchlist_name" class="form-control form-control-sm" placeholder="New watchlist name..." required maxlength="50">
                <button type="submit" class="btn btn-primary btn-sm text-nowrap">Add Watchlist</button>
            </form>
        </div>
    <?php if (empty($watchlists)): ?>
        <p>No active watchlists.</p>
    <?php else: ?>
        <table>
            <thead>
            <tr>
                <th>Watchlist Name</th>
                <th>Number of Tickers</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($watchlists as $wl): ?>
                <tr>
                <td><?= htmlspecialchars($wl['name']) ?></td>
                <td><?= htmlspecialchars($wl['ticker_count']) ?></td>
                <td><?= htmlspecialchars($wl['created_at']) ?></td>
                
                <!-- actions column -->
                <td class="align-middle">
                    <div class="d-flex align-items-center justify-content-center gap-3 h-100">
                        <?php if ($wl['is_primary'] == 1): ?>
                            <i class="bi bi-star-fill text-warning" title="Primary Watchlist"></i>
                        <?php else: ?>
                            <form method="post" class="m-0 d-inline-flex">
                                <input type="hidden" name="action" value="set_primary">
                                <input type="hidden" name="watch_list_id" value="<?= $wl['watch_list_id'] ?>">
                                <button type="submit" class="p-0 border-0 bg-transparent text-muted icon-btn" title="Set as Primary">
                                    <i class="bi bi-star"></i>
                                </button>
                            </form>
                        <?php endif; ?>

                    <form method="post" onsubmit="return confirm('Remove <?= htmlspecialchars($wl['name']) ?>?');" class="m-0 d-inline-flex align-items-center">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="watch_list_id" value="<?= htmlspecialchars($wl['watch_list_id']) ?>">
                        <button type="submit" class="p-0 border-0 bg-transparent text-danger line-height-1 icon-btn" title="Remove watchlist">
                        <i class="bi bi-trash"></i>
                        </button>
                    </form>
                    </div>
                </td>

                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
  <?php endif; ?>
</section>



<?php include __DIR__ . '/includes/footer.php'; ?>
