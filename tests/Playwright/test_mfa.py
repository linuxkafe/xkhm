"""
The second factor, and the trust of a device.

The rule under test: **a login from a new device asks for the code; a
login from a device that is already trusted does not.**

The TOTP here is implemented **independently** of the application's —
in Python, from the RFC — so the test is not the implementation
checking itself.
"""

from __future__ import annotations

import base64
import hashlib
import hmac
import os
import struct
import time

import pytest

from conftest import CLIENT_PASS, CLIENT_USER, stack_is_up

BASE_URL = os.getenv("XKHM_BASE_URL", "http://localhost:8083").rstrip("/")
APP_URL = os.getenv("XKHM_APP_URL", "http://localhost:8083/app/")


# ─── The TOTP, from the RFC 6238 ────────────────────────────────────────────


def _base32_decode(secret: str) -> bytes:
    padded = secret.upper().rstrip("=")
    padded += "=" * ((8 - len(padded) % 8) % 8)
    return base64.b32decode(padded)


def totp(secret: str, when: float | None = None) -> str:
    """The six-digit code of a secret, at a given instant."""
    counter = int((when if when is not None else time.time()) // 30)
    digest = hmac.new(
        _base32_decode(secret),
        struct.pack(">Q", counter),
        hashlib.sha1,
    ).digest()

    offset = digest[-1] & 0x0F
    value = struct.unpack(">I", digest[offset : offset + 4])[0] & 0x7FFFFFFF

    return f"{value % 1000000:06d}"


# ─── The fixtures ───────────────────────────────────────────────────────────


@pytest.fixture(autouse=True)
def _require_stack(stack_ready: bool):
    if not stack_ready:
        pytest.skip("xkhm stack not reachable — start it with `docker compose up -d`")


@pytest.fixture
def api(page):
    """An API client with a session, and the device under test."""
    return page.request


@pytest.fixture
def browser_context(page):
    """The browser context — where the cookies live."""
    return page.context


def _login(api, device_id: str) -> dict:
    response = api.post(
        "/api/v1/auth/login",
        data={
            "email": CLIENT_USER,
            "password": CLIENT_PASS,
            "deviceId": device_id,
        },
    )

    assert response.status == 200, response.text()

    return response.json()["data"]


def _disable_mfa(api) -> None:
    """Leave the account as it was found."""
    api.post("/api/v1/client/security/disable")


# ─── The API ─────────────────────────────────────────────────────────────────


def test_a_client_without_a_second_factor_logs_in_normally(api):
    """The second factor is opt-in: without it, the login is the usual one."""
    _login(api, "dispositivo-qualquer")
    _disable_mfa(api)

    data = _login(api, "dispositivo-qualquer")

    assert "mfaRequired" not in data
    assert data["client"]["email"] == CLIENT_USER


def test_the_enrolment_produces_a_secret_and_a_uri(api):
    _login(api, "dispositivo-qualquer")

    response = api.post("/api/v1/client/security/enrol")
    assert response.status == 200, response.text()

    data = response.json()["data"]

    assert data["secret"]
    assert data["uri"].startswith("otpauth://totp/")

    _disable_mfa(api)


def test_a_new_device_is_asked_for_the_code(api):
    """The whole point: a device the server does not know asks for the code."""
    _login(api, "dispositivo-qualquer")

    enrolled = api.post("/api/v1/client/security/enrol").json()["data"]
    secret = enrolled["secret"]

    # Confirm the enrolment with a real code.
    confirmed = api.post(
        "/api/v1/client/security/confirm", data={"code": totp(secret)}
    )
    assert confirmed.status == 200, confirmed.text()

    try:
        # A **new** device.
        data = _login(api, "dispositivo-novo")

        assert data.get("mfaRequired") is True
        assert data.get("challenge")

        # The wrong code is refused.
        refused = api.post(
            "/api/v1/auth/mfa",
            data={
                "challenge": data["challenge"],
                "code": "000000",
                "deviceId": "dispositivo-novo",
            },
        )
        assert refused.status == 401

        # The right code opens the session.
        accepted = api.post(
            "/api/v1/auth/mfa",
            data={
                "challenge": data["challenge"],
                "code": totp(secret),
                "deviceId": "dispositivo-novo",
            },
        )
        assert accepted.status == 200, accepted.text()
        assert accepted.json()["data"]["client"]["email"] == CLIENT_USER
    finally:
        _disable_mfa(api)


def test_a_known_device_is_not_asked_for_the_code(api, browser_context):
    """After the first code, the device is trusted."""
    _login(api, "dispositivo-qualquer")

    secret = api.post("/api/v1/client/security/enrol").json()["data"]["secret"]
    api.post("/api/v1/client/security/confirm", data={"code": totp(secret)})

    try:
        data = _login(api, "dispositivo-novo")
        challenge = data["challenge"]

        # The cookie the server issues: the device is now trusted.
        response = api.post(
            "/api/v1/auth/mfa",
            data={
                "challenge": challenge,
                "code": totp(secret),
                "deviceId": "dispositivo-novo",
            },
        )
        assert response.status == 200

        cookies = {cookie["name"]: cookie["value"] for cookie in browser_context.cookies()}
        assert "XKHM_TRUSTED_DEVICE" in cookies, (
            "the trust cookie has to be issued"
        )

        # The next login from the same device does not ask.
        again = api.post(
            "/api/v1/auth/login",
            data={
                "email": CLIENT_USER,
                "password": CLIENT_PASS,
                "deviceId": "dispositivo-novo",
            },
        )
        assert again.status == 200
        assert "mfaRequired" not in again.json()["data"]
    finally:
        _disable_mfa(api)


def test_a_tampered_trust_cookie_is_refused(api, browser_context):
    """A cookie that was edited is not a cookie."""
    _login(api, "dispositivo-qualquer")

    secret = api.post("/api/v1/client/security/enrol").json()["data"]["secret"]
    api.post("/api/v1/client/security/confirm", data={"code": totp(secret)})

    try:
        data = _login(api, "dispositivo-novo")

        api.post(
            "/api/v1/auth/mfa",
            data={
                "challenge": data["challenge"],
                "code": totp(secret),
                "deviceId": "dispositivo-novo",
            },
        )

        cookies = {
            cookie["name"]: cookie["value"]
            for cookie in browser_context.cookies()
        }
        original = cookies.get("XKHM_TRUSTED_DEVICE", "")
        tampered = original[:-1] + ("a" if original[-1] != "a" else "b")

        browser_context.add_cookies(
            [
                {
                    "name": "XKHM_TRUSTED_DEVICE",
                    "value": tampered,
                    "domain": "localhost",
                    "path": "/",
                }
            ]
        )

        again = api.post(
            "/api/v1/auth/login",
            data={
                "email": CLIENT_USER,
                "password": CLIENT_PASS,
                "deviceId": "dispositivo-novo",
            },
        )

        assert again.json()["data"].get("mfaRequired") is True
    finally:
        _disable_mfa(api)


def test_an_expired_challenge_is_refused(api):
    """A challenge that has been sitting around is not a challenge."""
    _login(api, "dispositivo-qualquer")

    secret = api.post("/api/v1/client/security/enrol").json()["data"]["secret"]
    api.post("/api/v1/client/security/confirm", data={"code": totp(secret)})

    try:
        data = _login(api, "dispositivo-novo")

        # A challenge signed with a different key is not a challenge.
        response = api.post(
            "/api/v1/auth/mfa",
            data={
                "challenge": "1|abcdef|9999999999.deadbeef",
                "code": totp(secret),
                "deviceId": "dispositivo-novo",
            },
        )

        assert response.status == 401
    finally:
        _disable_mfa(api)


# ─── The application ────────────────────────────────────────────────────────


def test_the_security_screen_exists(page):
    """The client can enrol from the application."""
    page.goto(f"{BASE_URL}/api/v1/auth/login")
    page.request.post(
        "/api/v1/auth/login",
        data={"email": CLIENT_USER, "password": CLIENT_PASS},
    )

    # A phone: on a wide screen the application shows a navigation rail,
    # and the drawer — where the security section is — does not exist.
    page.set_viewport_size({"width": 390, "height": 844})

    page.goto(APP_URL)
    page.wait_for_selector("flutter-view", timeout=30_000)

    # The semantics tree materialises asynchronously; it has to be asked
    # for, and it is asked for again after every reload.
    drawer = page.locator('[flt-semantics-identifier="nav-drawer"] [role="button"]')

    for _ in range(5):
        placeholder = page.locator("flt-semantics-placeholder")
        if placeholder.count() > 0:
            placeholder.first.dispatch_event("click")
            page.wait_for_timeout(1500)

        if drawer.count() > 0:
            break

        page.wait_for_timeout(800)

    assert drawer.count() > 0, "the semantics tree did not materialise"

    drawer.click()
    page.wait_for_timeout(1200)

    assert page.get_by_role("button", name="Segurança").count() >= 1
