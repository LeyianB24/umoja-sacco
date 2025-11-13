<?php
// inc/header.php
require_once __DIR__ . '/../config/app_config.php';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title><?= htmlspecialchars(SITE_NAME) ?> — <?= htmlspecialchars(TAGLINE) ?></title>

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

  <!-- Custom CSS -->
  <link rel="stylesheet" href="<?= ASSET_BASE ?>/css/style.css">
</head>
<body class="bg-light">

<!-- Header -->
<header class="site-header">
  <nav class="navbar navbar-expand-lg navbar-dark" style="background: linear-gradient(90deg, #0A6833, #064422);">
    <div class="container">
      <a class="navbar-brand d-flex align-items-center" href="<?= BASE_URL ?>/index.php">
        <img src="<?= ASSET_BASE ?>/images/people_logo.png"
             alt="<?= SITE_NAME ?> logo"
             class="rounded-circle"
             style="width:56px;height:56px;object-fit:cover;border:3px solid #F2A713;">
        <div class="ms-3 d-none d-md-block">
          <div style="font-weight:700;color:#fff;font-size:1.05rem;"><?= htmlspecialchars(SITE_NAME) ?></div>
          <div style="font-size:.85rem;color:#f7e7c0;"><?= htmlspecialchars(TAGLINE) ?></div>
        </div>
      </a>

      <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse" id="mainNav">
        <ul class="navbar-nav ms-auto align-items-lg-center">
          <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/index.php"><i class="bi bi-house-fill me-1"></i> Home</a></li>
          <li class="nav-item"><a class="nav-link" href="#about"><i class="bi bi-info-circle me-1"></i> About Us</a></li>
          <li class="nav-item"><a class="nav-link" href="#projects"><i class="bi bi-building me-1"></i> Projects</a></li>
          <li class="nav-item"><a class="nav-link" href="#loans"><i class="bi bi-cash-stack me-1"></i> Loans</a></li>
          <li class="nav-item"><a class="nav-link" href="#savings"><i class="bi bi-piggy-bank me-1"></i> Savings</a></li>
          <li class="nav-item"><a class="nav-link" href="#investments"><i class="bi bi-graph-up me-1"></i> Investments</a></li>
          <li class="nav-item"><a class="nav-link" href="#contact"><i class="bi bi-envelope me-1"></i> Contact</a></li>

          <!-- Auth Buttons -->
          
        </ul>
      </div>
    </div>
  </nav>
</header>
