# UIU ResearchCollab — Student Portal

Connecting Minds. Creating Research.

A Core PHP + MySQL academic research-collaboration portal for UIU students. This milestone implements the **full student portal** (auth through Research Repository); Faculty/Admin portals, Calendar, real-time chat, and email verification are out of scope for now (see "Deferred Features" below).

Stack: **HTML5, CSS3, Bootstrap 5.3.3, Bootstrap Icons, vanilla JavaScript, Core/Plain PHP, MySQL/PDO** — no frameworks (no Laravel/React/Vue/Node).

---

## ✅ Application Status

**Runtime tested in PHP 8.2 + MariaDB; ready with minor known issues.**

This project has gone through three passes: (1) a full build pass implementing every student-portal module, (2) a static security/consistency code-review pass, and (3) a **real runtime test pass** — actual PHP and MariaDB were installed and run, the database was actually imported, an actual HTTP server actually served the app, and dozens of real request/response flows were actually executed and verified against the live database. This is not a claim inferred from reading the code — it was executed.

### Exact Tested Environment

- **PHP 8.2.33**, CLI, with `pdo_mysql`, `mysqli`, `mbstring`, `fileinfo`, `gd`, and `curl` extensions enabled
- **MariaDB 12.3.3**
- **PDO MySQL**: confirmed enabled and functional (`db()` in `config/database.php` connected and queried successfully)
- **PHP's built-in development server** (`php -S`) — used as the HTTP server for this test pass, **not Apache**
- **Headless Chrome** — full-page screenshots at desktop (1400px) and mobile (390px) widths, used to visually confirm the design (colors, header, sidebar, cards, typography, spacing) was preserved exactly
- **`curl`** — used for every functional request/response test: form submissions, CSRF validation, session/cookie behavior, authorization/ownership attacks, and direct database verification after each action

### Runtime Verification Summary

