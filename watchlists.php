<?php
require_once __DIR__ . '/includes/db_connect.php';

// Add new symbol to watchlist from text form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add'&& isset($_POST['symbol'], $_POST['watch_list_id'])) {
    $symbol = strtoupper(trim($_POST['symbol']));
    $watchListId = (int)$_POST['watch_list_id'];

    if ($symbol !== '') {
        $stmt = $pdo->prepare("
            INSERT INTO watchlist_items (watch_list_id, symbol, active)
            VALUES (?, ?, 1)
            ON DUPLICATE KEY UPDATE
                active = 1,
                created_at = created_at;
        ");
        $stmt->execute([$watchListId, $symbol]);
    }

    // PRG pattern — avoids duplicate submits
    header("Location: watchlists.php?watchlist_id=" . $watchListId);
    exit;
}


// delete symbol from watchlist from trash can icon
// doesn't actually delete from watchlist, just sets to inactive
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'delete'
    && isset($_POST['symbol'], $_POST['watch_list_id'])
) {
    $symbol = strtoupper(trim($_POST['symbol']));
    $watchListId = (int)$_POST['watch_list_id'];

    $stmt = $pdo->prepare("
      UPDATE watchlist_items
      SET active = 0
      WHERE watch_list_id = ?
        AND symbol = ?
    ");
    $stmt->execute([$watchListId, $symbol]);

    header("Location: watchlists.php?watchlist_id=" . $watchListId);
    exit;
}

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

if (!$selected_watchlist_id) {
    echo "<p><em>No primary watchlist configured.</em></p>";
}

/**
 * 3. Fetch watchlist items
 */
$items = [];

if ($selected_watchlist_id) {
    $itemsStmt = $pdo->prepare("
        SELECT wi.symbol, t.name as company_name, t.sector as sector, t.industry as industry, wi.notes as notes, DATE(wi.created_at) as added_at
        FROM watchlist_items wi
        LEFT JOIN tickers t ON wi.symbol = t.symbol
        WHERE wi.active = 1 
        AND wi.watch_list_id = ?
        ORDER BY wi.symbol
    ");
    $itemsStmt->execute([$selected_watchlist_id]);
    $items = $itemsStmt->fetchAll();
}
?>



<?php
$selected_watchlist_name = '';
foreach ($watchlists as $wl) {
    if ($wl['watch_list_id'] == $selected_watchlist_id) {
        $selected_watchlist_name = $wl['name'];
        break;
    }
}
?>

<?php if ($selected_watchlist_id): ?>

<!-- Watchlist Overview Table -->
  <section>
    <div class="d-flex align-items-end justify-content-start gap-2 mb-3">
      <h2 class="mb-0">Watchlist Overview</h2>
      <a href="watchlists_manage.php" class="text-decoration-none d-inline-flex align-items-center" title="Manage Watchlists">
        <i class="bi bi-gear fs-5 me-1 fw-bold"></i> <span class="small">Manage</span>
      </a>
    </div>


    <div class="table-card">
      <div class="d-flex align-items-center gap-3 mb-3">
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
        
        <!-- Purely for spacing -->
        <div class="flex-grow-1"></div>

        <!-- Add symbol text box form -->
        <form method="post" class="add-symbol-form">
          <input type="hidden" name="action" value="add">
          <input type="hidden" name="watch_list_id" value="<?= $selected_watchlist_id ?>">

          <input
            type="text"
            name="symbol"
            placeholder="Add (e.g. AAPL)"
            required
            maxlength="10"
          >

          <button type="submit" class="btn btn-primary btn-sm">Add</button>
        </form>
      </div>
  <?php if (empty($items)): ?>
    <p>No symbols in this watchlist.</p>

  <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Symbol</th>
            <th>Name</th>
            <th>Sector</th>
            <th>Industry</th>
            <th>Notes</th>
            <th>Added</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $item): ?>
            <tr>
              <td>
                <a href="snapshot.php?symbol=<?= urlencode($item['symbol']) ?>">
                  <?= htmlspecialchars($item['symbol']) ?>
                </a>
              </td>
              <td><?= htmlspecialchars($item['company_name']) ?></td>
              <td><?= htmlspecialchars($item['sector']) ?></td>
              <td><?= htmlspecialchars($item['industry']) ?></td>
              <td><?= htmlspecialchars($item['notes']) ?></td>
              <td class="small text-nowrap"><?= htmlspecialchars($item['added_at']) ?></td>
              
              <!-- actions column -->
              <td class="align-middle">
                <div class="d-flex align-items-center justify-content-center gap-2 h-100">
                  <a href="https://finance.yahoo.com/quote/<?= htmlspecialchars($item['symbol']) ?>" 
                    target="_blank" rel="noopener noreferrer" title="Yahoo! Finance"
                    class="text-decoration-none d-inline-flex align-items-center">
                    <i class="bi bi-box-arrow-up-right icon-btn"></i>
                  </a>

                  <form method="post" onsubmit="return confirm('Remove <?= htmlspecialchars($item['symbol']) ?>?');" class="m-0 d-inline-flex align-items-center">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="symbol" value="<?= htmlspecialchars($item['symbol']) ?>">
                    <input type="hidden" name="watch_list_id" value="<?= $selected_watchlist_id ?>">
                    <button type="submit" class="p-0 border-0 bg-transparent text-danger line-height-1 icon-btn" title="Remove from watchlist">
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
<?php endif; ?>

<!-------------------------------------------->
<!-- CHARTS -->
<!-------------------------------------------->

<!-- Query to get sector distribution -->
<?php if ($selected_watchlist_id): ?>
  <?php
  $sectorStmt = $pdo->prepare("
      SELECT COALESCE(t.sector, 'Unknown') AS sector, COUNT(*) AS count
      FROM watchlist_items wi
      LEFT JOIN tickers t ON wi.symbol = t.symbol
      WHERE wi.active = 1 
      and wi.watch_list_id = ?
      GROUP BY sector
      ORDER BY count DESC
  ");
  $sectorStmt->execute([$selected_watchlist_id]);
  $sectorData = $sectorStmt->fetchAll(PDO::FETCH_ASSOC);

  $labels = array_column($sectorData, 'sector');
  $values = array_map('intval', array_column($sectorData, 'count'));
  ?>

  <!-- Query to get type distribution -->
   <?php
   $typeStmt = $pdo->prepare("
    SELECT COALESCE(t.type, 'Unknown') AS type, COUNT(*) AS count
    FROM watchlist_items wi
    LEFT JOIN tickers t ON wi.symbol = t.symbol
    WHERE wi.active = 1
    AND wi.watch_list_id = ?
    GROUP BY type
    ORDER BY count DESC
    ");
    $typeStmt->execute([$selected_watchlist_id]);
    $typeData = $typeStmt->fetchAll(PDO::FETCH_ASSOC);

    $typeLabels = array_column($typeData, 'type');
    $typeValues = array_map('intval', array_column($typeData, 'count'));
    ?>

<!-- display the charts -->
  <section>
  <h2>Composition</h2>

  <div class="chart-grid">
    <div class="chart-card">
      <h3>By Sector</h3>
      <canvas id="sectorPieChart"></canvas>
    </div>
    <div class="chart-card">
      <h3>By Type</h3>
      <canvas id="typePieChart"></canvas>
    </div>
  </div>
</section>

<?php endif; ?>


<!-- Scripts to render Charts -->

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>


<!-- By Sector -->
<script>
const ctx = document.getElementById('sectorPieChart');
if (ctx) {
  new Chart(ctx, {
    type: 'pie',
    data: {
      labels: <?= json_encode($labels) ?>,
      datasets: [{
        data: <?= json_encode($values) ?>,
        backgroundColor: [
          '#4E79A7', '#59A14F', '#E15759', '#F28E2B',
          '#76B7B2', '#EDC948', '#B07AA1', '#FF9DA7'
        ],
      }]
    },
    options: {
      plugins: {
        tooltip: {
          callbacks: {
            label: function (context) {
              const label = context.label || '';
              const value = context.raw || 0;
              const total = context.dataset.data.reduce((a, b) => a + b, 0);
              const pct = ((value / total) * 100).toFixed(1);

              return `${label}: ${value} (${pct}%)`;
            }
          }
        },
        legend: { position: 'right' }
      }
    }
  });
}

// By Type
const typeCtx = document.getElementById('typePieChart');
if (typeCtx) {
  new Chart(typeCtx, {
    type: 'pie',
    data: {
      labels: <?= json_encode($typeLabels) ?>,
      datasets: [{
        data: <?= json_encode($typeValues) ?>,
        backgroundColor: ['#59A14F', '#4E79A7', '#F28E2B', '#E15759']
      }]
    },
    options: {
      plugins: {
        tooltip: {
          callbacks: {
            label: function (context) {
              const label = context.label || '';
              const value = context.raw || 0;
              const total = context.dataset.data.reduce((a, b) => a + b, 0);
              const pct = ((value / total) * 100).toFixed(1);

              return `${label}: ${value} (${pct}%)`;
            }
          }
        },
        legend: { position: 'right' }
      }
    }
  });
}
</script>




<?php include __DIR__ . '/includes/footer.php'; ?>
