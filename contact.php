<?php
require_once __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('/contact.php');

    $name    = trim((string)($_POST['name'] ?? ''));
    $email   = trim((string)($_POST['email'] ?? ''));
    $subject = trim((string)($_POST['subject'] ?? ''));
    $message = trim((string)($_POST['message'] ?? ''));

    $old = ['name' => $name, 'email' => $email, 'subject' => $subject, 'message' => $message];
    $errors = [];

    if ($name === '' || mb_strlen($name) > 150) {
        $errors[] = 'Please enter your name.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
        $errors[] = 'Please enter a valid email address.';
    }
    if ($subject === '' || mb_strlen($subject) > 200) {
        $errors[] = 'Please enter a subject (max 200 characters).';
    }
    if ($message === '' || mb_strlen($message) > 5000) {
        $errors[] = 'Please enter a message (max 5000 characters).';
    }

    if ($errors) {
        flash('error', implode(' ', $errors));
        set_old($old);
        redirect('/contact.php');
    }

    $stmt = db()->prepare('INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)');
    $stmt->execute([$name, $email, $subject, $message]);

    // No outbound email service is configured in this environment, so the
    // message is simply stored for follow-up rather than emailed.
    flash('success', 'Your message has been received. Thank you for reaching out — we will get back to you soon.');
    redirect('/contact.php');
}

$domains   = all_research_domains(db());
$pageTitle = 'Contact Us || UIU ResearchCollab';
$activeNav = '';
require __DIR__ . '/includes/public_header.php';
?>
    <section class="features-section">
        <div class="container" style="max-width: 800px;">
            <div class="section-heading">
                <h2>Contact Us</h2>
            </div>
            <p class="text-center" style="color:#555555; font-size:14px; margin-bottom:30px;">
                Have a question, suggestion, or issue? Send us a message and we'll get back to you.
            </p>

            <form action="contact.php" method="POST" novalidate>
                <?= csrf_field() ?>

                <div class="mb-3">
                    <label for="contact-name" class="form-label">Full Name</label>
                    <input type="text" class="form-control" id="contact-name" name="name" value="<?= e(old('name')) ?>" maxlength="150" required>
                </div>

                <div class="mb-3">
                    <label for="contact-email" class="form-label">Email Address</label>
                    <input type="email" class="form-control" id="contact-email" name="email" value="<?= e(old('email')) ?>" maxlength="150" required>
                </div>

                <div class="mb-3">
                    <label for="contact-subject" class="form-label">Subject</label>
                    <input type="text" class="form-control" id="contact-subject" name="subject" value="<?= e(old('subject')) ?>" maxlength="200" required>
                </div>

                <div class="mb-3">
                    <label for="contact-message" class="form-label">Message</label>
                    <textarea class="form-control" id="contact-message" name="message" rows="6" maxlength="5000" required><?= e(old('message')) ?></textarea>
                </div>

                <div class="text-center">
                    <button type="submit" class="btn btn-primary-custom">Send Message<i class="bi bi-send-fill"></i></button>
                </div>
            </form>
        </div>
    </section>
<?php require __DIR__ . '/includes/public_footer.php'; ?>
</body>
</html>
