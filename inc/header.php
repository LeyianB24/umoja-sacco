<?php
// inc/header.php

/* -------------------------------------------------------------
   1. Secure Session Start
------------------------------------------------------------- */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* -------------------------------------------------------------
   2. Load Configuration
------------------------------------------------------------- */
require_once __DIR__ . '/../config/app_config.php';

/* -------------------------------------------------------------
   3. Helpers
------------------------------------------------------------- */
if (!function_exists('is_active')) {
    function is_active($page_name) {
        return basename($_SERVER['PHP_SELF']) === $page_name ? 'active' : '';
    }
}

// Check if user is logged in (Adjust 'member_id'/'admin_id' based on your auth logic)
$is_logged_in = isset($_SESSION['member_id']) || isset($_SESSION['admin_id']);
$dashboard_link = isset($_SESSION['admin_id']) ? '/admin/dashboard.php' : '/member/dashboard.php';
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <title>
        <?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' — ' : '' ?>
        <?= defined('SITE_NAME') ? htmlspecialchars(SITE_NAME) : 'Sacco Portal' ?>
    </title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="<?= ASSET_BASE ?>/css/style.css">

    <script>
        (() => {
            const saved = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-bs-theme', saved);
        })();
    </script>

    <style>
        /* ---------------------------------------------------------
           THEME VARIABLES & CORE STYLES
        --------------------------------------------------------- */
        :root {
            /* Define colors if not in style.css */
            --sacco-green: #064e3b; /* Deep Forest Green */
            --sacco-gold: #fbbf24;  /* Bright Gold */
            --text-on-green: #ffffff;
        }

        body {
            font-family: 'Poppins', sans-serif;
        }

        /* NAVBAR CONTAINER */
        .site-header .navbar {
            background: var(--sacco-green);
            border-bottom: 3px solid var(--sacco-gold);
            box-shadow: 0 4px 14px rgba(0,0,0,0.18);
            padding: 0.8rem 0;
            transition: background 0.3s ease;
        }

        /* BRANDING */
        .brand-logo {
            width: 45px;
            height: 45px;
            object-fit: contain;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            padding: 2px;
            border: 1px solid var(--sacco-gold);
        }
        .navbar-brand-text {
            color: #fff;
            font-weight: 700;
            font-size: 1.3rem;
            letter-spacing: -0.5px;
        }
        .navbar-brand-tagline {
            color: var(--sacco-gold);
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 500;
        }

        /* LINKS */
        .site-header .nav-link {
            color: rgba(255, 255, 255, 0.85) !important;
            font-weight: 500;
            font-size: 0.95rem;
            margin: 0 5px;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .site-header .nav-link:hover,
        .site-header .nav-link.active {
            color: #fff !important;
            transform: translateY(-1px);
        }

        /* Active Indicator (Underline) */
        .site-header .nav-link::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: 5px;
            left: 50%;
            background-color: var(--sacco-gold);
            transition: all 0.3s ease;
            transform: translateX(-50%);
        }
        .site-header .nav-link:hover::after,
        .site-header .nav-link.active::after {
            width: 80%;
        }

        /* AUTH BUTTONS */
        .btn-auth-login {
            color: #fff;
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 50px;
            padding: 6px 20px;
            font-weight: 500;
            transition: all 0.3s;
        }
        .btn-auth-login:hover {
            background: rgba(255,255,255,0.1);
            border-color: #fff;
            color: #fff;
        }

        .btn-auth-register {
            background-color: var(--sacco-gold);
            color: var(--sacco-green);
            border: none;
            border-radius: 50px;
            padding: 6px 24px;
            font-weight: 600;
            box-shadow: 0 4px 10px rgba(0,0,0,0.15);
            transition: all 0.3s;
        }
        .btn-auth-register:hover {
            background-color: #fff;
            color: var(--sacco-green);
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.2);
        }

        /* MOBILE MENU */
        .navbar-toggler {
            border: none;
            padding: 0;
        }
        .navbar-toggler:focus {
            box-shadow: none;
        }
        .navbar-toggler-icon {
            filter: invert(1);
        }

        @media (max-width: 991px) {
            .navbar-collapse {
                background: rgba(0,0,0,0.1);
                border-radius: 12px;
                padding: 1rem;
                margin-top: 1rem;
            }
            .btn-auth-register, .btn-auth-login {
                width: 100%;
                text-align: center;
                margin-bottom: 0.5rem;
            }
        }
    </style>
