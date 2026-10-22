#!/usr/bin/env python3
"""
Extract the **real names** of the settings from the WHMCS.

The WHMCS shows the configuration with the labels of its own admin
language file. Most of the labels of the *settings* are hardcoded in the
encrypted configuration pages — in English — and a handful come from
`admin/lang/<language>.php`.

This tool extracts what **can** be extracted, from the original
installation:

    python3 tools/extract_whmcs_labels.py

It writes `resources/whmcs_admin_labels.php` — a data file with the
section names and the labels of the settings that the language file
covers.

The labels that the language file does **not** cover are humanised from
the setting's own name, which is what the WHMCS's own page shows
(`LateFeeType` → «Late Fee Type»).
"""

from __future__ import annotations

import pathlib
import re
import sys

#: Where the original WHMCS is.
WHMCS = pathlib.Path("/home/seyon/dev/whmcs-original")

#: The language file of the administration, in the language of the
#: installation.
LANGUAGE = WHMCS / "admin" / "lang" / "portugues.php"

#: The configuration pages of the WHMCS — the sections of the panel.
#
#: The list is the WHMCS's own: `admin/config<name>.php`.
SECTIONS = [
    ("general", "Geral"),
    ("servers", "Servidores"),
    ("currencies", "Moedas"),
    ("products", "Produtos"),
    ("productoptions", "Opções de produto"),
    ("domains", "Domínios"),
    ("orders", "Pedidos"),
    ("orderstatuses", "Estados de pedido"),
    ("invoices", "Faturas"),
    ("payments", "Pagamentos"),
    ("gateways", "Portas de pagamento"),
    ("promotions", "Promoções"),
    ("affiliates", "Afiliados"),
    ("clients", "Clientes"),
    ("clientgroups", "Grupos de clientes"),
    ("clientsummary", "Resumo do cliente"),
    ("tickets", "Tickets"),
    ("ticketdepartments", "Departamentos de tickets"),
    ("ticketescalations", "Escalamentos de tickets"),
    ("ticketstatuses", "Estados de ticket"),
    ("ticketspamcontrol", "Controlo de spam nos tickets"),
    ("twofa", "Verificação em dois passos"),
    ("securityqs", "Perguntas de segurança"),
    ("emailtemplates", "Modelos de e-mail"),
    ("fraud", "Fraude"),
    ("tax", "Impostos"),
    ("auto", "Automação"),
    ("addons", "Módulos adicionais"),
    ("addonmods", "Módulos dos addons"),
    ("customfields", "Campos personalizados"),
    ("adminroles", "Funções de administrador"),
    ("admins", "Administradores"),
    ("backups", "Cópias de segurança"),
    ("bannedemails", "Emails banidos"),
    ("bannedips", "IPs banidos"),
    ("bundles", "Pacotes"),
    ("registrars", "Registradores"),
    ("miscellaneous", "Diversos"),
]

#: `$_ADMINLANG['section']['key'] = "label";`
LABEL = re.compile(
    r"\$_ADMINLANG\['([a-z0-9_]+)'\]\['([a-z0-9_]+)'\]\s*=\s*\"(.*?)\";"
)


def main() -> int:
    if not LANGUAGE.is_file():
        print(f"  [FALHA] não encontrei {LANGUAGE}")
        return 1

    text = LANGUAGE.read_text(encoding="utf-8", errors="replace")

    # As etiquetas, por secção.
    by_section: dict[str, dict[str, str]] = {}
    for section, key, value in LABEL.findall(text):
        by_section.setdefault(section, {})[key] = value.replace('\\"', '"')

    # As etiquetas que são o nome de uma definição: a chave é o nome
    # da definição, em minúsculas.
    settings = {
        key: value
        for section, keys in by_section.items()
        for key, value in keys.items()
        if len(key) > 3 and key.islower()
    }

    # Só as que servem para uma definição: as chaves com maiúsculas no
    # meio (as das páginas) não servem.
    names = {
        key: value
        for key, value in settings.items()
        if not any(character.isupper() for character in key)
    }

    # A seccao de cada etiqueta: e a pagina de configuracao do WHMCS
    # onde a definicao vive.
    label_sections = {}
    for section, keys in by_section.items():
        for key in keys:
            if key in names:
                label_sections[key] = section

    target = pathlib.Path(__file__).resolve().parent.parent / "resources"
    target.mkdir(parents=True, exist_ok=True)

    lines = [
        "<?php",
        "",
        "declare(strict_types=1);",
        "",
        "/**",
        " * The real names of the WHMCS's settings, and its configuration sections.",
        " *",
        " * GENERATED FILE — do not edit by hand.",
        " * Run `python3 tools/extract_whmcs_labels.py` to regenerate it.",
        " *",
        " * The labels come from the original installation's",
        " * `admin/lang/portugues.php`; the sections are the WHMCS's own",
        " * configuration pages.",
        " */",
        "",
        "return [",
        "    // The sections of the configuration, and their names.",
        "    'sections' => [",
    ]

    for key, label in SECTIONS:
        lines.append(f"        '{key}' => '{label}',")

    lines.append("    ],")
    lines.append("")
    lines.append("    // The labels of the settings that the language file covers.")
    lines.append("    'labels' => [")

    for key in sorted(names):
        value = names[key].replace("\\", "\\\\").replace("'", "\\'")
        lines.append(f"        '{key}' => '{value}',")

    lines.append("    ],")
    lines.append("")
    lines.append("    // The section of each label: the configuration page it lives in.")
    lines.append("    'label_sections' => [")
    for key in sorted(label_sections):
        lines.append(f"        '{key}' => '{label_sections[key]}',")
    lines.append("    ],")
    lines.append("];")

    (target / "whmcs_admin_labels.php").write_text("\n".join(lines) + "\n", encoding="utf-8")

    print()
    print("  Nomes reais das definições")
    print("  " + "=" * 62)
    print(f"  {len(SECTIONS)} secções (as páginas de configuração do WHMCS)")
    print(f"  {len(names)} etiquetas extraídas de admin/lang/portugues.php")
    print(f"  → resources/whmcs_admin_labels.php")
    print()

    return 0


if __name__ == "__main__":
    sys.exit(main())
