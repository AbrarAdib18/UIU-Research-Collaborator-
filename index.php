<?php
require_once __DIR__ . '/includes/bootstrap.php';

// If already logged in as a student, send straight to the portal home.
// (Faculty/Admin have no dedicated portal yet, so they simply see this
// public page rather than bouncing forever between here and the
// student-only dashboard guard — see includes/student_guard.php.)
if (is_logged_in() && ($_SESSION['role'] ?? '') === 'student') {
    redirect('/student/dashboard.php');
}

$domains = all_research_domains(db());

$opportunities = db()->query(
    "SELECT o.*, GROUP_CONCAT(d.name SEPARATOR '||') AS domain_names
     FROM research_opportunities o
     LEFT JOIN opportunity_domains od ON od.opportunity_id = o.id
     LEFT JOIN research_domains d ON d.id = od.domain_id
     WHERE o.status = 'Open' AND o.visibility = 'Public'
     GROUP BY o.id
     ORDER BY o.created_at DESC
     LIMIT 2"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UIU ResearchCollab</title>

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
                        <a href="index.php" class="nav-item active">HOME</a>
                        <a href="#" class="nav-item">ABOUT</a>
                        <a href="#research-opportunities" class="nav-item">RESEARCH</a>
                        <a href="#" class="nav-item">COMMUNITY</a>
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

                <a href="#statistics" class="secondary-item">STATISTICS</a>
                <a href="#" class="secondary-item">FAQs</a>
                <a href="signup.php" class="secondary-item">SIGN UP</a>
                <a href="login.php" class="secondary-item login-item">LOGIN</a>
            </div>
        </div>
    </div>

    <?php render_flashes(); ?>

    <!-- MAIN SECTION -->
    <section class="main-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 col-md-6">
                    <div class="main-content">
                        <h1>Find the Right<span>Research Team.</span></h1>
                        <h5>Connecting Minds. Creating Research.</h5>
                        <p>
                            UIU ResearchCollab helps you discover the
                            right collaborators, share ideas, and build
                            impactful research together.
                        </p>
                        <div class="main-buttons">
                            <a href="signup.php" class="btn btn-primary-custom">Get Started<i class="bi bi-arrow-right"></i></a>
                            <a href="#research-opportunities" class="btn btn-outline-custom">Explore Research</a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 col-md-6">
                    <div class="main-image-container">
                        <img src="IMAGES/img-1.png" alt="Students collaborating on research" class="main-image">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- WHY CHOOSE US -->
    <section class="features-section">
        <div class="container">
            <div class="section-heading">
                <h2>Why Choose UIU ResearchCollab?</h2>
            </div>
            <div class="row feature-row">
                <div class="col-lg-2 col-md-4 col-6">
                    <div class="feature-card">
                        <div class="feature-icon blue"><i class="bi bi-people-fill"></i></div>
                        <h4>Research Connect</h4>
                        <p>Find the most compatible research collaborators based on interests and skills.</p>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-6">
                    <div class="feature-card">
                        <div class="feature-icon orange"><i class="bi bi-megaphone-fill"></i></div>
                        <h4>Research Opportunities</h4>
                        <p>Discover and apply for research projects and team openings.</p>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-6">
                    <div class="feature-card">
                        <div class="feature-icon green"><i class="bi bi-globe2"></i></div>
                        <h4>Communities</h4>
                        <p>Join domain-specific communities and engage in meaningful discussions.</p>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-6">
                    <div class="feature-card">
                        <div class="feature-icon purple"><i class="bi bi-book-fill"></i></div>
                        <h4>Open Repository</h4>
                        <p>Access and share research papers, datasets, and resources for free.</p>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-6">
                    <div class="feature-card">
                        <div class="feature-icon teal"><i class="bi bi-person-workspace"></i></div>
                        <h4>Team Workspace</h4>
                        <p>Collaborate with your team using tasks, files, and schedules.</p>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-6">
                    <div class="feature-card">
                        <div class="feature-icon pink"><i class="bi bi-person-vcard-fill"></i></div>
                        <h4>Research Profile</h4>
                        <p>Showcase your skills, publications, and experience to the research community.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- STATS -->
    <section class="statistics-section" id="statistics">
        <div class="container">
            <div class="row statistics-row">
                <div class="col-6 col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="bi bi-people-fill"></i></div>
                        <div class="stat-number">250+</div>
                        <div class="stat-label">Researchers</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="bi bi-diagram-3-fill"></i></div>
                        <div class="stat-number">80+</div>
                        <div class="stat-label">Research Groups</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="bi bi-file-earmark-text-fill"></i></div>
                        <div class="stat-number">120+</div>
                        <div class="stat-label">Published Papers</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="bi bi-diagram-3"></i></div>
                        <div class="stat-number">15+</div>
                        <div class="stat-label">Research Domains</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- RESEARCH DOMAINS -->
    <section class="domains-section">
        <div class="container">
            <div class="section-heading">
                <h2>Explore Research Domains</h2>
            </div>
            <div class="domains-carousel">
                <button class="domain-nav-btn domain-prev" id="domainPrev" aria-label="Previous research domains">
                    <i class="bi bi-chevron-left"></i>
                </button>
                <div class="domains-viewport" id="domainsViewport">
                    <div class="domains-track" id="domainsTrack">
                        <?php foreach ($domains as $domain): ?>
                            <div class="domain-item">
                                <div class="domain-icon"><i class="<?= e($domain['icon'] ?: 'bi bi-mortarboard') ?>"></i></div>
                                <span><?= e($domain['name']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <button class="domain-nav-btn domain-next" id="domainNext" aria-label="Next research domains">
                    <i class="bi bi-chevron-right"></i>
                </button>
            </div>
        </div>
    </section>

    <!-- RESEARCH OPPORTUNITIES -->
    <section class="opportunities-section" id="research-opportunities">
        <div class="container">
            <div class="section-heading">
                <h2>Latest Research Opportunities</h2>
            </div>
            <?php if ($opportunities): ?>
                <div class="row justify-content-center">
                    <?php foreach ($opportunities as $opp): ?>
                        <?php $tags = $opp['domain_names'] ? explode('||', $opp['domain_names']) : []; ?>
                        <div class="col-lg-5 col-md-6">
                            <div class="opportunity-card">
                                <span class="opportunity-badge fydp"><?= e($opp['project_type'] ?: 'Research') ?></span>
                                <h3><?= e($opp['title']) ?></h3>
                                <div class="opportunity-info">
                                    <span><i class="bi bi-people-fill"></i> Need <?= (int)$opp['team_size_min'] ?> - <?= (int)$opp['team_size_max'] ?> Members</span>
                                </div>
                                <div class="opportunity-tags">
                                    <?php foreach (array_slice($tags, 0, 3) as $tag): ?>
                                        <span><?= e($tag) ?></span>
                                    <?php endforeach; ?>
                                </div>
                                <div class="opportunity-footer">
                                    <span>Deadline: <?= format_date($opp['deadline']) ?></span>
                                    <a href="login.php">View Details<i class="bi bi-arrow-right"></i></a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-center text-muted">New research opportunities are posted regularly — sign up to be notified.</p>
            <?php endif; ?>

            <div class="text-center view-all-wrapper">
                <a href="signup.php" class="view-all-btn">View All Opportunities</a>
            </div>
        </div>
    </section>

    <!-- FOOTER -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 col-md-6">
                    <div class="footer-brand">
                        <img src="IMAGES/logo.jpg" alt="UIU ResearchCollab Logo">
                        <div>
                            <h3>UIU ResearchCollab</h3>
                            <p>Connecting Minds.<br>Creating Research.</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-6">
                    <div class="footer-column">
                        <h4>Quick Links</h4>
                        <a href="index.php">Home</a>
                        <a href="#">About Us</a>
                        <a href="#">Research Domains</a>
                        <a href="#">Communities</a>
                        <a href="#">FAQs</a>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="footer-column">
                        <h4>Support</h4>
                        <a href="#">Help Center</a>
                        <a href="#">How It Works</a>
                        <a href="#">Guidelines</a>
                        <a href="#">Contact Us</a>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="footer-column">
                        <h4>Legal</h4>
                        <a href="#">Terms of Service</a>
                        <a href="#">Privacy Policy</a>
                        <a href="#">Cookie Policy</a>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?= date('Y') ?> UIU ResearchCollab. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- RESEARCH DOMAIN CAROUSEL -->
    <script>
    const domainsTrack = document.getElementById("domainsTrack");
    const domainsViewport = document.getElementById("domainsViewport");
    const domainPrev = document.getElementById("domainPrev");
    const domainNext = document.getElementById("domainNext");
    let domainPosition = 0;
    const domainsToMove = 1;

    function updateDomainCarousel() {
        const domainItems = domainsTrack.querySelectorAll(".domain-item");
        if (domainItems.length === 0) return;
        const firstItem = domainItems[0];
        const itemWidth = firstItem.offsetWidth;
        const trackStyle = window.getComputedStyle(domainsTrack);
        const gap = parseFloat(trackStyle.gap) || 0;
        const moveDistance = (itemWidth + gap) * domainPosition;
        domainsTrack.style.transform = `translateX(-${moveDistance}px)`;
        updateDomainButtons();
    }

    function updateDomainButtons() {
        const domainItems = domainsTrack.querySelectorAll(".domain-item");
        const viewportWidth = domainsViewport.offsetWidth;
        const firstItem = domainItems[0];
        const itemWidth = firstItem.offsetWidth;
        const trackStyle = window.getComputedStyle(domainsTrack);
        const gap = parseFloat(trackStyle.gap) || 0;
        const totalTrackWidth = (itemWidth * domainItems.length) + (gap * (domainItems.length - 1));
        const maxPosition = Math.ceil((totalTrackWidth - viewportWidth) / (itemWidth + gap));
        domainPrev.disabled = domainPosition <= 0;
        domainNext.disabled = domainPosition >= maxPosition;
    }

    domainNext.addEventListener("click", function () {
        domainPosition += domainsToMove;
        updateDomainCarousel();
    });

    domainPrev.addEventListener("click", function () {
        domainPosition -= domainsToMove;
        if (domainPosition < 0) domainPosition = 0;
        updateDomainCarousel();
    });

    window.addEventListener("resize", updateDomainCarousel);
    updateDomainCarousel();
    </script>
</body>
</html>
