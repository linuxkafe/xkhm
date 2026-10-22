---
ticket: T042
phase: learn
status: done
created: 2026-10-22
requires:
  - aes/tickets/T042-verify.md
---

# T042 — Learn: A configuração em secções, com os nomes reais

## O que se aprendeu

### 1. O ficheiro de idioma do WHMCS é a fonte das etiquetas — e das secções

As páginas de configuração do WHMCS estão cifradas, e as suas
etiquetas não se podem ler. Mas `admin/lang/portugues.php` tem 2 860
etiquetas em texto claro, **organizadas por secção** — e a secção de
cada etiqueta é, na maioria dos casos, a página onde a definição
vive.

**Aprendido:** quando um produto esconde os seus textos, o ficheiro
de idioma é o lugar onde os textos se recuperam — e traz a
*estrutura* de graça, não só o conteúdo.

### 2. As secções do idioma são páginas do **admin**, não da **configuração**

`$_ADMINLANG['fields']['companyname']` está em «fields» — e «fields»
não é uma página da configuração: são os **dados da empresa**, que
vivem na página geral. A ponte entre umas e outras é o alias.

**Aprendido:** os dois vocabulários coexistem e precisam de uma
tabela de correspondência. Sem ela, a mesma página aparece duas
vezes («Geral» e «Global», «Modelos de e-mail» e «Emailtpls») — e o
operador vê a configuração partida ao meio.

### 3. O ficheiro de dados tem de chegar ao contentor — nos **dois** estágios

O `resources/` era copiado no estágio de *build* do Dockerfile e não
no de *execução*. O contentor corria, respondia 200, e mostrava a
configuração **errada** — 175 de 235 definições em «Diversos»,
porque o ficheiro das etiquetas não lá estava. Nenhum teste falhou:
o código estava certo e o dado faltava.

**Aprendido:** um ficheiro de dados gerado é parte do artefacto, e
tem de estar no contentor que serve. Um teste que dependa dele
(`testTheLabelComesFromTheLanguageFile`) é o que impede a repetição.

### 4. A intuição sobre a secção é menos fiável do que o ficheiro

A intuição punha `LateFeeType` em «Impostos» e `CompanyName` em
«Geral». O ficheiro de idioma põe `LateFeeType` em «Geral» — e tem
razão: no WHMCS, a multa por atraso está na página geral, e o IVA é
que está na página de impostos.

**Aprendido:** quando há uma fonte que sabe, preferi-la à
intuição. A regra escrita à mão é o **último** recurso, para o que
a fonte não cobre.

### 5. Um teste que fixa um **retrato** dos dados envelhece

O teste do relatório de_definições em falta afirmava «o NIF não
existe na instalação». O NIF passou a existir (veio nos dados de
produção), e o teste passou a falhar — sem que nada do código
tivesse mudado.

**Aprendido:** um teste que diz «o valor é X» envelhece quando os
dados mudam; um teste que diz «**quando** o valor é X, então Y»
envelhece com elegância. A regra testada foi trocada pela
fotografia.

## O que fica para o futuro

- As **etiquetas escritas à mão** nas páginas cifradas do WHMCS
  (~60 de 235) continuam a mostrar o nome da definição. Só o código
  do WHMCS as tem.
- A secção «Diversos» tem 33 definições. Cada uma que se mova
  para uma página concreta é um acto deliberado — não um efeito
  secundário.
