---
ticket: T042
phase: plan
status: done
created: 2026-10-22
tier: heavy
verdict: pass
requires:
  - aes/tickets/T042.md
produces:
  - aes/tickets/T042-plan.md
blocked_by: ''
---

# T042 — Plan: A configuração em secções, com os nomes reais

## Two-Agent Analysis (heavy tier)

### Critic Phase

```
## Como se fosse uma criança
A configuration page do WHMCS é como uma gaveta com etiquetas. Cada
gaveta tem as coisas de um assunto — as moedas numa, os servidores
noutra. O xkhm tinha uma caixa só, com todas as coisas lá dentro e o
nome técnico de cada uma ("LateFeeType"). Quem não soubesse o
WHMCS não sabia o que aquilo era.

## Como se fosse um especialista

[FAILURE MODE 1] — A secção de uma definição é uma **menta**, se não
for auditável.

Mecanismo: as páginas do WHMCS estão cifradas. A tentação é pôr as
definições em grupos "razoáveis" e chamar a isso as páginas do
WHMCS. O operador, que conhece o WHMCS, abre «Servidores» e não lá
está a definição dos servidores — e perde a confiança no painel
inteiro.

Mitigation: a secção vem **do ficheiro de idioma** quando ele a tem
(2 372 etiquetas, com a secção de origem). Quando não tem, a regra
é escrita à mão, por **prefixo do nome**, e vive numa constante
(`SECTION_PREFIXES`) que se lê em cinco segundos. A regra é
auditável — e o ficheiro de etiqueta guarda a origem.

[FAILURE MODE 2] — Uma secção que engole as restantes não é uma
separação.

Mecanismo: 235 definições, e a regra por prefixo pode deixar 175
numa secção. O painel passa a ter "uma lista com um título novo".

Mitigation: o portão (`test_the_sections_are_not_one_bucket`) exige
que a maior secção seja menos de 75 % do total, e que haja pelo
menos cinco secções. Hoje são 19, e a maior tem 76.

[FAILURE MODE 3] — O ficheiro de etiqueta é um **binário** no
contentor.

Mecanismo: o ficheiro gerado vive em `resources/`, e o Dockerfile
copiava `resources/` **só** no estágio de build. O contentor de
execução ficava sem ele, e as secções caíam todas em «Diversos» —
sem erro, sem aviso: apenas a configuração errada.

Mitigation: o ficheiro é copiado nos **dois** estágios, e o teste
`testTheLabelComesFromTheLanguageFile` falha se o ficheiro não lá
estiver. A lição ficou registada: um ficheiro de dados tem de
chegar ao contentor, e um teste tem de depender dele.

[FAILURE MODE 4] — O «diversos» é onde tudo acaba, e é um esconderijo.

Mecanismo: uma definição que não casa em nenhuma regra fica em
«Diversos», e ninguém a vê.

Mitigation: o painel mostra a contagem por secção, e o que fica em
«Diversos» está identificado — não escondido. A lista de 19
secções cobre 235 definições; o resto está visível e contável.

[ASSUMPTION]
Se falso: se as etiquetas do ficheiro de idioma não forem as
etiquetas que o WHMCS mostra na página da configuração, o painel
mostra o nome errado.

O ficheiro de idioma da administração **é** a fonte das etiquetas do
WHMCS — é assim que o produto funciona. O que não se pode ler são as
etiquetas escritas à mão nas páginas cifradas, e essas são ~60 de
235. Para essas, o painel mostra o **nome da definição** (em inglês,
como o WHMCS as tem), humanizado.

[ALTERNATIVE FRAMING]
Em vez de reconstruir as páginas do WHMCS, mostravam-se as
definições por **tema funcional** (facturação, clientes, correio) —
que é mais útil para quem não conhece o WHMCS.

Rejeitado: o pedido foi expresso («como o WHMCS»), e o operador
trabalha com o WHMCS ao lado. Um painel com nomes diferentes obriga
a traduzir de cabeça. E o rótulo de cada secção continua em
português, que é a lingua real da instalação.

## O que se não escolheu

- **Opção A**: escrever as secções à mão, uma por uma das 235
  definições. Rejeitada: é trabalho que apodrece, e o ficheiro de
  idioma já diz a secção de 2 372 etiquetas.
- **Opção B**: decryptar as páginas do WHMCS. Rejeitada: o código é
  ionCube e não há chave; e não é preciso — o idioma chega.
- **Opção C**: um árbol de secções livre. Rejeitada: o operador
  pediu as secções **do WHMCS**, não umas melhores.

## Riscos

- As secções do ficheiro de idioma são **páginas do admin**, não
  páginas da configuração. ABridge entre umas e outras é o alias
  (`SECTION_ALIASES`), escrito à mão e testado.
- O `resources/` tem de estar no contentor — e no **estágio de
  execução**, não só no de build.

## Custo de estar errado

**Médio.** O painel fica com secções que não são as do WHMCS, e o
operador não encontra o que procura. Não há perda de dados: a
escrita é a mesma, e a tabela não muda.

## Esqueleto do raciocínio

[As secções do painel são as páginas do WHMCS]
  + [as páginas do WHMCS estão cifradas: as etiquetas não se leem]
  + [o ficheiro de idioma traz a etiqueta e a secção de origem]
  → [extrai-se o ficheiro de idioma, e a secção vem dele]
  → [quando o ficheiro não diz, a regra por prefixo decide]
  → [a regra é auditável, e o portão proíbe a secção que engole as
     outras]
```

## A solução

1. `tools/extract_whmcs_labels.py` — lê o `admin/lang/portugues.php`
   do WHMCS original e gera `resources/whmcs_admin_labels.php` com
   as etiquetas, as secções e a **origem** de cada etiqueta.
2. `src/Service/SettingsLabels.php` — o serviço que dá a etiqueta
   real, a secção, e o nome da secção; com `SECTION_ALIASES` (as
   páginas do admin trazidas para as páginas da configuração) e
   `SECTION_PREFIXES` (a regra por prefixo).
3. `src/Service/SettingsService.php` — `grouped()` agrupa pela
   secção **normalizada**.
4. `src/Http/Controller/Admin/SettingsController.php` — a navegação
   das secções, e o nome real por cima da chave.
5. `public/templates/default/css/whmcs.css` — o estilo da navegação.

## O que fica de fora

- **A edição em massa** entre secções. O WHMCS faz isso; aqui cada
  secção edita as suas.
- **A pesquisa de definições** à parte. A secção é a navegação.
- **As etiquetas escritas à mão nas páginas cifradas** do WHMCS: não
  se podem ler, e não há como as reproduzir. O painel mostra o nome
  da definição para essas — e **diz** que o nome vem da definição.
