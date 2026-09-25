<?php
/**
 * Shared header (top bar + both navbars) for public marketing pages
 * (index.php, contact.php, terms.php, privacy.php, faq.php).
 *
 * Expects, optionally, before including this file:
 *   $pageTitle   string  <title> text (defaults to "UIU ResearchCollab")
 *   $domains     array   rows from research_domains, for the dropdown
 *                        (falls back to an empty list if not provided)
 *
 * All section anchors point at index.php#... so links work correctly
 * from every public page, not just the homepage.
 */

$pageTitle = $pageTitle ?? 'UIU ResearchCollab';
$domains   = $domains ?? [];
$activeNav = $activeNav ?? 'home'; // 'home' | 'about' | 'research' | 'community'
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="CSS/style.css">
</head>
<body>
    <!-- TOP BAR -->
    <div class="top-bar">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-6">
                    <div class="portal-title">UIU Research Collaboration Portal</div>
                </div>
                <div class="col-6 text-end">
                    <div class="portal-tagline">Connecting Minds. Creating Research.</div>
                </div>
            </div>
        </div>
    </div>

    <!-- 1ST NAVBAR -->
    <nav class="main-navbar">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-auto">
                    <a href="index.php" class="logo-link">
                        <img src="IMAGES/logo.jpg" alt="UIU ResearchCollab Logo" class="website-logo">
                    </a>
                </div>
                <div class="col">
                    <div class="main-menu">
                        <a href="index.php" class="nav-item<?= $activeNav === 'home' ? ' active' : '' ?>">HOME</a>
                        <a href="index.php#about" class="nav-item<?= $activeNav === 'about' ? ' active' : '' ?>">ABOUT</a>
                        <a href="index.php#research-opportunities" class="nav-item<?= $activeNav === 'research' ? ' active' : '' ?>">RESEARCH</a>
                        <a href="index.php#community" class="nav-item<?= $activeNav === 'community' ? ' active' : '' ?>">COMMUNITY</a>
                    </div>
                </div>
                <div class="col-auto">
                    <form class="search-box" action="login.php" method="get">
                        <input type="text" placeholder="Search..." aria-label="Search">
                        <button type="submit"><i class="bi bi-search"></i></button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    <!-- 2ND NAVBAR -->
    <div class="secondary-navbar">
        <div class="container-fluid">
            <div class="secondary-menu">
                <a href="index.php" class="secondary-item home-icon">
                    <i class="bi bi-house-fill"></i>
                </a>

                <div class="secondary-item dropdown-item-custom dropdown">
                    <button class="research-domain-btn" type="button" id="researchDomainDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        RESEARCH DOMAINS
                        <i class="bi bi-caret-down-fill"></i>
                    </button>
                    <div class="research-domain-menu dropdown-menu" aria-labelledby="researchDomainDropdown">
                        <div class="research-domain-scroll">
                            <?php foreach ($domains as $domain): ?>
                                <a href="signup.php" class="research-domain-item">
                                    <i class="<?= e($domain['icon'] ?: 'bi bi-mortarboard') ?>"></i>
                                    <span><?= e($domain['name']) ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <a href="index.php#statistics" class="secondary-item">STATISTICS</a>
                <a href="faq.php" class="secondary-item">FAQs</a>
                <a href="signup.php" class="secondary-item">SIGN UP</a>
                <a href="login.php" class="secondary-item login-item">LOGIN</a>
            </div>
        </div>
    </div>

    <?php render_flashes(); ?>

    <?php $maintenanceNotice = get_platform_setting(db(), 'maintenance_notice'); ?>
    <?php if ($maintenanceNotice): ?>
        <div class="container-fluid">
            <div class="alert alert-warning mb-0" role="alert" style="border-radius:0;text-align:center;">
                <i class="bi bi-exclamation-triangle-fill"></i> <?= e($maintenanceNotice) ?>
            </div>
        </div>
    <?php endif; ?>