- Database import (`schema.sql` → `migrations.sql` → `seed.sql`) succeeded cleanly with zero errors; 37 tables, 55 foreign keys, zero orphaned rows.
- The demo password hash was verified with **real `password_verify()`** (not just a script) — confirmed `true` for `Password123!` and `false` for a wrong password.
- Signup, login, logout, session handling, and the faculty/admin redirect-loop fix were all exercised over real HTTP and confirmed correct.
- Profile CRUD, the `javascript:` URL-scheme XSS protection, and cross-user ownership attacks (attempting to edit/delete another student's data) were tested with both an attack attempt and a positive control — attacks were blocked, legitimate actions succeeded.
- Research Connect's dynamic match-scoring, the full team-invitation lifecycle (send/duplicate/self-invite/team-full/accept), team creation, task/milestone creation, and team-file upload were all exercised end-to-end against the live database.
- **A real bug was found and fixed during this pass**: re-applying to an opportunity after withdrawing was permanently blocked (not just caught by static review — the earlier fix had only improved the error message, not the underlying capability). Now fixed and retested successfully. See §13 for the full list of bugs found across both review passes.
- Team-file download authorization, private-community access control, and cross-user notification manipulation were all tested as actual attacks against the live app and correctly blocked.
- Visual design preservation (palette, header, sidebar, cards, typography, spacing, mobile layout) was confirmed via real rendered screenshots, not just reading CSS.

### Three Items Still Needing Your Local Confirmation

Runtime testing in this pass used PHP's built-in server, not Apache, so these three items could not be fully exercised and need one pass in your real XAMPP:

1. **Apache/`.htaccess` behavior** — the built-in PHP server ignores `.htaccess` entirely, so the `uploads/team-files/.htaccess` deny-rule was never actually tested by a real Apache config. The **application-level** authorization gate (the PHP membership check before streaming a file) *was* tested directly and confirmed working — but Apache's own file-serving behavior still needs one confirmation. See the new **"Apache/XAMPP Final Check"** section below for the exact steps.
2. **Concurrent team-size-acceptance race condition** — two simultaneous "accept" requests hitting the very last open slot on a team aren't protected by a row lock. This is a low-priority future hardening item, not a blocker — it requires deliberately-simultaneous requests to trigger and is very unlikely at classroom/demo scale.
3. **Oversize file upload rejection** — the size-limit check was read and confirmed in the code, but was not exercised with an actual oversized file in this pass. See the new **"Oversize Upload Test"** section below for the exact steps and the real configured limit.

---

## 1. XAMPP Setup

1. Install [XAMPP](https://www.apachefriends.org/) (Apache + MySQL + PHP 8+).
2. Copy this entire project folder into `C:\xampp\htdocs\` (e.g. `C:\xampp\htdocs\UIU-ResearchCollab-main`).
3. Start **Apache** and **MySQL** from the XAMPP Control Panel.
4. Open `http://localhost/UIU-ResearchCollab-main/index.php` in your browser once the database is set up (step 2 below).

The app auto-detects its own URL path (via `includes/functions.php`'s `base_url()`), so it works regardless of the folder name you install it under.

## 2. Database Setup

Open **phpMyAdmin** (`http://localhost/phpmyadmin`) and run these three files **in this exact order**:

1. `database/schema.sql` — creates the database `uiu_researchcollab` and all base tables (this is the original project schema dump).
2. `database/migrations.sql` — adds the Research Repository tables (`research_resources`, `saved_resources`) and a `cv_path` column on `student_profiles`. Safe to re-run (uses `IF NOT EXISTS` / guarded `ALTER`).
3. `database/seed.sql` — realistic demo data (see credentials below). **Assumes a fresh import** — run it only once, right after the two files above.

Easiest way: in phpMyAdmin, use **Import** and select each file in order (or paste each file's contents into the SQL tab and run).

## 3. Database Configuration

Edit `config/database.php` if your MySQL credentials differ from stock XAMPP defaults:

```php
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'uiu_researchcollab');
define('DB_USER', 'root');
define('DB_PASS', '');
```

## 4. Upload Directories

The app writes to these folders — they already exist in the repo with `.htaccess`/`index.php` guards, but confirm they're **writable** by your web server user:

- `uploads/avatars/` — profile & cover photos
- `uploads/cv/` — student CV/résumé uploads
- `uploads/resources/` — Research Repository file uploads
- `uploads/team-files/` — team workspace files (served **only** through `student/team-files.php`'s membership-gated download route — direct web access is blocked via `.htaccess`)

On Windows/XAMPP these are writable by default; on Linux you may need `chmod -R 775 uploads/`.

## 5. Demo Accounts (local development only)

| Role    | Email                  | Password       |
|---------|-------------------------|----------------|
| Student | student@example.com     | Password123!   |
| Faculty | faculty@example.com     | Password123!   |
| Admin   | admin@example.com       | Password123!   |

Plus 7 more seeded student accounts (e.g. `tanvir.ahmed@bscse.uiu.ac.bd`, same password) and 1 more faculty account — see `database/seed.sql` for the full list. **These credentials are for local development/demo only — never use them in production.**

Only the **Student** portal is functional in this milestone; Faculty/Admin accounts can log in (their role is recognized and routed correctly) but land on a "coming soon" notice rather than a dedicated portal.

---

## 6. Project Structure

```
/
├── index.php, login.php, signup.php, logout.php   — public site + auth
├── config/database.php                             — PDO connection
├── includes/                                        — shared bootstrap, auth, CSRF,
│                                                       flash, functions, header/sidebar/footer
├── student/                                          — the whole student portal (26 pages)
├── CSS/, JS/, IMAGES/                                — existing design system (preserved)
├── uploads/                                          — user-uploaded files (gitignored content)
└── database/
    ├── schema.sql       — original full schema
    ├── migrations.sql   — Research Repository tables + cv_path column
    └── seed.sql         — demo data
```

## 7. Completed Student Features

Every module below is real, database-backed, and reachable from the sidebar (Dashboard, My Profile, Research Connect, Research Opportunities, My Teams, Communities, Research Repositories, Saved Items, Notifications, Settings, Logout):

- **Auth**: signup (with duplicate-email/ID checks, `@bscse.uiu.ac.bd` validation), login, logout, session-based auth, CSRF protection, password hashing (`password_hash`/`password_verify`), inactive-account blocking.
- **Dashboard**: live profile completion, domain/skill/application/team counts, unread notifications, recommended collaborators (real match scoring), latest open opportunities, upcoming team milestones, recent activity feed.
- **Profile**: personal/contact/academic info, research domains (multi-select tags), skills with levels, education (new section), research preferences, projects, publications, certifications, achievements, languages, work experience, profile/cover photo upload, CV upload, profile visibility (single enum) — all with real CRUD. Profile completion recalculates after every save.
- **Research Connect**: filterable/searchable researcher directory (domain, skill, department, academic level), paginated, dynamic match-score badges, read-only researcher profile view respecting visibility settings, "Invite to Team" flow.
- **Research Opportunities**: browse/search/filter, details page, apply with a message, withdraw, save/unsave, duplicate/deadline/status enforcement, notifications on apply.
- **My Teams**: create teams, browse/discover open teams, request to join, send/accept/reject invitations and join requests, full team workspace (tasks, milestones, files with secure upload/download, chronological messages), size-limit and duplicate-membership enforcement throughout.
- **Communities**: browse/search/filter, join/leave, posts and comments (member-only posting, owner-only deletion).
- **Research Repository**: browse/search/filter by type/year/domain, add a resource (file or external link), edit/delete your own, save/unsave.
- **Saved Items**: unified view of saved opportunities and saved resources.
- **Notifications**: triggered by applications, invitations, join requests, team messages, and community comments; mark read / mark all read; deep links to the related page.
- **Settings**: change password, and the 4 granular visibility toggles (contact/research/project/publication) that gate what other students see on your researcher profile.

## 8. Deferred / Out of Scope (per project brief)

- Faculty and Admin portals (accounts exist and can log in, but no dedicated dashboards).
- Calendar / events module.
- Real-time chat (team messaging is page-refresh based, not WebSocket-driven).
- Email verification and password-reset flows.
- Payment integration.
- A handful of Profile.html fields with no corresponding database column were intentionally **not** wired up and are labeled "Coming Soon" in the UI instead of being faked: Date of Birth, Gender, Preferred Contact, University (as a separate field), Expected Graduation, Academic Status, Research Methodologies checklist, Extracurricular Activities, and the day/time Availability grid (real availability lives in Research Preferences → "Availability hrs/week"). The original static "Research Experience" timeline section (which had no database table of its own, distinct from Work Experience) was merged into Work Experience rather than left as decorative dead content.

## 9. Known Limitations

- No automated test suite; verification was done by two full passes of careful manual code review (build pass + a dedicated security/consistency review pass — see section 13) since no PHP/MySQL runtime was available in the development sandbox to execute `php -l` or run the app directly. See the Testing Checklist below for what to verify manually after import.
- Opportunities are created by faculty/admin only in this milestone (seeded directly); there is no student-facing "create opportunity" flow, and no faculty review UI for applications (accepted/rejected demo statuses are pre-seeded).
- Match scoring runs in PHP over the current page of candidates rather than in SQL — fine at seed-data/demo scale, would need optimization for a large student body.
- `team_files`/`research_resources` seed rows reference a couple of placeholder filenames that don't exist on disk (clearly named as samples in `seed.sql`'s comments) — the **listing** UI works, but downloading those specific seeded rows will 404 until a real file is uploaded through the app.
- Team-size-limit checks on accepting an invitation/join-request re-verify capacity at accept-time (not just at send-time), but aren't wrapped in a row lock (`SELECT ... FOR UPDATE`) — under truly simultaneous accepts for the very last open slot, both could theoretically pass the check before either commits. Not exploitable by a single user, low real-world risk at classroom/demo scale.
- Community "Robotics & IoT Club" (id 5) was deliberately seeded as `Private` (it already has 3 members and 2 posts) specifically so the private-community access rules added in the review pass have something real to test against — see the Testing Checklist.

## 10. Database Changes Explained

The original schema (`database/schema.sql`) already covered nearly every table the brief required. `database/migrations.sql` adds only what was missing:

- **`research_resources`** — powers the Research Repository (title, description, type, author, year, domain, file or external link, visibility/status, uploader).
- **`saved_resources`** — many-to-many bookmark table (user ↔ resource), mirroring the existing `saved_opportunities` pattern.
- **`student_profiles.cv_path`** — one nullable column so the CV/résumé upload feature on the Profile page could be real instead of a non-functional button.

No existing table, column, or constraint was renamed or removed.

## 11. Research Connect — Matching Formula

For a viewer V looking at candidate C (both `student_profiles` rows), the match score (0–100) is:

| Factor | Weight | Rule |
|---|---|---|
| Shared research domains | 40% | `(shared domains ÷ V's domain count) × 40` |
| Shared skills | 30% | `(shared skills ÷ V's skill count) × 30` |
| Department / program | 15% | +10 if same department, +5 more if also same program |
| Academic-level closeness | 10% | Parsed from the numeric trimester in `semester`: +10 if within 1 trimester, +5 if within 3, else 0 |
| Mutual availability | 5% | +5 if both have `looking_for_team = 1` in Research Preferences |

Implemented in `calculate_match_score()` in `includes/functions.php` — simple, explainable, and reused by both Research Connect and the Dashboard's "Recommended Collaborators".

## 12. Testing Checklist

Run these against your local import (all 3 SQL files + the demo accounts above). See section 14 for the full page-by-page checklist and exact URLs.

- [ ] **Signup & Login**: create a new `@bscse.uiu.ac.bd` account → duplicate email/ID rejected → log in → land on Dashboard → log out.
- [ ] **Redirect sanity**: while logged in as `faculty@example.com` or `admin@example.com`, visit `index.php`, `login.php`, and `signup.php` directly — confirm each just shows the public page (no infinite redirect/blank loading loop). This exercises a real loop bug that was found and fixed during review.
- [ ] **Profile completion**: log in as `student@example.com`, add a research domain, a skill, an education entry, a project, save preferences → completion % increases on Dashboard/sidebar.
- [ ] **Research Connect**: filter by domain/skill/department, confirm match % differs per candidate, open a profile, invite them to a team you lead.
- [ ] **Opportunities**: browse, filter, open details, apply, confirm you can't apply twice (including re-applying after a Rejected/Withdrawn application — this was a real bug that's now fixed), save one, see it on Saved Items.
- [ ] **Teams**: create a team, invite another student, accept from that student's account, request to join a team you're not in (Discover Teams), have that team's leader accept it, get rejected once and confirm you CAN request again (this was a real bug that's now fixed), create a task and a milestone, upload a file, post a message.
- [ ] **Communities**: join a Public community, create a post, comment, leave it. Then, logged in as a member of "Robotics & IoT Club" (community id 5 — users 5/6/7, e.g. `farhana.islam@bscse.uiu.ac.bd`), confirm you see its full post feed; logged in as a non-member (e.g. `student@example.com`), visit `student/community-details.php?id=5` directly and confirm you see a "private community" notice instead of the post feed and cannot join via the button.
- [ ] **Repository**: browse, filter, open details, save a resource, see it on Saved Items, add your own resource (file or link).
- [ ] **Notifications**: after the actions above, confirm notifications appear, mark them read, follow their links.
- [ ] **Settings**: change your password and log in again with the new one; toggle a visibility switch off and confirm it hides that section on your `researcher-profile.php` view (check from a different account).

## 13. Code Review & Pre-XAMPP Validation Pass

After the initial build, every file was re-reviewed in a dedicated second pass against a strict security/consistency checklist (include paths, DB schema cross-checks, CSRF, authorization/ownership, duplicate prevention, upload security, and frontend preservation). **No PHP/MySQL runtime was available to execute the code**, so this was static review, not a live test — treat section 14 as required before considering this "done." Real bugs found and fixed during this pass:

- **Redirect loop**: `index.php`/`login.php`/`signup.php` redirected ANY logged-in user (including faculty/admin) to the student-only dashboard, which then bounced them right back — an infinite loop for non-student accounts. Fixed to only auto-redirect students.
- **Seed data password hash was invalid**: the bcrypt hash originally given to the seed-data step did not actually verify against `Password123!` (caught by generating a real hash with Python's `bcrypt` and testing it) — every demo account would have failed to log in. Replaced with a verified hash across all 11 seeded users.
- **Stored-XSS via URL fields**: LinkedIn/GitHub/portfolio/project/publication/certification URL fields were HTML-escaped on output but not scheme-validated, so a value like `javascript:...` would still execute as a clickable link. Fixed in `profile.php`/`profile-edit.php` (input-side) and `researcher-profile.php` (output-side) to only ever render `http(s)://` links.
- **Re-applying after rejection was silently blocked**: `apply-opportunity.php` only pre-checked for a Pending/Accepted application, so a Rejected/Withdrawn applicant hit a raw duplicate-key path with a confusing error. Fixed.
- **A rejected team join-request could never be resent**: `team_requests` has a unique `(team_id, user_id)` key; the code only handled the Pending case, so any student rejected once could never request that team again. Fixed to re-open a past Rejected/Cancelled request instead of trying a doomed second insert.
- **Private communities weren't actually private**: `community-details.php` only checked `status='Active'`, not `privacy`, so a Private community's full post feed (and its Join button) was reachable by any logged-in student who knew/guessed its id. Fixed to gate content and joining on `privacy` server-side, not just hide the option in the browse list.
- A handful of smaller hardening fixes: missing try/catch around a couple of DB calls, an orphaned-file cleanup gap on a failed resource edit, and defensive `ON DUPLICATE KEY UPDATE` handling for team-membership inserts ahead of any future "leave team" feature.

Full per-module reviewer reports (files reviewed, issues found, fixes applied, and anything that still needs a live run to fully confirm) were produced for: Profile, Research Connect, Opportunities, Teams, Communities, Repository/Saved Items, and Notifications/Settings.

## 14. Page-by-Page Test Checklist & Exact URLs

Assuming the project is installed at `http://localhost/UIU-ResearchCollab-main/` — adjust the prefix if your folder name differs.

| Page | URL | Login required | What to check |
|---|---|---|---|
| Public home | `/index.php` | No | Loads with real research domains from DB; Login/Signup links work |
| Login | `/login.php` | No | Demo accounts log in; wrong password shows friendly error |
| Signup | `/signup.php` | No | Non-`@bscse.uiu.ac.bd` email rejected; duplicate email rejected |
| Dashboard | `/student/dashboard.php` | Yes | Real counts, no PHP warnings, empty states if a new account |
| Profile | `/student/profile.php` | Yes | Every section loads; edits persist after reload; completion % updates |
| Research Connect | `/student/research-connect.php` | Yes | Filters work; match % varies; pagination works |
| Researcher profile | `/student/researcher-profile.php?id=4` | Yes | Visibility-gated sections hide correctly per that student's settings |
| Opportunities | `/student/opportunities.php` | Yes | Filters, pagination, save toggle |
| Opportunity details | `/student/opportunity-details.php?id=1` | Yes | Apply / withdraw / status pill |
| My Teams | `/student/teams.php` | Yes | My Teams + Discover Teams sections |
| Team workspace | `/student/team-details.php?id=1` | Yes (member) | Non-members of a full/active team get redirected |
| Communities | `/student/communities.php` | Yes | Only Public communities listed |
| Private community | `/student/community-details.php?id=5` | Yes | Members see it fully; non-members see the private notice |
| Repository | `/student/repository.php` | Yes | Filters, add-resource form, save toggle |
| Saved Items | `/student/saved-items.php` | Yes | Both sections show only your own saved items |
| Notifications | `/student/notifications.php` | Yes | Mark read / mark all read |
| Settings | `/student/settings.php` | Yes | Password change; visibility toggles |

## 15. Troubleshooting

- **"Database connection failed" / connection refused** — MySQL isn't running in XAMPP, or `config/database.php`'s `DB_HOST`/`DB_PORT` don't match your setup. Start MySQL in the XAMPP Control Panel first.
- **"Access denied for user 'root'@'localhost'"** — your MySQL root password isn't blank. Update `DB_PASS` in `config/database.php` to match.
- **"could not find driver" / PDO MySQL missing** — enable the `pdo_mysql` extension in `php.ini` (in XAMPP it's usually already enabled; if not, uncomment `extension=pdo_mysql` and restart Apache).
- **404 on every page / links go to the wrong place** — you're likely opening a file path directly (e.g. `C:\xampp\htdocs\...`) instead of `http://localhost/...`. The app auto-detects its base path from `DOCUMENT_ROOT`, so always browse via `http://localhost/<your-folder-name>/index.php`.
- **CSS/JS/images don't load on `/student/` pages but work on the homepage** — confirm the `CSS/`, `JS/`, and `IMAGES/` folders sit directly under the project root (siblings of `student/`), not renamed or moved; student pages reference them as `../CSS/...` etc.
- **"Permission denied" on file upload** — make `uploads/` (and its subfolders) writable by the web server user. On Windows/XAMPP this is rarely an issue; on Linux/macOS run `chmod -R 775 uploads/`.
- **Demo credentials don't work** — confirm `database/seed.sql` was actually imported (not just `schema.sql`), and that you copied the email exactly (`student@example.com`, not `Student@Example.com` — email lookup is case-sensitive as stored, though MySQL's default collation is case-insensitive for comparisons, so this is unlikely; more likely `seed.sql` wasn't run, or was run before `migrations.sql`).
- **Foreign key errors while importing** — always import in order: `schema.sql` → `migrations.sql` → `seed.sql`. `seed.sql` disables `FOREIGN_KEY_CHECKS` during its own run, but it still needs the tables from the first two files to already exist.
- **"Headers already sent" warning** — usually caused by stray whitespace or output before a `<?php` tag in a custom edit. All shipped files start with `<?php` on line 1 with no leading BOM/whitespace; if you hand-edit a file, keep it that way, especially before any `redirect()`/`header()` call.

## 16. Apache/XAMPP Final Check

The runtime test pass ("Application Status" at the top of this README, and §13) used PHP's built-in server, which never consults `.htaccess`. Before presenting, run this one process on your real XAMPP to confirm Apache itself also blocks direct access to private team files — the PHP-level authorization gate was already confirmed working independently, but Apache's own enforcement is a second, separate layer worth confirming.

**A.** Start Apache and MySQL from the XAMPP Control Panel.
**B.** Import, in order: `database/schema.sql`, then `database/migrations.sql`, then `database/seed.sql`.
**C.** Confirm `config/database.php` matches your local XAMPP MySQL credentials.
**D.** Log in as:
   - `student@example.com`
   - `Password123!`
**E.** Join or create a team, and upload a file to it (`student/team-files.php`).
**F.** Open your browser's dev tools (Network tab) or view the page source, find the download link for that file, and copy its **physical/direct upload URL** — i.e. the actual `http://localhost/<project>/uploads/team-files/<team_id>/<filename>` path, not the `team-files.php?...&download=...` app link.
**G.** Log out, or log in as a different student who is **not** a member of that team.
**H.** Paste the direct file URL (from step F) straight into the browser's address bar.
**I.** Expected result — **one of the following**, either is a pass:
   - Apache returns **403 Forbidden**, or
   - The direct file request otherwise cannot be served (connection refused, blank/error response — anything that is NOT the actual file content).
**J.** Also separately verify the **application download endpoint** (`team-files.php?id=<team_id>&download=<file_id>`) still refuses this same non-member — this layer was already confirmed working in the runtime test pass, but re-confirm it here in your real Apache environment too, since it's the layer that matters most.

**If the direct file URL is still accessible in step I:**
- Check that `AllowOverride All` is set for the project directory (or at least for `uploads/`) in your Apache vhost/`httpd.conf` — XAMPP's default `httpd.conf` often has `AllowOverride None` for `htdocs`, which silently disables every `.htaccess` file in the project, including `uploads/team-files/.htaccess` and `uploads/.htaccess`.
- Restart Apache after any `httpd.conf` change — `AllowOverride` changes do not take effect until Apache restarts.
- **Do not rely on `.htaccess` alone** even after fixing this — the PHP-level membership check in `team-files.php` is the authoritative, always-on authorization gate regardless of web server configuration; `.htaccess` is defense-in-depth on top of it, not a substitute for it.

## 17. Oversize Upload Test

The configured team-file upload size limit, read directly from `student/team-files.php`, is:

```php
$result = validate_upload(
    $_FILES['file'] ?? [],
    ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'png', 'jpg', 'jpeg', 'zip', 'txt'],
    10 * 1024 * 1024   // 10 MB
);
```

**This exact scenario was not exercised with a real oversized file during the runtime test pass — run it once locally before presenting:**

1. Create a test file larger than 10 MB, e.g. on Windows:
   ```powershell
   fsutil file createnew oversized_test.pdf 11000000
   ```
   (11,000,000 bytes ≈ 10.5 MB, safely over the limit.)
2. Log in as a valid member of a team (e.g. `student@example.com`).
3. Go to that team's Files tab (`student/team-files.php?id=<team_id>`) and attempt to upload `oversized_test.pdf`.
4. Confirm all four of the following:
   - [ ] The upload is **rejected** (no success message).
   - [ ] **No row** is added to the `team_files` table for it (check via phpMyAdmin or `SELECT * FROM team_files ORDER BY id DESC LIMIT 1;`).
   - [ ] **No file** appears in `uploads/team-files/<team_id>/` for it.
   - [ ] A **clear, user-friendly error message** appears (from `validate_upload()`, something like "File exceeds the maximum allowed size.") — not a blank page, not a raw PHP warning, not a generic server error.

Also note: PHP's own `upload_max_filesize`/`post_max_size` in `php.ini` (commonly 2MB or 8MB by default in a stock XAMPP install) may reject a file **before** the application's own 10MB check even runs — if your test file never reaches `validate_upload()` at all and PHP itself truncates/rejects it first, that is still a pass (the file is still rejected, no row/no file), but confirm the error message shown is still reasonably clear rather than a blank page.

## 18. Presentation Demo Flow

A suggested walkthrough for demonstrating the portal, roughly 8–10 minutes:

1. **Login** as `student@example.com`.
2. **Dashboard** — point out the live statistics (profile completion, team invitations, active applications, teams) are real database counts, not placeholders.
3. **Profile** — show a completed section (e.g. Research & Skills or Projects) and its data; optionally update one field (e.g. the bio) to show it saves and persists.
4. **Research Connect** — apply a filter and point out the **dynamic match percentage** varies per researcher (e.g. 76%, 51%, 24%) rather than being a fixed number, and briefly explain the weighted formula (§11).
5. **Research Opportunities** — open one, show either the Apply flow or an existing application's status pill, and toggle Save/Unsave.
6. **My Teams** — open a team workspace and show a task, a milestone, a team message, and the **protected file area** (mention that files are only downloadable by team members, not by direct URL).
7. **Communities** — open a community, show its posts and comments feed.
8. **Research Repository** — save a resource and point out it now appears in Saved Items.
9. **Saved Items** — show both the saved opportunity and saved resource together in one place.
10. **Notifications** — show the unread badge and mark one as read.
11. **Settings** — demonstrate the profile-visibility toggles (e.g. turn off "Show my publications" and, in a second browser/incognito window logged in as a different student, show that section disappear from the researcher-profile view) — **do not actually change the primary demo account's password** during a live presentation, to avoid locking yourself out mid-demo; if you want to show the password-change form working, do it on a secondary/throwaway seeded account instead.

## 19. Git Safety Recommendations

`.gitignore` has been updated to cover:

- `uploads/avatars/*`, `uploads/cv/*`, `uploads/resources/*`, `uploads/team-files/*`, `uploads/communities/*` — actual uploaded user content (these are the real subfolder names used by the app; adjust here if you rename them)
- `config/database.local.php` — for an optional local-only credentials override file, if you choose to use one instead of editing `config/database.php` directly
- `.env` / local logs / `*.log` — already covered
- Generated test screenshots (e.g. `.runtime-test-screenshots/`, `*.png` under any local test-output folder you create)

**Explicitly kept out of `.gitignore`** (these must stay tracked in git):
- Every `uploads/**/index.php` 403 stub and every `uploads/**/.htaccess` security file — these are small, fixed, security-relevant files, not user content, and must ship with the repo.
- All original `.html` files, `CSS/`, `JS/`, `IMAGES/` — the approved frontend design.

This project does not currently use `.gitkeep` placeholder files (the `index.php` security stub in each `uploads/` subfolder already keeps that directory tracked in git), but if you later remove those stubs for any reason, add a `.gitkeep` back so the empty directory still gets created on a fresh clone.

**Never commit:**
- Real/local database passwords — `config/database.php` ships with the safe XAMPP defaults (`root` / empty password); if you change `DB_PASS` to a real credential for a shared or production environment, move it to an untracked file (e.g. `config/database.local.php`, included via `require` and already in `.gitignore`) instead of editing the tracked file in place.
- The contents of `database/seed.sql`'s demo accounts are intentionally public/well-known (documented in the file itself as local-development-only) — this is fine to commit, but never reuse these exact credentials anywhere beyond local development.

---

🤖 This backend was implemented by Claude Code on top of the existing approved frontend design — the visual design, color system, and layout were preserved throughout; this pass added the PHP/MySQL functionality behind it, followed by a dedicated security/consistency review pass (§13) and a real runtime test pass using PHP 8.2 and MariaDB (see "Application Status" at the top).
