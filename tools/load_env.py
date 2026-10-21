#!/usr/bin/env python3
"""
Load the local `.env` into the environment.

The `.env` is **not versioned**: it holds the local credentials, which
have no business being in the repository. The repository holds the
*shape* of the configuration (`${XKHM_DB_PASSWORD:?...}`), never the
value.

Every tool in `tools/` loads the environment through here, so that the
credentials have exactly one home.

    from load_env import load_env
    load_env()          # does not override what is already set
"""

from __future__ import annotations

import os
import pathlib

ROOT = pathlib.Path(__file__).resolve().parent.parent
ENV_FILE = ROOT / ".env"


def parse_env(text: str) -> dict[str, str]:
    """
    Parse a `.env` file.

    Understands `KEY=value`, comments, blank lines, and values quoted
    with `"` or `'`.
    """
    values: dict[str, str] = {}

    for raw in text.splitlines():
        line = raw.strip()

        if not line or line.startswith("#"):
            continue

        if line.startswith("export "):
            line = line[len("export ") :].lstrip()

        if "=" not in line:
            continue

        key, _, value = line.partition("=")
        key = key.strip()

        if not key:
            continue

        value = value.strip()

        if len(value) >= 2 and value[0] == value[-1] and value[0] in "\"'":
            value = value[1:-1]

        values[key] = value

    return values


def load_env(path: pathlib.Path | None = None, override: bool = False) -> dict[str, str]:
    """
    Load the `.env` into `os.environ`.

    Variables already present in the environment win, unless `override`
    is set: the shell is closer to the truth than a file.
    """
    target = path or ENV_FILE

    if not target.is_file():
        return {}

    values = parse_env(target.read_text(encoding="utf-8"))

    for key, value in values.items():
        if override or key not in os.environ:
            os.environ[key] = value

    return values


if __name__ == "__main__":
    load_env()

    for key in sorted(os.environ):
        if key.startswith(("XKHM_", "APP_", "DB_")):
            value = os.environ[key]
            # Never print a password.
            if "PASS" in key or "SECRET" in key:
                value = "***"
            print(f"  {key}={value}")
