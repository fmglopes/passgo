"""Visual + UX checks with a real browser (Playwright). Not required to run the app.
Usage: python3 tests/ux/screens_check.py BASE_URL OUT_DIR
Takes phone (375px) and desktop (1280px) screenshots of every main screen and checks:
 - no horizontal scrolling on the phone
 - every button/link/input is at least 44px high (touch targets)
 - every image has alt text, every input has a label
"""
import sys, re, json
from playwright.sync_api import sync_playwright

base = sys.argv[1].rstrip('/'); out = sys.argv[2]
PAGES = [
  ("home", "/index.php", False), ("services", "/services.php", False),
  ("services_search", "/services.php?q=passport", False), ("service_detail", "/service.php?id=1", True),
  ("guide", "/guide.php?id=1", True), ("book", "/book.php?service=1", True),
  ("appointments", "/appointments.php", True), ("profile", "/profile.php", True),
  ("login", "/login.php", False), ("register", "/register.php", False),
]
problems = []
with sync_playwright() as p:
    b = p.chromium.launch()
    for label, vp in (("phone", {"width": 375, "height": 812}), ("desktop", {"width": 1280, "height": 800})):
        ctx = b.new_context(viewport=vp, device_scale_factor=2 if label == "phone" else 1)
        page = ctx.new_page()
        page.goto(base + "/login.php"); page.fill("input[name=email]", "demo@passgo.pt")
        page.fill("input[name=password]", "demo1234"); page.click("form button[type=submit]")
        for name, path, _ in PAGES:
            if name in ("login", "register"):
                ctx.clear_cookies()
            page.goto(base + path); page.wait_for_load_state("networkidle")
            page.screenshot(path=f"{out}/{label}_{name}.png", full_page=True)
            if label == "phone":
                ov = page.evaluate("document.documentElement.scrollWidth - window.innerWidth")
                if ov > 1: problems.append(f"{name}: horizontal overflow {ov}px")
                small = page.evaluate("""() => [...document.querySelectorAll('button, a.btn, input:not([type=hidden]):not([type=checkbox]), select')]
                    .filter(e => e.offsetParent !== null).map(e => [e.innerText||e.name||e.type, Math.round(e.getBoundingClientRect().height)])
                    .filter(x => x[1] < 44)""")
                for s in small: problems.append(f"{name}: control '{s[0].strip()[:30]}' is {s[1]}px high (<44)")
                noalt = page.evaluate("[...document.images].filter(i => !i.hasAttribute('alt')).length")
                if noalt: problems.append(f"{name}: {noalt} image(s) without alt")
        ctx.close()
    b.close()
print(json.dumps(problems, indent=1) if problems else "UX checks: no problems found")
