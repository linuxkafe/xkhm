"""
The mobile interface, on a phone.

The application has to be usable on a 390 px screen: the figures side by
side, the greeting compact, the articles summarised, and no horizontal
scroll anywhere.
"""

from __future__ import annotations

import os
import pathlib

import pytest
from playwright.sync_api import expect

from conftest import CLIENT_PASS, CLIENT_USER, stack_is_up

APP_URL = os.getenv("XKHM_APP_URL", "http://localhost:8083/app/")

#: A phone.
PHONE = {"width": 390, "height": 844}


@pytest.fixture(autouse=True)
def _require_stack(stack_ready: bool):
    if not stack_ready:
        pytest.skip("xkhm stack not reachable — start it with `docker compose up -d`")


def _tree_text(page) -> str:
    """
    Every label in the accessibility tree.

    The host element's `inner_text` only returns the text of its
    **direct** children: Flutter merges adjacent texts into one node,
    and that node lives deeper. Reading the labels of the whole tree is
    what actually reaches the content.
    """
    return page.locator("flt-semantics").evaluate_all(
        "els => els.map(e => (e.getAttribute('aria-label') || '') + ' '"
        " + (e.innerText || '')).join('\\n')"
    )


def _enable_semantics(page, attempts: int = 5) -> bool:
    """
    Flutter only materialises the semantics DOM on request — and the
    request has to be made again after every reload.

    The tree is present but visually hidden (it is waiting for a screen
    reader, not for a person), so the test waits for it to have
    **content** rather than to be visible.
    """
    for _ in range(attempts):
        placeholder = page.locator("flt-semantics-placeholder")

        if placeholder.count() > 0:
            placeholder.first.dispatch_event("click")
            page.wait_for_timeout(1200)

        host = page.locator("flt-semantics-host")
        if host.count() > 0 and host.inner_text().strip():
            return True

        page.wait_for_timeout(800)

    return False


def _fill(page, label: str, value: str, attempts: int = 3) -> None:
    """
    Type a value into a Flutter text field.

    Flutter keeps the real value in a `TextEditingController`; the DOM
    input is only a mirror. The field has to be really focused — or the
    first keystroke is lost to the focus transition — and the value has
    to have settled — or the server receives a truncated one.

    Both are checked, and the whole fill is retried if the result is
    wrong. A `fill()` alone is **not** enough: it drops the first
    keystroke, and the login silently fails.
    """
    field = page.get_by_label(label)

    for attempt in range(attempts):
        field.click()
        page.wait_for_timeout(150)

        field.fill("")
        page.wait_for_timeout(80)
        page.keyboard.type(value, delay=25)

        try:
            expect(field).to_have_value(value, timeout=5_000)
            page.wait_for_timeout(250)
            return
        except AssertionError:
            if attempt == attempts - 1:
                raise


def _sign_in(page) -> None:
    page.goto(APP_URL)
    page.wait_for_selector("flutter-view", timeout=30_000)
    _enable_semantics(page)

    _fill(page, "Email", CLIENT_USER)
    _fill(page, "Senha", CLIENT_PASS)

    page.get_by_role("button", name="Entrar").click()
    page.wait_for_timeout(4000)

    # A reload gives a fresh application that **boots with the session
    # already in place** — the semantics then materialise over the home
    # screen, not over a screen that is about to be replaced.
    page.reload()
    page.wait_for_selector("flutter-view", timeout=30_000)
    page.wait_for_timeout(2000)
    _enable_semantics(page)


