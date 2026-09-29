"""Generates docs/user-journey.png (Student / Faculty / Admin journey swimlanes).
Run: python docs/generate_journey.py   (requires Pillow)
"""
from PIL import Image, ImageDraw, ImageFont
import os

FONT = "C:/Windows/Fonts/segoeui.ttf"
BOLD = "C:/Windows/Fonts/segoeuib.ttf"
S = 2
W = 3400
LEFT, RIGHT, GAP = 300, 70, 30
NCOL = 10
BW = (W - LEFT - RIGHT - (NCOL - 1) * GAP) // NCOL
BH = 190
LANE_GAP = 190
TOP = 230
ORANGE = "#FF8800"

LANES = [
    ("Student", "#FF8800", "#FFF2E5", {
        0: ("Discover", "Landing page: about, how it works, domains", "index.php"),
        1: ("Sign up", "Create student account with UIU details", "signup.php"),
        2: ("Log in", "Authenticate and land on dashboard", "login.php"),
        3: ("Build profile", "Skills, interests, CV, availability", "student/profile-edit.php"),
        4: ("Find opportunities", "Browse, filter, save projects", "student/opportunities.php"),
        5: ("Apply", "Submit application to a faculty post", "student/apply-opportunity.php"),
        6: ("Form a team", "Create team, invite, accept requests", "student/team-create.php"),
        7: ("Collaborate", "Tasks, milestones, files, team chat", "student/team-tasks.php"),
        8: ("Request an advisor", "Ask a faculty member to mentor", "student/advisor-requests.php"),
        9: ("Share & engage", "Repository, communities, messages", "student/repository.php"),
    }),
    ("Faculty", "#1D4ED8", "#E8EEFC", {
        1: ("Sign up", "Register as faculty, submit for review", "signup.php"),
        2: ("Log in", "Access portal once verified by admin", "login.php"),
        3: ("Complete profile", "Research areas, availability, bio", "faculty/profile-edit.php"),
        4: ("Post opportunity", "Create research opening with needs", "faculty/opportunity-create.php"),
        5: ("Review applications", "Shortlist, accept or decline", "faculty/applications.php"),
        6: ("Find students", "Discover talent, send connections", "faculty/research-connect.php"),
        7: ("Guide teams", "Follow projects, message members", "faculty/advised-teams.php"),
        8: ("Answer mentorship", "Accept or decline advisor requests", "faculty/mentorship-requests.php"),
        9: ("Publish resources", "Add papers and materials to repository", "faculty/repository.php"),
    }),
    ("Admin", "#7E22CE", "#F3E8FB", {
        0: ("Log in", "Secure admin access", "login.php"),
        1: ("Monitor dashboard", "Platform KPIs and pending items", "admin/dashboard.php"),
        2: ("Verify faculty", "Approve or reject faculty accounts", "admin/faculty-verification.php"),
        3: ("Manage master data", "Domains, skills, languages", "admin/domains.php"),
        4: ("Moderate content", "Opportunities, teams, communities", "admin/opportunities.php"),
        8: ("Oversee advisors", "Requests and assignments", "admin/advisor-assignments.php"),
        9: ("Report & audit", "Reports, CSV export, activity logs", "admin/reports.php"),
    }),
]

# (from_lane, to_lane, col, label, dashed)
HANDOFFS = [
    (1, 0, 4, "opportunity published", False),
    (0, 1, 5, "application sent", False),
    (0, 1, 7, "progress shared", True),
    (0, 1, 8, "advisor request", False),
    (1, 0, 9, "resources shared", True),
    (2, 1, 2, "faculty approved", False),
    (2, 1, 4, "moderates posts", True),
    (2, 1, 8, "assigns / oversees", True),
]

LANE_H = BH + 60
H = TOP + len(LANES) * LANE_H + (len(LANES) - 1) * (LANE_GAP - 60) + 230

img = Image.new("RGB", (W * S, H * S), "#FFFFFF")
d = ImageDraw.Draw(img)


def rgb(c):
    c = c.lstrip("#")
    return tuple(int(c[i:i + 2], 16) for i in (0, 2, 4))


def fnt(p, s):
    return ImageFont.truetype(p, s * S)


def rr(x0, y0, x1, y1, r, fill=None, outline=None, w=2):
    d.rounded_rectangle([x0 * S, y0 * S, x1 * S, y1 * S], r * S, fill=fill and rgb(fill), outline=outline and rgb(outline), width=w * S)


def tx(x, y, s, font, fill, anchor="la"):
    d.text((x * S, y * S), s, font=font, fill=rgb(fill), anchor=anchor)


def wrap(s, font, width):
    words, lines, cur = s.split(), [], ""
    for w_ in words:
        t = (cur + " " + w_).strip()
        if d.textlength(t, font=font) / S <= width or not cur:
            cur = t
        else:
            lines.append(cur)
            cur = w_
    lines.append(cur)
    return lines


