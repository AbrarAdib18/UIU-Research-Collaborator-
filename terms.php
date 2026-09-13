<?php
require_once __DIR__ . '/includes/bootstrap.php';

$domains   = all_research_domains(db());
$pageTitle = 'Terms of Service || UIU ResearchCollab';
$activeNav = '';
require __DIR__ . '/includes/public_header.php';
?>
    <section class="features-section">
        <div class="container" style="max-width: 850px;">
            <div class="section-heading">
                <h2>Terms of Service</h2>
            </div>
            <div style="color:#444444; font-size:14px; line-height:1.9;">
                <p><em>Last updated: <?= date('F Y') ?>. UIU ResearchCollab is an academic
                student project built as a research-collaboration portal for UIU students.
                This is an educational/demo platform, not a commercial service, and these
                terms are written accordingly.</em></p>

                <h5 class="mt-4">1. Purpose of the Platform</h5>
                <p>UIU ResearchCollab exists to help UIU students discover research
                collaborators, form teams, apply to research opportunities, join
                communities, and share academic resources with one another.</p>

                <h5 class="mt-4">2. Eligibility</h5>
                <p>Accounts are intended for current UIU students, faculty, and staff.
                Student sign-up requires a valid @bscse.uiu.ac.bd university email address.</p>

                <h5 class="mt-4">3. Account Responsibility</h5>
                <p>You are responsible for keeping your password confidential and for all
                activity under your account. Notify us via <a href="contact.php">Contact Us</a>
                if you believe your account has been compromised.</p>

                <h5 class="mt-4">4. Acceptable Use</h5>
                <p>When using the platform, you agree to follow the
                <a href="faq.php#guidelines">Community Guidelines</a> — treat other students
                respectfully, only upload content you have rights to share, and use features
                like applications, invitations, and team requests genuinely rather than to spam
                or disrupt other users.</p>

                <h5 class="mt-4">5. Content You Share</h5>
                <p>You retain ownership of the profile information, posts, messages, files,
                and repository resources you submit. By posting them on the platform, you
                allow other authorized users (teammates, community members, or the public,
                depending on your own visibility settings) to view them as intended by the
                feature you used.</p>

                <h5 class="mt-4">6. No Warranty</h5>
                <p>This platform is provided "as is" as an academic project, without warranty
                of any kind, express or implied, including fitness for a particular purpose
                or uninterrupted availability.</p>

                <h5 class="mt-4">7. Changes</h5>
                <p>These terms may be updated as the platform evolves. Continued use after a
                change constitutes acceptance of the updated terms.</p>

                <h5 class="mt-4">8. Contact</h5>
                <p>Questions about these terms can be sent through the
                <a href="contact.php">Contact Us</a> page.</p>
            </div>
        </div>
    </section>
<?php require __DIR__ . '/includes/public_footer.php'; ?>
</body>
</html>
