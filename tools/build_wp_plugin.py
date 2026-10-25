#!/usr/bin/env python3
"""Assemble a self-contained WordPress plugin under build/wp-plugin/ (or --out).

Copies the xkhm core (src/, public/, resources/) and vendor/ into the plugin
skeleton so the plugin can be dropped into any WordPress installation without
external dependencies.
"""
import argparse
import os
import shutil
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
PLUGIN_SRC = ROOT / "wordpress-plugin"
DEFAULT_OUT = ROOT / "build" / "wp-plugin"

def copytree(src: Path, dst: Path, ignore=None):
    if not src.exists():
        return
    if dst.exists():
        shutil.rmtree(dst)
    shutil.copytree(src, dst, ignore=ignore)

def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--out", default=str(DEFAULT_OUT))
    ap.add_argument("--flutter-build", default=str(ROOT / "flutter" / "xkhm_app" / "build" / "web"))
    args = ap.parse_args()

    out = Path(args.out).resolve()
    if out.exists():
        shutil.rmtree(out)
    out.mkdir(parents=True, exist_ok=True)

    # 1. skeleton
    for item in PLUGIN_SRC.iterdir():
        if item.name in ("src", "public", "resources", "vendor", "storage"):
            continue
        if item.is_dir():
            shutil.copytree(item, out / item.name)
        else:
            shutil.copy2(item, out / item.name)

    # 2. core
    copytree(ROOT / "src", out / "src")
    copytree(ROOT / "public", out / "public")
    copytree(ROOT / "resources", out / "resources")
    # configuration.php (necessário para Config)
    if (ROOT / "configuration.php").exists():
        shutil.copy2(ROOT / "configuration.php", out / "configuration.php")

    # 3. vendor (if present)
    vendor = ROOT / "vendor"
    if vendor.exists():
        copytree(vendor, out / "vendor")

    # 4. storage skeleton (no data)
    (out / "storage").mkdir(exist_ok=True)
    (out / "storage" / "reset-tokens").mkdir(exist_ok=True)
    (out / "storage" / "reset-tokens" / "pending").mkdir(exist_ok=True)
    (out / "storage" / "reset-tokens" / "failed").mkdir(exist_ok=True)
    (out / "storage" / "notice-pdfs").mkdir(exist_ok=True)

    # 5. flutter web build (if present)
    fb = Path(args.flutter_build)
    if fb.exists():
        dest = out / "public" / "app"
        if dest.exists():
            shutil.rmtree(dest)
        shutil.copytree(fb, dest)

    print(f"Self-contained plugin assembled at: {out}")
    print("Size:")
    os.system(f"du -sh '{out}'")

if __name__ == "__main__":
    main()