def line(p0, p1, color, w=4, dashed=False):
    if not dashed:
        d.line([p0[0] * S, p0[1] * S, p1[0] * S, p1[1] * S], fill=rgb(color), width=w * S)
        return
    (x0, y0), (x1, y1) = p0, p1
    n = int(max(abs(x1 - x0), abs(y1 - y0)) // 16)
    for i in range(0, n, 2):
        a, b = i / n, min((i + 1) / n, 1)
        d.line([(x0 + (x1 - x0) * a) * S, (y0 + (y1 - y0) * a) * S, (x0 + (x1 - x0) * b) * S, (y0 + (y1 - y0) * b) * S], fill=rgb(color), width=w * S)


def arrowhead(x, y, direction, color, size=14):
    # direction: 'r', 'd', 'u'
    pts = {"r": [(x, y), (x - size, y - size / 1.6), (x - size, y + size / 1.6)],
           "d": [(x, y), (x - size / 1.6, y - size), (x + size / 1.6, y - size)],
           "u": [(x, y), (x - size / 1.6, y + size), (x + size / 1.6, y + size)]}[direction]
    d.polygon([(px * S, py * S) for px, py in pts], fill=rgb(color))


d.rectangle([0, 0, W * S, H * S], fill=rgb("#F8F9FA"))
tx(W / 2, 40, "UIU ResearchCollab — User Journey", fnt(BOLD, 54), "#212529", "ma")
tx(W / 2, 112, "How Students, Faculty and Admins move through the platform, and where their journeys hand off to each other", fnt(FONT, 26), "#6C757D", "ma")

# stage bands
STAGES = [("Onboard", 0, 3), ("Discover & Connect", 4, 6), ("Collaborate", 7, 8), ("Contribute", 9, 9)]
xcol = lambda c: LEFT + c * (BW + GAP)
for name, a, b in STAGES:
    x0, x1 = xcol(a), xcol(b) + BW
    rr(x0, 165, x1, 205, 20, fill="#FFE3C2")
    tx((x0 + x1) / 2, 185, name.upper(), fnt(BOLD, 21), "#B45309", "mm")

lane_y = [TOP + i * (LANE_H + LANE_GAP - 60) for i in range(len(LANES))]

for li, (name, color, tint, steps) in enumerate(LANES):
    y = lane_y[li]
    rr(30, y - 30, W - 40, y + BH + 30, 22, fill=tint, outline=color, w=2)
    rr(30, y - 30, 250, y + BH + 30, 22, fill=color)
    d.rectangle([200 * S, (y - 30) * S, 250 * S, (y + BH + 30) * S], fill=rgb(color))
    tx(140, y + BH / 2 - 14, name, fnt(BOLD, 36), "#FFFFFF", "mm")
    tx(140, y + BH / 2 + 26, "journey", fnt(FONT, 22), "#FFFFFF", "mm")
    cols = sorted(steps)
    # connectors first
    for a, b in zip(cols, cols[1:]):
        x0, x1, ym = xcol(a) + BW, xcol(b), y + BH / 2
        line((x0, ym), (x1 - 2, ym), color, 5)
        arrowhead(x1, ym, "r", color)
    for n, c in enumerate(cols, 1):
        title, desc, page = steps[c]
        x = xcol(c)
        rr(x, y, x + BW, y + BH, 16, fill="#FFFFFF", outline=color, w=3)
        d.ellipse([(x - 16) * S, (y - 16) * S, (x + 28) * S, (y + 28) * S], fill=rgb(color))
        tx(x + 6, y + 6, str(n), fnt(BOLD, 24), "#FFFFFF", "mm")
        tx(x + 18, y + 20, title, fnt(BOLD, 25), "#212529")
        yy = y + 62
        for ln in wrap(desc, fnt(FONT, 19), BW - 36):
            tx(x + 18, yy, ln, fnt(FONT, 19), "#495057")
            yy += 26
        tx(x + 18, y + BH - 30, page, fnt(FONT, 16), color)

# handoffs between adjacent lanes
for fr, to, c, label, dashed in HANDOFFS:
    x = xcol(c) + BW / 2 + (28 if fr < to else -28)
    y_fr = lane_y[fr] + (BH if fr < to else 0)
    y_to = lane_y[to] + (0 if fr < to else BH)
    col = "#DC3545" if not dashed else "#6C757D"
    end = y_to - 3 if fr < to else y_to + 3
    line((x, y_fr), (x, end), col, 4, dashed)
    arrowhead(x, y_to, "d" if fr < to else "u", col)
    ym = (y_fr + y_to) / 2
    f = fnt(FONT, 19)
    tw = d.textlength(label, font=f) / S
    rr(x + 10, ym - 15, x + 26 + tw, ym + 15, 12, fill="#FFFFFF", outline=col, w=1)
    tx(x + 18, ym, label, f, col, "lm")

# legend
ly = H - 165
rr(30, ly, W - 40, ly + 120, 18, fill="#FFFFFF", outline="#DEE2E6", w=2)
tx(70, ly + 24, "How to read", fnt(BOLD, 24), "#212529")
line((70, ly + 84), (150, ly + 84), "#DC3545", 4)
arrowhead(150, ly + 84, "r", "#DC3545")
tx(170, ly + 84, "Direct hand-off: one role's action triggers the next role's task", fnt(FONT, 21), "#495057", "lm")
line((1000, ly + 84), (1080, ly + 84), "#6C757D", 4, True)
arrowhead(1080, ly + 84, "r", "#6C757D")
tx(1100, ly + 84, "Supporting interaction / oversight", fnt(FONT, 21), "#495057", "lm")
tx(1600, ly + 84, "Numbered boxes = steps within a role · coloured filename = main page in the codebase", fnt(FONT, 21), "#495057", "lm")

out = os.path.join(os.path.dirname(os.path.abspath(__file__)), "user-journey.png")
img.resize((W, H), Image.LANCZOS).save(out)
print(out, W, H)
