<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <title>Stock Screener</title>

  <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- Bootstrap Icons -->
    <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

  <link rel="stylesheet" href="assets/styles.css">
  <link rel="icon" href="favicon.ico" type="image/x-icon">
</head>
<body>

<header class="site-header">
  <div class="container">
    <div class="brand">
      <a href="index.php">
        <div class="logo">
          <i class="bi bi-graph-up-arrow"></i>
        </div>
      </a>
      <div class="title">
        <div class="main-title">Stock Screener</div>
        <div class="subtitle">Personal Portfolio Tracker</div>
      </div>
      <!-- Hamburger button for mobile -->
      <button id="nav-toggle" aria-label="Toggle navigation">
        <i class="bi bi-list"></i>
      </button>
    </div>

     <nav id="main-nav" class="nav">
      <a href="index.php"><i class="bi bi-columns-gap"></i> Overview</a>
      <a href="watchlists.php"><i class="bi bi-list-stars"></i> Watchlists</a>
      <a href="details.php"><i class="bi bi-bar-chart-line"></i> Details</a>
    </nav>
  </div>
</header>

<!-- JS for toggle -->

<script>
  const toggle = document.getElementById('nav-toggle');
  const nav = document.getElementById('main-nav');

  if (toggle && nav) {
    toggle.addEventListener('click', () => {
      nav.classList.toggle('active');
    });
  }
</script>


<main class="content">
  <div class="container">