</head>

<body>

<header class="site-header sticky-top">
    <nav class="navbar navbar-expand-lg">
        <div class="container">

            <a class="navbar-brand d-flex align-items-center gap-3" href="<?= BASE_URL ?>/public/index.php">
                <div class="brand-logo d-flex align-items-center justify-content-center bg-white text-success fw-bold fs-4">
                    <img src="<?= ASSET_BASE ?>/images/people_logo.png" alt="Logo" style="width:100%; height:100%; object-fit:cover; border-radius:50%; display:block;" onerror="this.style.display='none';this.parentElement.innerText='S'">
                </div>

                <div class="d-flex flex-column justify-content-center lh-1">
                    <span class="navbar-brand-text">
                        <?= defined('SITE_NAME') ? htmlspecialchars(SITE_NAME) : 'UMOJA' ?>
                    </span>
                    <span class="navbar-brand-tagline d-none d-sm-block">
                        <?= defined('TAGLINE') ? htmlspecialchars(TAGLINE) : 'DRIVERS SACCO' ?>
                    </span>
                </div>
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav ms-auto align-items-center gap-lg-1 py-3 py-lg-0">

                    <li class="nav-item">
                        <a class="nav-link <?= is_active('index.php') ?>" href="<?= BASE_URL ?>/public/index.php">
                            Home
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>/public/index.php#about">About Us</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>/public/index.php#services">Services</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>/public/index.php#contact">Contact</a>
                    </li>

                    <li class="nav-item d-none d-lg-block mx-2 text-white-50">|</li>

                    <?php if ($is_logged_in): ?>
                        <li class="nav-item dropdown">
                            <a class="btn btn-auth-register dropdown-toggle d-flex align-items-center gap-2" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-person-circle"></i> Account
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2 rounded-3">
                                <li><a class="dropdown-item py-2" href="<?= BASE_URL . $dashboard_link ?>"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a></li>
                                <li><a class="dropdown-item py-2" href="<?= BASE_URL ?>/member/profile.php"><i class="bi bi-person-gear me-2"></i> Profile</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item py-2 text-danger" href="<?= BASE_URL ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item ms-lg-2">
                            <a href="<?= BASE_URL ?>/public/login.php" class="btn btn-auth-login nav-link border-0 text-white">
                                Log In
                            </a>
                        </li>
                        <li class="nav-item ms-lg-2">
                            <a href="<?= BASE_URL ?>/public/register.php" class="btn btn-auth-register">
                                Join Us
                            </a>
                        </li>
                    <?php endif; ?>

                    <li class="nav-item ms-lg-2">
                        <button class="btn nav-link text-warning p-0 border-0" id="themeToggle" title="Switch Theme">
                            <i class="bi bi-moon-stars-fill"></i>
                        </button>
                    </li>

                </ul>
            </div>
        </div>
    </nav>
</header>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const toggle = document.getElementById('themeToggle');
        const icon = toggle.querySelector('i');
        const html = document.documentElement;

        toggle.addEventListener('click', () => {
            const current = html.getAttribute('data-bs-theme');
            const next = current === 'light' ? 'dark' : 'light';
            html.setAttribute('data-bs-theme', next);
            localStorage.setItem('theme', next);
            icon.className = next === 'light' ? 'bi bi-moon-stars-fill' : 'bi bi-sun-fill';
        });
    });
</script>