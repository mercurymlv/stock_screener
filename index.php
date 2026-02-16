<?php
// connect securly to the database
require_once __DIR__ . '/includes/db_connect.php';
?>

<?php
// include header
include __DIR__ . '/includes/header.php';
?>

<!-- Start Singnals -->
<!-- // Z-score - both buy and sell potential -->

<?php
$indicator = 'z_score_20';

$sqlBuy = "
SELECT
        ls.symbol,
        t.name,
        ls.value AS z_score,
        rsi.value AS RSI,
        ls.as_of_date,
        GROUP_CONCAT(
          CONCAT(
            '<a href=''watchlists.php?watchlist_id=',
            w.watch_list_id,
            '''>',
            w.name,
            '</a>'
          )
          ORDER BY w.name
          SEPARATOR ', '
        ) AS watchlists_html
    FROM latest_signals_v ls
    JOIN watchlist_items wi
      ON wi.symbol = ls.symbol and wi.active = 1
    LEFT JOIN watchlists w
      ON w.watch_list_id = wi.watch_list_id and w.active = 1
    LEFT JOIN tickers t
      ON t.symbol = ls.symbol
    LEFT JOIN latest_signals_v rsi
      ON ls.symbol = rsi.symbol and rsi.indicator = 'rsi_14'
    WHERE ls.indicator = :indicator
    GROUP BY
        ls.symbol,
        t.name,
        ls.value,
        rsi.value,
        ls.as_of_date
    ORDER BY ls.value ASC
    LIMIT 10;
";

$sqlSell = "
SELECT
        ls.symbol,
        t.name,
        ls.value AS z_score,
        rsi.value AS RSI,
        ls.as_of_date,
        GROUP_CONCAT(
          CONCAT(
            '<a href=''watchlists.php?watchlist_id=',
            w.watch_list_id,
            '''>',
            w.name,
            '</a>'
          )
          ORDER BY w.name
          SEPARATOR ', '
        ) AS watchlists_html
    FROM latest_signals_v ls
    JOIN watchlist_items wi
      ON wi.symbol = ls.symbol and wi.active = 1
    LEFT JOIN watchlists w
      ON w.watch_list_id = wi.watch_list_id and w.active = 1
    LEFT JOIN tickers t
      ON t.symbol = ls.symbol
    LEFT JOIN latest_signals_v rsi
      ON ls.symbol = rsi.symbol and rsi.indicator = 'rsi_14'
    WHERE ls.indicator = :indicator
    GROUP BY
        ls.symbol,
        t.name,
        ls.value,
        rsi.value,
        ls.as_of_date
    ORDER BY ls.value DESC
    LIMIT 10;
";

