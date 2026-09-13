<?php
require_once __DIR__ . '/includes/bootstrap.php';

$domains   = all_research_domains(db());
$pageTitle = 'Privacy Policy || UIU ResearchCollab';
$activeNav = '';
require __DIR__ . '/includes/public_header.php';
?>
    <section class="features-section">
        <div class="container" style="max-width: 850px;">
            <div class="section-heading">
                <h2>Privacy Policy</h2>
            </div>
            <div style="color:#444444; font-size:14px; line-height:1.9;">
                <p><em>Last updated: <?= date('F Y') ?>. UIU ResearchCollab is an academic
                student project. This policy explains, in plain language, what information
                the platform stores and how it's used — written for an educational/demo
                context, not a commercial one.</em></p>

                <h5 class="mt-4">1. What We Store</h5>
                <p>When you use the platform, we store the information you provide directly:
                your account details (name, university email, password — stored as a secure
                hash, never in plain text), profile information (bio, domains, skills,
                education, projects, publications, etc.), uploaded files (avatars, CVs, team
                files, repository resources), and your activity within the platform (team
                memberships, applications, messages, notifications).</p>

                <h5 class="mt-4">2. How It's Used</h5>
                <p>Your information is used only to operate the platform's features: matching
                you with compatible collaborators, displaying your profile to other students
                according to your own visibility settings, running your team workspace, and
                sending you in-app notifications about activity relevant to you.</p>

                <h5 class="mt-4">3. Who Can See Your Information</h5>
                <p>You control most of this yourself via Settings → Profile Visibility.
                Team members see shared team content (tasks, files, messages). Community
                members see posts in communities they've joined. Private communities and
                team workspaces are restricted to their members only, enforced on every page,
                not just hidden from the menu.</p>

                <h5 class="mt-4">4. Contact Form Messages</h5>
                <p>Messages submitted through <a href="contact.php">Contact Us</a> are stored
                so they can be reviewed and followed up on. No outbound email is sent
                automatically — this demo environment doesn't have an email service configured.</p>

                <h5 class="mt-4">5. Data Retention</h5>
                <p>Information is retained for as long as your account exists. As an academic
                project, data may be reset between development/demo cycles.</p>

                <h5 class="mt-4" id="cookies">6. Cookies</h5>
                <p>The platform uses a single essential session cookie (PHP's native session
                mechanism) to keep you logged in as you navigate between pages. This cookie is
                strictly necessary for the site to function — it is not used for advertising,
                tracking across other sites, or analytics.</p>

                <h5 class="mt-4">7. Contact</h5>
                <p>Questions about this policy can be sent through the
                <a href="contact.php">Contact Us</a> page.</p>
            </div>
        </div>
    </section>
<?php require __DIR__ . '/includes/public_footer.php'; ?>
</body>
</html>
