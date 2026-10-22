---
ticket: T044
phase: plan
status: done
created: 2026-10-22
tier: heavy
verdict: pass
requires:
  - aes/tickets/T044.md
produces:
  - aes/tickets/T044-plan.md
blocked_by: ''
---

# T044 — Plan: O preçário de domínios e o estado da rede

## Two-Agent Analysis (heavy tier)

### Critic Phase

```
## Como se fosse uma criança
A loja diz quanto custa cada nome de domínio antes de se
procurar. E há um painel que diz "a máquina está bem" — e outro
que diz "esta noite não há serviço, porque vamos mexer na
máquina". São coisas diferentes.

## Como se fosse um especialista

[FAILURE MODE 1] — O preço de um registo de domínio está na coluna
errada.

Mecanismo: o `tblpricing` tem uma coluna por ciclo, e uma
`msetupfee` para a taxa de instalação. Um registo de domínio **é**
uma instalação: cobra-se a taxa. Ler `annually` dava 0, e ler
`msetupprice` numa tabela que não tem a coluna dava 0 também — o
mesmo zero, por caminhos diferentes.

Mitigation: o preço vem de `msetupfee`, e o teste compara com o
WHMCS vivo (18 / 18 / 35). A comparação externa é o porto seguro:
um número que ninguém viu de fora não é uma verdade.

[FAILURE MODE 2] — O «estado da rede» e o «estado do servidor» são
a mesma coisa, e não podem ser.

Mecanismo: o WHMCS tem duas páginas: `serverstatus.php` (a máquina
agora) e `networkissues.php` (o que vem). A segunda é a que diz
«hoje, das 22:30 às 06:00, há uma intervenção no datacenter» — e é
exactamente o aviso que está na tabela de anúncios desta
instalação. Se as duas forem misturadas, o cliente vê «carga do
sistema 1.86» e concludes que está tudo bem, na véspera da
intervenção.

Mitigation: são duas páginas, com nomes diferentes e conteúdos
diferentes. A de intervenção mostra o **estado actual** («sem
intervenções comunicadas» ou «há N intervenções») e a lista.

[FAILURE MODE 3] — As entradas do menu que ficam abaixo da dobra
ficam **inalcançáveis**.

Mecanismo: a `NavigationRail` do Flutter **não rola**. Com quinze
destinos e um ecrã de 900 px de altura, os últimos ficam fora do
ecrã — e, no ecrã largo, **não há gaveta** para os alcançar. O
cliente via um menu sem «Estado da Rede» e sem «Procurar Domínio»,
e dizia que as funcionalidades «não estão disponíveis».

Mitigation: `scrollable: true` na barra. E o teste do ecrã largo
(clicar no último destino) é o que apanha a regressão.

[ASSUMPTION]
Se falso: se as intervenções da rede não forem os anúncios, a página
mostra coisa errada.

O esquema **não tem** uma marca de «incidente de rede» — a
`tblannouncements` do WHMCS 5.3.9 só tem `published` e `parentid`.
O aviso de manutenção da instalação («Intervenção entre as 22:30 e
as 06:00») está lá, e é o que o WHMCS mostra na página da rede. A
página diz que mostra as **intervenções comunicadas** — que é o que
é, e não promete mais.

[ALTERNATIVE FRAMING]
Em vez de uma página nova, meter as intervenções na página do estado
do servidor.

Rejeitado: mistura duas perguntas, e o aviso fica enterrado num
ecrã de números de sistema. O WHMCS separa-as, e o aviso é
importante demais para estar enterrado.
```

## A solução

1. `DomainRepositoryPDO::getTLDPrices()` — o `JOIN` com `tblpricing`
   por `type`, e o preço em `msetupfee`.
2. `DomainService::getTLDPriceTable()` — a tabela, no formato do
   WHMCS.
3. `GET /api/v1/domains/pricing` — devolve `tld`, `register`,
   `transfer`, `renew`, `minYears`.
4. `domain_checker_screen.dart` — o preçário, no fundo da página
   (como o WHMCS), com o registar / transferir / renovar.
5. `network_status_screen.dart` — a página nova, e a entrada no
   menu.
6. `home_shell.dart` — `scrollable: true` na barra.

## O que fica de fora

- **A marcação de incidente de rede** — exigiria uma coluna nova,
  e a produção não pode ser alterada.
- **A pesquisa em lote** de domínios (`domainchecker` faz isso no
  WHMCS, com CAPTCHA). O ecrã aceita um domínio de cada vez.
- **A compra** de um domínio a partir do preçário. O carrinho é
  outro trabalho.