$sqlComposite = "
SELECT
    cp.symbol,
    t.name,
    cp.z_score_20,
    cp.composite_price,
    cp.persistence,
    cp.volume_z_20,
    cp.rsi_14,
    GROUP_CONCAT(
        CONCAT(
            '<a href=''watchlists.php?watchlist_id=',
            w.watch_list_id,
            '''>',
            w.name,
            '</a>'
        )
        ORDER BY w.name
        SEPARATOR ', '
    ) AS watchlists_html

FROM (
    SELECT *
    FROM v_composite_price
    ORDER BY ABS(composite_price) DESC
    LIMIT 20
) cp

JOIN watchlist_items wi
    ON wi.symbol = cp.symbol
   AND wi.active = 1

LEFT JOIN watchlists w
    ON w.watch_list_id = wi.watch_list_id
   AND w.active = 1

LEFT JOIN tickers t
    ON t.symbol = cp.symbol

GROUP BY
    cp.symbol,
    t.name,
    cp.z_score_20,
    cp.composite_price,
    cp.persistence,
    cp.volume_z_20,
    cp.rsi_14;
";

$stmtBuy  = $pdo->prepare($sqlBuy);
$stmtSell = $pdo->prepare($sqlSell);

$stmtBuy->execute(['indicator' => $indicator]);
$stmtSell->execute(['indicator' => $indicator]);

$buyRows  = $stmtBuy->fetchAll(PDO::FETCH_ASSOC);
$sellRows = $stmtSell->fetchAll(PDO::FETCH_ASSOC);

$stmtComposite = $pdo->query($sqlComposite);
$compRows = $stmtComposite->fetchAll(PDO::FETCH_ASSOC);

// function to apply color to high values
function zScoreClass(float $z): string
{
    if ($z <= -2.0) return 'signal-strong-buy';
    if ($z >=  2.0) return 'signal-strong-sell';
    return '';
}

// function to apply color to RSI as complementary signal to zScore
function rsiClass(float $z, float $rsi): string
{
    if ($z <= -1.5) {
        if ($rsi <= 35.0) return 'signal-agree-buy';
        if ($rsi >= 60.0) return 'signal-not-agree';
    }

    if ($z >= 1.5) {
        if ($rsi >= 65.0) return 'signal-agree-sell';
        if ($rsi <= 40.0) return 'signal-not-agree';
    }

    return '';
}

// color-coding functions for the composite price table
function compositeClass(float $c): string
{
    if ($c <= -2.0) return 'signal-strong-buy';
    if ($c >=  2.0) return 'signal-strong-sell';
    if ($c < 0)     return 'signal-buy';
    if ($c > 0)     return 'signal-sell';
    return '';
}

function componentExtreme(float $v): string
{
    if (abs($v) >= 2.0) return 'signal-extreme';
    return '';
}





?>

<section>
  <h2>Overview</h2>

</section>

<div class="signals-grid">

  <section class="signals buy table-card">
    <h3>📈 Buy Candidates</h3>

    <!-- here -->

    <table>
      <thead>
        <tr>
          <th>Symbol</th>
          <th>Name</th>
          <th class="num">Z</th>
          <th class="num">RSI</th>
          <th>Watchlists</th>
          <th>Yahoo!</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($buyRows as $row): ?>
          <tr>
            <td>
              <a href="snapshot.php?symbol=<?= urlencode($row['symbol']) ?>">
                <?= htmlspecialchars($row['symbol']) ?>
              </a>
            </td>
            <td><?= htmlspecialchars($row['name']) ?></td>
            <td class="num <?= zScoreClass((float)$row['z_score']) ?>">
              <?= number_format($row['z_score'], 2) ?>
            </td>
            <td class="num <?= rsiClass((float)$row['z_score'], (float)$row['RSI']) ?>">
              <?= number_format($row['RSI'], 1) ?>
            </td>
            <td class="wl"><?= $row['watchlists_html'] ?: '—' ?></td>
            <td class="text-center align-middle"><a href="https://finance.yahoo.com/quote/<?= htmlspecialchars($row['symbol']) ?>" title="View on Yahoo Finance" target="_blank" rel="noopener noreferrer"><i class="bi bi-box-arrow-up-right"></i></a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </section>

  <section class="signals sell table-card">
    <h3>📉 Sell Candidates</h3>

    <table>
      <thead>
        <tr>
          <th>Symbol</th>
          <th>Name</th>
          <th class="num">Z</th>
          <th class="num">RSI</th>
          <th>Watchlists</th>
          <th>Yahoo!</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($sellRows as $row): ?>
          <tr>
            <td>
              <a href="snapshot.php?symbol=<?= urlencode($row['symbol']) ?>">
                <?= htmlspecialchars($row['symbol']) ?>
              </a>
            </td>
            <td><?= htmlspecialchars($row['name']) ?></td>
            <td class="num <?= zScoreClass((float)$row['z_score']) ?>">
              <?= number_format($row['z_score'], 2) ?>
            </td>
            <td class="num <?= rsiClass((float)$row['z_score'], (float)$row['RSI']) ?>">
              <?= number_format($row['RSI'], 1) ?>
            </td><td class="wl">  <?= $row['watchlists_html'] ?: '—' ?></td>
            <td class="text-center align-middle"><a href="https://finance.yahoo.com/quote/<?= htmlspecialchars($row['symbol']) ?>" target="_blank" rel="noopener noreferrer"><i class="bi bi-box-arrow-up-right"></i></a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </section>

</div>

<section class="signals table-card mt-4">
  <h3>📊 Composite Signals (Ranked by Strength)</h3>

  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>Symbol</th>
        <th>Name</th>
        <th class="num">Composite</th>
        <th class="num">Z</th>
        <th class="num">Days +/- (20)</th>
        <th class="num">RSI</th>
        <th class="num">Z-Volume</th>
        <th>Watchlists</th>
        <th>Yahoo!</th>
      </tr>
    </thead>
    <tbody>
      <?php $rank = 1; ?>
      <?php foreach ($compRows as $row): ?>
        <tr>
          <td class="num"><?= $rank++ ?></td>

          <td>
            <a href="snapshot.php?symbol=<?= urlencode($row['symbol']) ?>">
              <?= htmlspecialchars($row['symbol']) ?>
            </a>
          </td>

          <td><?= htmlspecialchars($row['name']) ?></td>

          <td class="num <?= compositeClass((float)$row['composite_price']) ?>">
            <strong><?= number_format($row['composite_price'], 2) ?></strong>
          </td>

          <td class="num <?= componentExtreme((float)$row['z_score_20']) ?>">
            <?= number_format($row['z_score_20'], 2) ?>
          </td>

          <td class="num">
            <?= number_format($row['persistence'], 2) ?>
          </td>

          <td class="num <?= rsiClass((float)$row['z_score_20'], (float)$row['rsi_14']) ?>">
            <?= number_format($row['rsi_14'], 1) ?>
          </td>

          <td class="num">
            <?= number_format($row['volume_z_20'], 2) ?>
          </td>

          <td class="wl"><?= $row['watchlists_html'] ?: '—' ?></td>

          <td class="text-center align-middle"><a href="https://finance.yahoo.com/quote/<?= htmlspecialchars($row['symbol']) ?>" title="View on Yahoo Finance" target="_blank" rel="noopener noreferrer"><i class="bi bi-box-arrow-up-right"></i></a></td>

        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>



<?php include __DIR__ . '/includes/footer.php'; ?>