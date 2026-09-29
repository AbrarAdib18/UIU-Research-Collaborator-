"""Generates docs/information-architecture.png (site map of the UIU ResearchCollab portals).
Run: python docs/generate_ia.py   (requires Pillow)
"""
from PIL import Image, ImageDraw, ImageFont
import os

FONT = "C:/Windows/Fonts/segoeui.ttf"
BOLD = "C:/Windows/Fonts/segoeuib.ttf"
f = lambda p, s: ImageFont.truetype(p, s)
F_TITLE, F_SUB = f(BOLD, 54), f(FONT, 26)
F_HEAD, F_HSUB = f(BOLD, 34), f(FONT, 21)
F_GRP, F_ITEM = f(BOLD, 23), f(FONT, 21)
F_LAYER = f(BOLD, 26)

S = 2  # supersample for smooth edges
COLS = [
    ("Public Site", "Visitors · no login", "#0F766E", "#E6F4F1", [
        ("Landing (index.php)", ["Home", "About", "How It Works", "Community", "Research Domains", "FAQs / Help Center", "Contact Us"]),
        ("Authentication", ["Login", "Sign Up (student / faculty)", "Forgot Password", "Reset Password", "Logout"]),
        ("Legal", ["Terms of Service", "Privacy Policy"]),
    ]),
    ("Student Portal", "/student · student_guard", "#1D4ED8", "#E8EEFC", [
        ("Dashboard", ["Overview, quick stats & activity"]),
        ("Profile", ["View Profile", "Edit Profile (skills, CV, availability)", "Settings"]),
        ("Opportunities", ["Browse & Details", "Apply", "Save / Saved Items"]),
        ("Teams", ["My Teams · Create · Details", "Invitations · Join Requests", "Tasks · Milestones · Files", "Team Messages", "Team Advisor Requests"]),
        ("Research Connect", ["Find Researchers", "Researcher Profile"]),
        ("Faculty & Advisors", ["Faculty Connections", "Faculty Profile", "Advisor Requests"]),
        ("Communities", ["Browse · Create", "Community Details · Posts"]),
        ("Repository", ["Browse · Resource Details", "Save Resource"]),
        ("Messages & Notifications", ["Inbox · Conversation", "Notifications"]),
    ]),
    ("Faculty Portal", "/faculty · faculty_guard", "#B45309", "#FCF0E1", [
        ("Dashboard", ["Overview, requests & activity"]),
        ("Profile", ["View Profile", "Edit Profile", "Settings"]),
        ("Opportunities", ["My Opportunities · Details", "Create · Edit", "Applications · Application Details"]),
        ("Mentorship", ["Mentorship Requests · Details", "Advised Students", "Advised Teams · Advised Projects"]),
        ("Research Connect", ["Find Students", "Student Profile", "Faculty Connections"]),
        ("Communities", ["Browse", "Community Details"]),
        ("Repository", ["Browse Resources", "Create / Publish Resource"]),
        ("Messages & Notifications", ["Inbox · Conversation", "Notifications"]),
    ]),
    ("Admin Portal", "/admin · admin_guard", "#7E22CE", "#F3E8FB", [
        ("Dashboard", ["Platform overview & KPIs"]),
        ("User Management", ["Users · Details · Edit · Status", "Students", "Faculty · Faculty Verification"]),
        ("Master Data", ["Research Domains", "Skills", "Languages"]),
        ("Content Moderation", ["Opportunities · Details", "Applications", "Teams · Team Details", "Communities · Moderation", "Repository · Resource Details"]),
        ("Advisor System", ["Advisor Requests", "Advisor Assignments"]),
        ("Oversight", ["Connections", "Messages Monitor (metadata only)"]),
        ("Platform", ["Notifications / Announcements", "Reports (CSV export)", "Activity Logs (audit)", "Settings (7 settings)"]),
    ]),
]

LAYERS = [
    ("Shared Application Layer (includes/)", "#334155", [
        "bootstrap.php", "auth.php", "csrf.php", "flash.php", "functions.php",
        "student / faculty / admin guards", "role headers · sidebars · footers"]),
    ("API (api/chat)", "#334155", [
        "conversations.php", "get-messages.php", "send-message.php", "mark-read.php"]),
    ("Data & Storage", "#334155", [
        "MySQL / MariaDB via PDO (config/database.php)", "schema.sql · migrations 001–006 · seed.sql",
        "uploads/: avatars · cv · resources · communities"]),
]

W = 3400
PAD, GAP = 70, 40
COL_W = (W - 2 * PAD - 3 * GAP) // 4
CARD_PAD, LINE = 20, 34


def h(c, a=255):
    c = c.lstrip("#")
    return tuple(int(c[i:i + 2], 16) for i in (0, 2, 4)) + (a,)


def group_h(items):
    return CARD_PAD + 34 + len(items) * LINE + CARD_PAD - 6


