---
ticket: T048
phase: plan
status: done
created: 2026-10-22
tier: standard
verdict: pass
requires:
  - aes/tickets/T048.md
produces:
  - aes/tickets/T048-plan.md
blocked_by: ''
---

# T048 — Plan: O tipo de interligação do servidor

## Two-Agent Analysis (standard tier)

### Critic Phase

```
ASSUMPTIONS I'M MAKING:
- [KNOWN]    `tblservers.type` é o módulo — o que os dois servidores
             desta instalação confirmam (`virtualmin`, `whmcs_multisite`).
- [INFERRED] A lista dos módulos vem do `modules/servers/` do WHMCS
             original, como o WHMCS a lê. Verificado: a directoria tem
             37 módulos e os dois da instalação estão lá.
- [ASSUMED]  Os nomes dos módulos são legíveis — o que não é verdade:
             os módulos são cifrados. A identidade (o identificador) é
             o que importa, e o nome é um mapa escrito à mão.

WHAT WASN'T SPECIFIED:
- Se o tipo é obrigatório. A decisão: sim — sem ele, o servidor é um
  nome de máquina, e o aprovisionamento não existe.

ALTERNATIVES I DIDN'T CHOOSE:
- **Um campo de texto livre.** Rejeitado: o operador não lembra o
  identificador exacto (`whmcs_multisite`, não `multisite`). Um menu
  com os módulos do WHMCS é o que ele conhece.
- **Ler os módulos do disco da instalação.** Rejeitado: os módulos
  não existem no xkhm — o xkhm não aprovisiona, é só a interface.
  A lista do WHMCS original é o inventário.

RISKS & SIDE EFFECTS:
- O repositório **não gravava** o `type` — um servidor novo ficava
  sem tipo. Corrigido: a coluna passa a ser gravada.
- O `resources/` tem de estar no contentor — e já está, nos dois
  estágios (T042).

COST OF BEING WRONG: LOW — é um campo, e o teste apanha o erro.
```

## A solução

1. `tools/extract_server_modules.py` → `resources/whmcs_server_modules.php`
   (37 módulos, com o identificador e o nome).
2. `src/Service/ServerTypes.php` — a lista, e o nome de cada um.
3. `src/Http/Controller/Admin/ServerController.php` — o campo do tipo,
   com a explicação do que é, e a validação (obrigatório).
4. `CatalogueRepositoryPDO::saveServer` — a coluna `type` passa a ser
   gravada.
5. A lista dos servidores mostra o módulo.
