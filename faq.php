<?php
require_once __DIR__ . '/includes/bootstrap.php';

$domains   = all_research_domains(db());
$pageTitle = 'Help Center & FAQs || UIU ResearchCollab';
$activeNav = '';
require __DIR__ . '/includes/public_header.php';

$faqs = [
    ['q' => 'Who can create an account on UIU ResearchCollab?',
     'a' => 'Any UIU student with a valid @bscse.uiu.ac.bd university email address can sign up. Faculty and Admin accounts are also supported for login, though their dedicated portals are still in development.'],
    ['q' => 'How does the match score in Research Connect work?',
     'a' => 'Each potential collaborator is scored 0–100 based on shared research domains (40%), shared skills (30%), department/program compatibility (15%), academic-level closeness (10%), and mutual availability (5%). The higher the score, the more compatible you are as research partners.'],
    ['q' => 'How do I join or create a research team?',
     'a' => 'From My Teams, you can create a new team, browse and request to join an existing open team, or invite another student to a team you lead from their researcher profile.'],
    ['q' => 'Can I apply to more than one research opportunity?',
     'a' => 'Yes. You can apply to any number of open opportunities, as long as you don\'t already have a pending or accepted application on the same one. You may also re-apply after withdrawing or being rejected.'],
    ['q' => 'Who can see my profile information?',
     'a' => 'You control this from Settings → Profile Visibility, with separate toggles for your contact info, research details, projects, and publications. Other students only see what you\'ve chosen to make visible.'],
    ['q' => 'How do team file uploads stay secure?',
     'a' => 'Files are only downloadable through an authenticated, membership-checked download link — never by direct URL. Uploads are restricted to a safe file-type allowlist and a size limit, and generated filenames prevent path or extension tricks.'],
    ['q' => 'I forgot my password. What do I do?',
     'a' => 'Use the "Forgot Password?" link on the Login page. Since this is a local/demo environment without an email service configured, your reset link is shown directly on screen instead of being emailed.'],
];
?>
    <section class="features-section">
        <div class="container" style="max-width: 850px;">
            <div class="section-heading">
                <h2>Help Center &amp; FAQs</h2>
            </div>
            <p class="text-center" style="color:#555555; font-size:14px; margin-bottom:30px;">
                Answers to common questions about using UIU ResearchCollab. Can't find what
                you're looking for? <a href="contact.php">Contact us</a>.
            </p>

            <div class="accordion" id="faqAccordion">
                <?php foreach ($faqs as $i => $faq): ?>
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button <?= $i === 0 ? '' : 'collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#faq<?= $i ?>" aria-expanded="<?= $i === 0 ? 'true' : 'false' ?>">
                                <?= e($faq['q']) ?>
                            </button>
                        </h2>
                        <div id="faq<?= $i ?>" class="accordion-collapse collapse <?= $i === 0 ? 'show' : '' ?>" data-bs-parent="#faqAccordion">
                            <div class="accordion-body" style="font-size:14px; color:#444444;">
                                <?= e($faq['a']) ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- GUIDELINES -->
    <section class="features-section" id="guidelines">
        <div class="container" style="max-width: 850px;">
            <div class="section-heading">
                <h2>Community Guidelines</h2>
            </div>
            <ul style="color:#555555; font-size:14px; line-height:2;">
                <li>Be respectful and constructive in team messages, community posts, and comments.</li>
                <li>Only post or upload content you have the right to share (papers, datasets, code).</li>
                <li>Keep research opportunity applications and team requests genuine — duplicate or spam applications may be removed.</li>
                <li>Respect other students' profile visibility settings; information they've chosen not to share is private.</li>
                <li>Report any misuse or suspicious activity via <a href="contact.php">Contact Us</a>.</li>
            </ul>
        </div>
    </section>
<?php require __DIR__ . '/includes/public_footer.php'; ?>
</body>
</html>
