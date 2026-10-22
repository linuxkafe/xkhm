#!/usr/bin/env python3
"""
The server modules — the plugins that provision a server.

The `tblservers.type` column holds the **module**: the plugin that
creates the account on the machine, configures the hosting, and
renews it. Without it, a server is just a hostname.

The list comes from the original installation's `modules/servers/`
directory — the same place the WHMCS's own `configservers` page
reads it from. The *identifiers* are the directory names (which is
what the database holds); the *names* are a hand-written map, because
the modules themselves are encrypted and cannot be read.

Run:

    python3 tools/extract_server_modules.py
"""

from __future__ import annotations

import pathlib
import sys

#: Where the original WHMCS is.
WHMCS = pathlib.Path("/home/seyon/dev/whmcs-original")

#: The directory the WHMCS reads its server modules from.
MODULES = WHMCS / "modules" / "servers"

#: The name of the modules, for the ones that are well known.
#:
#: The rest keep the identifier as their name — and that is honest:
#: the module is what it is.
NAMES = {
    "cpanel": "cPanel",
    "cpanel_aws": "cPanel para AWS",
    "centovacast": "CentovaCast",
    "cloudmin": "Cloudmin",
    "directadmin": "DirectAdmin",
    "enkompass": "Enkompass",
    "enomssl": "eNom SSL",
    "enomtruste": "eNom Trust",
    "ensimx": "eSim-X",
    "fluidvm": "FluidVM",
    "gamecp": "GameCP",
    "helm": "Helm",
    "helm4": "Helm 4",
    "hostbill": "HostBill",
    "hypervm": "HyperVM",
    "interworx": "InterWorx",
    "licensing": "Licensing",
    "lxadmin": "LXAdmin",
    "mediacp": "MediaCP",
    "plesk": "Plesk",
    "plesk8": "Plesk 8",
    "plesk9": "Plesk 9",
    "plesk10": "Plesk 10",
    "pleskreseller": "Plesk Reseller",
    "resellercentral": "ResellerCentral",
    "scpanel": "scPanel",
    "tcadmin": "TCAdmin",
    "veportal": "VE Portal",
    "virtualmin": "Virtualmin",
    "vpsnet": "VPSNet",
    "websitepanel": "WebsitePanel",
    "whmcs_multisite": "WHMCS MultiSite",
    "whmsonic": "WHMsonic",
    "xpanel": "xPanel",
    "autorelease": "Auto Release",
    "heartinternet": "Heart Internet",
    "globalsignssl": "GlobalSign SSL",
    "globalsignvouchers": "GlobalSign Vouchers",
    "resellerclubssl": "ResellerClub SSL",
}


def modules() -> list[str]:
    """The identifiers of the server modules."""
    if not MODULES.is_dir():
        return []

    return sorted(
        path.name
        for path in MODULES.iterdir()
        if path.is_dir() and not path.name.startswith(".")
    )


def main() -> int:
    found = modules()

    if not found:
        print(f"  [ERRO] {MODULES} nao existe — o WHMCS original nao esta la")
        return 1

    target = pathlib.Path(__file__).resolve().parent.parent / "resources" / "whmcs_server_modules.php"
    target.parent.mkdir(parents=True, exist_ok=True)

    lines = [
        "<?php",
        "",
        "declare(strict_types=1);",
        "",
        "/**",
        " * The server modules — the plugins that provision a server.",
        " *",
        " * GENERATED FILE — do not edit by hand.",
        " * Run `python3 tools/extract_server_modules.py` to regenerate it.",
        " *",
        " * The identifiers come from the original installation's",
        " * `modules/servers/` directory; the names are a hand-written map,",
        " * because the modules themselves are encrypted.",
        " */",
        "",
        "return [",
        "    // O identificador => o nome.",
        "    'modules' => [",
    ]

    for name in found:
        lines.append(f"        '{name}' => '{NAMES.get(name, name)}',")

    lines.append("    ],")
    lines.append("];")
    lines.append("")

    target.write_text("\n".join(lines))

    print(f"  {len(found)} modulos de servidor -> {target.name}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