def col_h(groups):
    return 130 + sum(group_h(i) + 22 for _, i in groups)


body_h = max(col_h(c[4]) for c in COLS)
layer_y = 300 + body_h + 110
H = layer_y + 270

img = Image.new("RGBA", (W * S, H * S), h("#FFFFFF"))
d = ImageDraw.Draw(img)


def rr(x0, y0, x1, y1, r, fill=None, outline=None, w=2):
    d.rounded_rectangle([x0 * S, y0 * S, x1 * S, y1 * S], r * S, fill=fill, outline=outline, width=w * S)


def tx(x, y, s, font, fill, anchor="la"):
    d.text((x * S, y * S), s, font=ImageFont.truetype(font.path, font.size * S), fill=fill, anchor=anchor)


def line(p0, p1, fill, w=4):
    d.line([p0[0] * S, p0[1] * S, p1[0] * S, p1[1] * S], fill=fill, width=w * S)


# background
d.rectangle([0, 0, W * S, H * S], fill=h("#F8FAFC"))
tx(W / 2, 40, "UIU ResearchCollab — Information Architecture", F_TITLE, h("#0F172A"), "ma")
tx(W / 2, 112, "Site map of the Public, Student, Faculty and Admin portals  ·  Core PHP + MySQL (PDO)  ·  Bootstrap 5", F_SUB, h("#475569"), "ma")

# root node
rx0, rx1, ry0, ry1 = W / 2 - 440, W / 2 + 440, 170, 240
rr(rx0, ry0, rx1, ry1, 18, fill=h("#0F172A"))
tx(W / 2, 205, "Role-based Entry  ·  login.php / index.php", F_HEAD, h("#FFFFFF"), "mm")

# connectors
bus_y = 272
line((W / 2, ry1), (W / 2, bus_y), h("#94A3B8"))
xs = [PAD + i * (COL_W + GAP) + COL_W / 2 for i in range(4)]
line((xs[0], bus_y), (xs[-1], bus_y), h("#94A3B8"))
for x in xs:
    line((x, bus_y), (x, 300), h("#94A3B8"))

for i, (name, sub, color, tint, groups) in enumerate(COLS):
    x0 = PAD + i * (COL_W + GAP)
    x1 = x0 + COL_W
    rr(x0, 300, x1, 300 + body_h, 22, fill=h(tint), outline=h(color), w=3)
    rr(x0, 300, x1, 410, 22, fill=h(color))
    d.rectangle([x0 * S, 380 * S, x1 * S, 410 * S], fill=h(color))
    tx(x0 + COL_W / 2, 336, name, F_HEAD, h("#FFFFFF"), "mm")
    tx(x0 + COL_W / 2, 378, sub, F_HSUB, h("#FFFFFFDD"), "mm")
    y = 432
    for gname, items in groups:
        gh = group_h(items)
        rr(x0 + 18, y, x1 - 18, y + gh, 12, fill=h("#FFFFFF"), outline=h(color, 120), w=2)
        d.rectangle([(x0 + 18) * S, (y + 14) * S, (x0 + 24) * S, (y + gh - 14) * S], fill=h(color))
        tx(x0 + 40, y + CARD_PAD - 2, gname, F_GRP, h(color))
        yy = y + CARD_PAD + 34
        for it in items:
            tx(x0 + 44, yy + 12, "•", F_ITEM, h(color), "lm")
            tx(x0 + 66, yy + 12, it, F_ITEM, h("#1E293B"), "lm")
            yy += LINE
        y += gh + 22

# shared layer
line((W / 2, 300 + body_h), (W / 2, layer_y - 46), h("#94A3B8"))
tx(W / 2, layer_y - 40, "All portals are built on", f(FONT, 22), h("#64748B"), "ma")
lw = (W - 2 * PAD - 2 * GAP) // 3
for i, (name, color, items) in enumerate(LAYERS):
    x0 = PAD + i * (lw + GAP)
    rr(x0, layer_y, x0 + lw, layer_y + 220, 18, fill=h("#E2E8F0"), outline=h(color), w=3)
    tx(x0 + 28, layer_y + 18, name, F_LAYER, h("#0F172A"))
    if i == 0:
        cols = [items[0:4], items[4:7]]
        for ci, ch in enumerate(cols):
            for j, it in enumerate(ch):
                tx(x0 + 34 + ci * (lw // 2), layer_y + 74 + j * 34, "▪ " + it, F_ITEM, h("#1E293B"))
    else:
        for j, it in enumerate(items):
            tx(x0 + 34, layer_y + 74 + j * 34, "▪ " + it, F_ITEM, h("#1E293B"))

out = os.path.join(os.path.dirname(os.path.abspath(__file__)), "information-architecture.png")
img.convert("RGB").resize((W, H), Image.LANCZOS).save(out)
print(out, W, H)
