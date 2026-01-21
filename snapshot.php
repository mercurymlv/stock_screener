<?php
// connect securly to the database
require_once __DIR__ . '/includes/db_connect.php';

// Process Form Submission to Change Symbol
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_symbol') {
    $new_symbol = strtoupper(trim($_POST['new_symbol']));
    
    // Redirect to the same page with the new symbol in the GET parameters
    header("Location: snapshot.php?symbol=" . urlencode($new_symbol));
    exit;
}

?>


<?php
// include header
include __DIR__ . '/includes/header.php';
?>

<?php
// <!-- default symbol -->
$symbol = $_GET['symbol'] ?? 'ADP';

// Fetch fundamentals + latest signal data
$symbol = $_GET['symbol'] ?? 'ADP';

// We pull the "latest" value for every indicator we track for this symbol
$stmt = $pdo->prepare("
    SELECT 
        t.*,
        MAX(CASE WHEN ls.indicator = 'z_score_20' THEN ls.value END) as z_score,
        MAX(CASE WHEN ls.indicator = 'rsi_14' THEN ls.value END) as rsi,
        MAX(CASE WHEN ls.indicator = 'pe_ratio' THEN ls.value END) as pe_ratio,
        MAX(CASE WHEN ls.indicator = 'forward_pe' THEN ls.value END) as forward_pe,
        MAX(CASE WHEN ls.indicator = 'peg_ratio' THEN ls.value END) as peg_ratio,
        MAX(CASE WHEN ls.indicator = 'div_yield' THEN ls.value END) as div_yield,
        MAX(CASE WHEN ls.indicator = 'debt_equity' THEN ls.value END) as debt_equity,
        MAX(CASE WHEN ls.indicator = 'current_ratio' THEN ls.value END) as current_ratio,
        MAX(CASE WHEN ls.indicator = 'roe' THEN ls.value END) as roe,
        MAX(CASE WHEN ls.indicator = 'rev_growth' THEN ls.value END) as rev_growth
    FROM tickers t
    LEFT JOIN latest_signals_v ls ON t.symbol = ls.symbol
    WHERE t.symbol = ?
    GROUP BY t.symbol
");

$stmt->execute([$symbol]);
$stock = $stmt->fetch();

// if (!$stock) {
//     die("Symbol not found.");
// }
?>

<div class="container mt-4">
    <div class="d-flex justify-content-end align-items-center gap-1 mb-0">
        <form method="post" class="d-flex gap-2 align-items-center mb-0">
            <input type="hidden" name="action" value="change_symbol">
            <input type="text" name="new_symbol" class="form-control form-control-sm" placeholder="Enter symbol..." required maxlength="10">
            <button type="submit" class="btn btn-primary btn-sm text-nowrap">Change Symbol</button>
        </form>
    </div>
    <?php if (!$stock): ?>
        <div class="alert alert-warning shadow-sm border-0 d-flex align-items-center" role="alert">
            <div class="me-3 fs-3">🔍</div>
            <div>
                <h5 class="alert-heading mb-1">Symbol Not Found: <strong><?= htmlspecialchars($symbol) ?></strong></h5>
                <p class="mb-0 text-muted">This ticker might not be in our database yet. Remember, our feed updates via cron job once per day.</p>
            </div>
        </div>
    <?php else: ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><?php echo $stock['symbol']; ?> : <small class="text-muted"><?php echo $stock['name']; ?></small></h2>
    </div>

    <div class="row g-3">
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="stock-card">
                <div class="stock-card-header header-valuation">
                    <h6 class="card-section-title">📊 Valuation</h6>
                </div>
                <div class="card-body">
                    <div class="metric-row">
                        <span class="metric-label">P/E Ratio</span>
                        <span class="metric-value">
                            <?= number_format($stock['pe_ratio'], 2); ?>
                        </span>
                    </div>
                    <div class="metric-row">
                        <span class="metric-label">Forward P/E</span>
                        <span class="metric-value">
                            <?= number_format($stock['forward_pe'], 2); ?>
                        </span>
                    </div>
                    <div class="metric-row">
                        <span class="metric-label">PEG Ratio</span>
                        <span class="metric-value">
                            <?= number_format($stock['peg_ratio'], 2); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6 mb-3">
            <div class="stock-card">
                <div class="stock-card-header header-technical">
                    <h6 class="card-section-title"><i class="bi bi-stopwatch"></i> Technical Timing</h6>
                </div>
                <div class="card-body">
                    <div class="metric-row">
                        <span class="metric-label">Z-Score:</span>
                        <span class="metric-value"><?php echo number_format($stock['z_score'], 2); ?></span>
                    </div>
                    <div class="metric-row">    
                        <span class="metric-label">RSI (14):</span>
                        <span class="metric-value"><?php echo number_format($stock['rsi'], 1); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6 mb-3">
            <div class="stock-card">
                <div class="stock-card-header header-growth">
                    <h6 class="card-section-title">🌱 Yield & Growth</h6>
                </div>
                <div class="card-body">
                    <div class="metric-row">
                        <span class="metric-label">Div Yield:</span>
                        <span class="metric-value"><?php echo number_format($stock['div_yield'], 2); ?>%</span>
                    </div>
                    <div class="metric-row">
                        <span class="metric-label">Revenue Growth:</span>
                        <span class="metric-value"><?php echo number_format($stock['rev_growth'], 2); ?>%</span>
                    </div>
                    <div class="metric-row">
                        <span class="metric-label">ROE:</span>
                        <span class="metric-value"><?php echo number_format($stock['roe'], 2); ?>%</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6 mb-3">
            <div class="stock-card">
                <div class="stock-card-header header-balance">
                    <h6 class="card-section-title"><i class="bi bi-currency-dollar"></i> Balance Sheet</h6>
                </div>
                <div class="card-body">
                    <div class="metric-row">
                        <span class="metric-label">Debt/Equity:</span>
                        <span class="metric-value"><?php echo number_format($stock['debt_equity'], 2); ?></span>
                    </div>
                    <div class="metric-row">
                        <span class="metric-label">Current Ratio:</span>
                        <span class="metric-value"><?php echo number_format($stock['current_ratio'], 2); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>


<?php include __DIR__ . '/includes/footer.php'; ?>