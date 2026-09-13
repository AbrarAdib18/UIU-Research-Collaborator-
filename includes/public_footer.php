<?php
/**
 * Shared footer for public marketing pages. See includes/public_header.php.
 * Include after the page's main content, then close </body></html> yourself
 * if you need extra page-specific scripts first — otherwise this closes both.
 */
?>
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
                        <a href="index.php#about">About Us</a>
                        <a href="index.php#domains">Research Domains</a>
                        <a href="index.php#community">Communities</a>
                        <a href="faq.php">FAQs</a>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="footer-column">
                        <h4>Support</h4>
                        <a href="faq.php">Help Center</a>
                        <a href="index.php#how-it-works">How It Works</a>
                        <a href="faq.php#guidelines">Guidelines</a>
                        <a href="contact.php">Contact Us</a>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="footer-column">
                        <h4>Legal</h4>
                        <a href="terms.php">Terms of Service</a>
                        <a href="privacy.php">Privacy Policy</a>
                        <a href="privacy.php#cookies">Cookie Policy</a>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?= date('Y') ?> UIU ResearchCollab. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