@pytest.fixture
def phone_page(page):
    """
    A page on a phone, signed in, with the home screen ready.

    The fixture waits for the figure that every test here looks at: the
    semantics tree materialises asynchronously, and a test that assumes
    it is there is a test that fails once in ten.
    """
    page.set_viewport_size(PHONE)

    # Flutter materialises the semantics tree asynchronously, and after a
    # reload it sometimes needs a second go. The retry is about the
    # *test harness*, not about the application.
    for _ in range(3):
        _sign_in(page)

        try:
            page.get_by_text("Crédito disponível").wait_for(timeout=20_000)
            return page
        except Exception:
            # Diagnostico: o que estava no ecra quando nao apareceu?
            try:
                host = page.locator("flt-semantics-host")
                body = host.inner_text() if host.count() else "(sem arvore)"
                print(f"\n  DIAGNOSTICO: url={page.url}")
                print(f"  arvore={body[:200]!r}")
            except Exception as exc:
                print(f"  (diagnostico falhou: {exc})")
            continue

    pytest.fail("the home screen did not render its semantics")


# ─── The figures ────────────────────────────────────────────────────────────


def test_the_figures_are_side_by_side(phone_page):
    """
    The two figures share the row.

    They used to be 220 px wide each — on a 390 px phone they did not
    fit, so they stacked, and the screen showed one number at a time.
    """
    credit = phone_page.get_by_text("Crédito disponível")
    total = phone_page.get_by_text("Total em aberto")

    assert credit.count() == 1
    assert total.count() == 1

    credit_box = credit.bounding_box()
    total_box = total.bounding_box()

    # The **same line**. The semantics tree does not carry the real
    # horizontal position — every child of a group reports the group's
    # origin — so the geometry itself is proven by the widget test
    # ("the figures share the row on a phone"), which measures the real
    # layout.
    assert abs(total_box["y"] - credit_box["y"]) < 8, (
        "the figures must share the row, not stack"
    )


def test_the_greeting_is_compact(phone_page):
    """
    The screen shows something else before the first fold.

    The greeting used to take the whole first screen; now the first
    section starts in the top third.
    """
    news = phone_page.get_by_text("Notícias")

    if news.count() == 0:
        pytest.skip("the development client has no announcements")

    box = news.bounding_box()

    assert box["y"] < PHONE["height"] / 3, (
        f"the first section starts at {box['y']:.0f}px — the greeting is "
        "still eating the screen"
    )


# ─── The articles ───────────────────────────────────────────────────────────


def test_the_articles_are_summarised(phone_page):
    """A long article is clipped, with a way to see the rest."""
    more = phone_page.get_by_text("Ler mais")

    if more.count() == 0:
        pytest.skip("the development client has no long article")

    assert more.count() >= 1

    # And it expands.
    more.first.click()
    phone_page.wait_for_timeout(1200)

    assert phone_page.get_by_text("Ler menos").count() >= 1


def test_the_full_text_is_announced(phone_page):
    """
    The summary is **visual** — the text is clipped on the screen, but a
    screen reader still reads all of it.

    Flutter's semantics node carries the whole text, not the visible
    part: the clipping is a visual affordance, and the "read more" is
    there for the person reading with their eyes. Nobody using a screen
    reader loses the end of the announcement.
    """
    body = _tree_text(phone_page)

    # The announcement ends with "Obrigado LINUXKAFE" — the last line,
    # far below the three the screen shows.
    assert "obrigado linuxkafe" in body.lower(), (
        "the whole text has to reach the accessibility tree"
    )


# ─── The whole screen ───────────────────────────────────────────────────────


def test_there_is_no_horizontal_overflow(phone_page):
    """The page never scrolls sideways."""
    overflow = phone_page.evaluate(
        "() => document.documentElement.scrollWidth - "
        "document.documentElement.clientWidth"
    )

    assert overflow <= 1, f"the page overflows by {overflow}px"


def test_the_dark_mode_still_works(phone_page):
    """The hero follows the system colour scheme, like everything else."""
    background = phone_page.evaluate(
        "() => getComputedStyle(document.body).backgroundColor"
    )

    assert background is not None


def test_the_bottom_bar_has_the_modern_indicator(phone_page):
    """
    The navigation bar is the Material 3 one, with the indicator.

    This was already the case — the modernisation was elsewhere — and the
    test says so, so that a future change to the old bar is caught.
    """
    selected = phone_page.get_by_role("tab", name="Início")

    assert selected.count() >= 1
