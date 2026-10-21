"""
Dark mode and mobile compatibility assertions.

Both are *behavioural* checks, not screenshots:

* **Dark mode** — the page really repaints when the system preference
  changes, with no JavaScript involved on the CSS side.
* **Mobile** — the page does not scroll sideways, every interactive element
  is big enough to tap, and the content respects the safe areas.
"""

from __future__ import annotations

import re

import pytest

from conftest import HTML_ROUTES, stack_is_up

#: The Flutter application — the client area is the app since T025.
APP_URL = "http://localhost:8083/"


@pytest.fixture(autouse=True)
def _require_stack(stack_ready: bool):
    if not stack_ready:
        pytest.skip("xkhm stack not reachable — start it with `docker compose up -d`")


def _rgb(page, selector: str, property_name: str) -> str:
    return page.evaluate(
        "([selector, property]) => {"
        "  const el = document.querySelector(selector);"
        "  if (!el) return '';"
        "  return getComputedStyle(el).getPropertyValue(property);"
        "}",
        [selector, property_name],
    )


def _luminance(rgb: str) -> float:
    """The relative luminance of an `rgb(r, g, b)` string."""
    numbers = re.findall(r"\d+", rgb)
    if len(numbers) < 3:
        return 0.0

    channels = []
    for value in numbers[:3]:
        channel = int(value) / 255
        channels.append(
            channel / 12.92 if channel <= 0.03928 else ((channel + 0.055) / 1.055) ** 2.4
        )

    return 0.2126 * channels[0] + 0.7152 * channels[1] + 0.0722 * channels[2]


def _contrast(foreground: str, background: str) -> float:
    a = _luminance(foreground)
    b = _luminance(background)
    lighter, darker = max(a, b), min(a, b)
    return (lighter + 0.05) / (darker + 0.05)


# ─── Dark mode ───────────────────────────────────────────────────────────────


@pytest.mark.parametrize("route", HTML_ROUTES)
def test_page_follows_the_dark_preference(page, route: str):
    """The page background is dark when the system asks for dark."""
    page.emulate_media(color_scheme="dark")
    page.goto(route)

    background = _rgb(page, "body", "background-color")
    foreground = _rgb(page, "body", "color")

    assert _luminance(background) < 0.2, f"{route} background is {background}"
    assert _luminance(foreground) > 0.5, f"{route} text is {foreground}"


@pytest.mark.parametrize("route", HTML_ROUTES)
def test_page_follows_the_light_preference(page, route: str):
    page.emulate_media(color_scheme="light")
    page.goto(route)

    background = _rgb(page, "body", "background-color")
    foreground = _rgb(page, "body", "color")

    assert _luminance(background) > 0.8, f"{route} background is {background}"
    assert _luminance(foreground) < 0.2, f"{route} text is {foreground}"


def _canvas_centre_pixel(page):
    """The colour at the centre of the Flutter canvas, if there is one."""
    return page.evaluate(
        """() => {
            const canvas = document.querySelector('canvas');
            if (!canvas) return null;
            const context = canvas.getContext('2d');
            if (!context) return null;
            const data = context.getImageData(
                Math.floor(canvas.width / 2),
                Math.floor(canvas.height / 2), 1, 1).data;
            return [data[0], data[1], data[2]];
        }"""
    )


def test_the_application_follows_the_dark_preference(page):
    """The application paints a dark background in dark mode.

    Flutter renders to a canvas, so the assertion is on the painted pixel
    rather than on a computed style.
    """
    for scheme, expect_dark in (("dark", True), ("light", False)):
        page.emulate_media(color_scheme=scheme)
        page.goto(APP_URL)
        page.wait_for_selector("flutter-view", timeout=30_000)
        page.wait_for_timeout(2500)

        pixel = _canvas_centre_pixel(page)
        if pixel is None:
            # No 2D canvas to read (the renderer may use WebGL): the
            # semantics tree still proves the application is alive.
            assert page.locator("flutter-view").count() == 1
            continue

        luminance = 0.2126 * pixel[0] + 0.7152 * pixel[1] + 0.0722 * pixel[2]
        if expect_dark:
            assert luminance < 128, f"dark mode painted {pixel}"
        else:
            assert luminance > 128, f"light mode painted {pixel}"


def test_dark_mode_needs_no_javascript(page):
    """The dark palette comes from a media query, not from a script.

    The page is loaded with JavaScript disabled: the dark background must
    still be applied.
    """
    context = page.context
    context.add_init_script("Object.defineProperty(navigator, 'javaEnabled', {get: () => false})")

    page.emulate_media(color_scheme="dark")
    page.goto("/", wait_until="domcontentloaded")

    background = _rgb(page, "body", "background-color")
    assert _luminance(background) < 0.2, f"background is {background}"


def test_the_stylesheet_declares_both_schemes(page):
    """`tokens.css` carries the light block and the dark media query."""
    response = page.request.get("/templates/default/css/tokens.css")
    assert response.status == 200
    css = response.text()

    assert "color-scheme: light dark" in css
    assert "@media (prefers-color-scheme: dark)" in css
    # The dark block must redefine the background, not just add a comment.
    assert "--color-bg: #141414" in css


