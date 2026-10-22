---
id: SD-CI-006
title: "Um ficheiro de dados tem de chegar ao contentor — nos dois estágios"
tier: ci
epistemic_state: verified
confidence: 0.95
date: 2026-10-22
source: T042
tags: [docker, build, data, config, whmcs, labels]
---

# Um ficheiro de dados tem de chegar ao contentor — nos dois estágios

## O que aconteceu

As etiquetas reais da configuração do WHMCS vêm de um ficheiro
gerado: `resources/whmcs_admin_labels.php` (2 372 etiquetas, 38
secções), extraído do `admin/lang/portugues.php` do WHMCS original.

O `Dockerfile` tinha:

```dockerfile
# estágio de build
COPY resources/ resources/

# estágio de execução
COPY --from=builder /build/vendor   /var/www/html/vendor
COPY --from=builder /build/src      /var/www/html/src
COPY --from=builder /build/public   /var/www/html/public
# … e o resources/ aqui não estava
```

O contentor corria, respondia **200**, e mostrava a configuração
**errada**: 175 de 235 definições em «Diversos», e o cabeçalho
«Tipo da Multa por Atraso» em vez de «LateFeeType». **Nenhum erro**,
**nenhum aviso**: o código estava certo e o **dado** faltava.

## A regra

> **Um ficheiro de dados gerado é parte do artefacto — e o artefacto
> tem de estar no contentor que serve. O estágio de build não é o
> estágio de execução.**

E o corollary que salva o próximo:

> **Um teste que dependa do ficheiro é o que impede a repetição.**
> `testTheLabelComesFromTheLanguageFile` falha se o ficheiro não lá
> estiver — e foi esse teste que tornou o defeito visível.

Sem o teste, a próxima pessoa a construir a imagem volta a copiar o
`resources/` só para o estágio de build, e o defeito regressa em
silêncio.

## A variante generalize

O mesmo se aplica a qualquer ficheiro gerado: tokens de design
(`assets/design_tokens.json`), o mapa das etiquetas, a lista de
palavras reservadas. A pergunta é sempre a mesma — **o contentor de
execução tem este ficheiro?** — e a resposta é sempre um `COPY` no
estágio certo, mais um teste que dependa dele.

## Ver

- `Dockerfile` — as duas cópias
- `tools/extract_whmcs_labels.py` — o gerador
- `tests/Integration/SettingsLabelsTest.php` — o teste que depende
