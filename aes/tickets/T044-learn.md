---
ticket: T044
phase: learn
status: done
created: 2026-10-22
requires:
  - aes/tickets/T044-verify.md
---

# T044 — Learn: O preçário de domínios e o estado da rede

## O que se aprendeu

### 1. Um zero pode vir de dois sítio diferentes — e ambos são o mesmo zero

O preço de um registo de domínio era 0 por duas razões que se
mascaravam: `tbldomainpricing` **não tem** a coluna `msetupprice`
(que a camada de compatibilidade sintetizava a 0), e o preço real
está em `tblpricing.msetupfee` — porque um registo é uma
**instalação**, e cobra-se a taxa de instalação.

**Aprendido:** um valor que não faz sentido é um sintoma, e a
primeira pergunta é «que coluna é esta?». A segunda é «o que é
esta coisa, na prática?» — um registo de domínio é um setup, e o
preço de um setup está na coluna do setup.

### 2. A comparação com o original é o porto seguro

O número correcto (18 / 18 / 35) não veio de raciocinar: veio de
**ver** o WHMCS vivo com o Playwright, e de comparar. Sem a
comparação, um `18` plausível seria aceito com a mesma confiança
que um `0`.

**Aprendido:** quando se reimplementa uma referência, a
referência é o **oráculo**. Um teste que diz «18» porque o
comparador disse 18 vale mais do que um teste que diz «18» porque
o código devolve 18.

### 3. «Estado do servidor» e «Estado da rede» são perguntas diferentes

A carga do sistema estar a 1.86 não diz nada sobre a intervenção
das 22:30. Misturar as duas coisas esconde o aviso mais importante
por trás de um número que parece tranquilo.

**Aprendido:** são duas páginas, com nomes que as distinguem. A
máquina agora e o que vem a seguir. E a página da rede abre com o
**estado actual** em letras grandes — «sem intervenções
comunicadas» ou «há N intervenções» — porque essa é a pergunta que
o cliente faz.

### 4. Um menu que não rola esconde as funcionalidades

O `NavigationRail` do Flutter não rola. Com quinze destinos, os
últimos ficam abaixo da dobra, e no ecrã largo não há gaveta para os
alcançar. O operador via um menu sem «Procurar Domínio» e sem
«Estado da Rede» e dizia — com razão — que não estavam disponíveis.

**Aprendido:** «não está disponível» é um sintoma de **layout**, não
de funcionalidade. A pergunta a fazer não é «a função existe?»
mas «o utilizador consegue chegar lá?». E a resposta honesta
obrigou a um teste: clicar no **último** destino, no ecrã largo.

### 5. O esquema não tem marca de «incidente de rede» — e isso não é licença para inventar

A `tblannouncements` do WHMCS 5.3.9 não distingue um aviso de
manutenção de um comunicado. A página mostra as **intervenções
comunicadas** — que é o que há — e não promete uma
classificação que a base de dados não suporta.

**Aprendido:** quando o esquema não tem a distinção, a interface
também não a inventa. Uma coluna nova está fora de questão (a
produção não muda), e mentir no ecrã também não.