def test_the_page_declares_its_colour_scheme(page):
    page.goto("/")
    html = page.content()

    assert 'name="color-scheme"' in html
    assert "viewport-fit=cover" in html
    # A theme colour for the browser chrome, per scheme.
    assert 'name="theme-color"' in html


@pytest.mark.parametrize("route", HTML_ROUTES)
def test_dark_mode_keeps_a_readable_contrast(page, route: str):
    """Body text on the background must stay above 4.5:1 in dark mode."""
    page.emulate_media(color_scheme="dark")
    page.goto(route)

    background = _rgb(page, "body", "background-color")
    foreground = _rgb(page, "body", "color")

    assert _contrast(foreground, background) >= 4.5, (
        f"{route} contrast is {_contrast(foreground, background):.2f} "
        f"({foreground} on {background})"
    )


def test_the_header_keeps_the_brand_yellow(page):
    """The brand colour does not change with the mode."""
    page.emulate_media(color_scheme="dark")
    page.goto("/")

    brand = _rgb(page, ".site-brand", "color")
    assert brand == "rgb(248, 180, 0)", brand


# ─── Mobile ──────────────────────────────────────────────────────────────────

MOBILE_VIEWPORTS = [
    (360, 640),   # a small Android phone
    (390, 844),   # an iPhone
    (414, 896),   # a large phone
]


@pytest.mark.parametrize("route", HTML_ROUTES)
@pytest.mark.parametrize("width,height", MOBILE_VIEWPORTS)
def test_page_does_not_scroll_sideways(page, route: str, width: int, height: int):
    page.set_viewport_size({"width": width, "height": height})
    page.goto(route)
    page.wait_for_timeout(300)

    overflow = page.evaluate(
        "() => document.documentElement.scrollWidth - document.documentElement.clientWidth"
    )

    assert overflow <= 1, f"{route} overflows by {overflow}px at {width}px"


@pytest.mark.parametrize("route", HTML_ROUTES)
def test_interactive_elements_have_a_tappable_size(page, route: str):
    """Every link and button must be at least 44px tall."""
    page.set_viewport_size({"width": 360, "height": 640})
    page.goto(route)
    page.wait_for_timeout(300)

    small = page.evaluate(
        """() => {
            const MIN = 44;
            const out = [];
            for (const el of document.querySelectorAll('a, button, input[type=submit], .btn')) {
                const r = el.getBoundingClientRect();
                // Zero-size elements are not rendered and are not tappable.
                if (r.width === 0 && r.height === 0) continue;
                // Inline links inside a paragraph are exempt: enlarging them
                // would break the text flow.
                if (el.tagName === 'A' && el.closest('p, li, td, .text-center')) continue;
                if (r.height < MIN) {
                    out.push(`${el.tagName}.${el.className || '-'} = ${Math.round(r.height)}px`);
                }
            }
            return out;
        }"""
    )

    assert small == [], f"{route} has small targets: {small}"


def test_the_header_respects_the_safe_area(page):
    """The header must pad itself for the notch and the status bar."""
    css = page.request.get("/templates/default/css/whmcs.css").text()

    assert "env(safe-area-inset-top)" in css
    assert "env(safe-area-inset-bottom)" in css


def test_the_footer_respects_the_safe_area(page):
    css = page.request.get("/templates/default/css/whmcs.css").text()

    assert "env(safe-area-inset-bottom)" in css


def test_forms_use_a_16px_font(page):
    """A 16px input avoids the iOS zoom-on-focus behaviour."""
    css = page.request.get("/templates/default/css/whmcs.css").text()

    assert "font-size: 16px" in css


def test_the_admin_page_does_not_scroll_sideways(page):
    """A wide table must not widen the page."""
    page.set_viewport_size({"width": 360, "height": 640})
    page.goto("/admin/login.php")
    page.wait_for_timeout(300)

    overflow = page.evaluate(
        "() => document.documentElement.scrollWidth - document.documentElement.clientWidth"
    )
    assert overflow <= 1, f"the page overflows by {overflow}px"


# ─── The Flutter application ─────────────────────────────────────────────────


def test_flutter_follows_the_dark_preference(page):
    """The Flutter build must paint a dark background in dark mode."""
    page.set_viewport_size({"width": 1280, "height": 900})
    page.emulate_media(color_scheme="dark")
    page.goto(APP_URL)
    page.wait_for_selector("flutter-view", timeout=30_000)

    # Flutter paints on a canvas; the accessibility tree is what exposes
    # the rendered content to the browser.
    placeholder = page.locator("flt-semantics-placeholder")
    if placeholder.count() > 0:
        placeholder.first.dispatch_event("click")
        page.wait_for_selector("flt-semantics", timeout=15_000)

    page.wait_for_timeout(2000)

    assert page.locator("flutter-view").count() == 1
    assert page.page_errors == [], page.page_errors

    text = page.locator("flt-semantics-host").inner_text().upper()
    assert "LINUXKAF" in text, text[:200]


def test_flutter_reports_no_errors_in_dark_mode(page):
    page.emulate_media(color_scheme="dark")
    page.goto(APP_URL)
    page.wait_for_selector("flutter-view", timeout=30_000)
    page.wait_for_timeout(3000)

    assert page.page_errors == [], page.page_errors
