---
ticket: T037
phase: plan
status: done
created: 2026-10-21
tier: heavy
verdict: pass
requires:
  - aes/tickets/T037.md
produces:
  - aes/tickets/T037-plan.md
blocked_by: ''
---

# T037 — Plan: MFA para dispositivos novos, sem alterar a base de dados

## Two-Agent Analysis (heavy tier)

### Critic Phase

```
## Como se fosse uma criança
A fechadura tem duas chaves: a minha e um codigo que o meu telefone
mostra. Se entro do mesmo telefone, so a minha. Se entro do
computador do trabalho, o telefone tem de dizer um numero.

E eu nao posso escrever nada na lista das fechaduras, porque essa
lista e da casa e nao se mexe.

## Como se fosse um especialista

[FAILURE MODE 1] — A restrição «sem alterar a base de dados» pode
ser lida como «sem usar a base de dados».

Mecanismo: guardar o segredo 2FA num **ficheiro**, como se fez com os
tokens do administrador. Funciona numa máquina, e falha quando há
duas: o cliente inscreve-se numa, muda de servidor, e perde o 2FA.

Mitigation: o segredo vai para `tblclients.authdata` — uma coluna que
**já existe** e que o WHMCS 6/7 usa exactamente para o 2FA. Não há
coluna nova nem tabela nova: a restrição é respeitada, e o dado fica
**com o cliente**, que é onde deve estar.

[FAILURE MODE 2] — A lista de dispositivos de confiança é o ponto
onde a restrição morde.

Mecanismo: a lista natural é uma tabela `tbltrusteddevices`. É
proibida. E a alternativa óbvia — um ficheiro por cliente — reintroduz
o problema da escala.

Mitigation: **nenhuma lista**. O servidor emite um **cookie
assinado** (HMAC) com o identificador do dispositivo e a validade. A
confiança é uma **prova**, não um registo: quem tem o cookie e a
assinatura certa é de confiança. Revogar é o cookie expirar.

[FAILURE MODE 3] — O segredo de assinatura.

Mecanismo: o `cc_encryption_hash` é o segredo **partilhado** com o
WHMCS. Usá-lo para assinar cookies mistura dois propósitos: uma
fuga de um expõe o outro.

Mitigation: uma **chave derivada** —
`hash_hmac('sha256', 'xkhm.device-trust', $ccEncryptionHash)`. O
mesmo segredo, um propósito diferente, e as chaves não se
confundem.

[FAILURE MODE 4] — O desafio do MFA tem de ser de vida curta.

Mecanismo: um desafio que dura um dia permite que alguém o intercepte
e o use mais tarde.

Mitigation: o desafio é um token **assinado** com cinco minutos de
validade, e carrega o cliente, o desafio e o instante. Sem
armazenamento, e sem reutilização.

[FAILURE MODE 5] — O `authdata` pode não ser nosso.

Mecanismo: o `authdata` é o campo de autenticação do WHMCS. Se uma
instalação o usar para outra coisa, a nossa inscrição **sobrescreve** o
dado dela — ou o nosso dado é sobrescrito pelaWHMCS.

Mitigation: o `authdata` guarda um **JSON com a nossa chave**
(`{"xkhm_mfa": {...}}`). Se o conteúdo não for nosso e não for
vazio, a inscrição **recusa** com uma mensagem clara, em vez de
apagar o que lá está. E a leitura tolera um `authdata` que não é
nosso (trata como «sem 2FA»), sem escrever nada.

[FAILURE MODE 6] — A app precisa de mostrar o QR, e um QR exige uma
dependência.

Mecanismo: o `otpauth://` não é legível por um humano. Sem QR, a
inscrição é «copie esta chave para a aplicação» — e metade dos
utilizadores desiste.

Mitigation: o `qr_flutter` (uma dependência, do `pub.dev`). E a chave
em texto, por baixo do QR, para quem usa outra aplicação.

[FAILURE MODE 7] — O segundo factor sem a app instalada.

Mecanismo: o MFA é opcional. Quem não se inscreve não é penalized.

Mitigation: a inscrição é **opt-in**. Sem inscrição, o login é o de
sempre. O MFA só aparece a quem o pediu.
```

### Implementor Phase

```
## Como se fosse uma criança
Faço uma chave nova, mostro-a num codigo de barras, a minha
aplicacao le e da-me um numero de 6 digitos de 30 em 30 segundos.
Eu escrevo o numero, e a porta abre. E o meu telefone fica de
confianca: ja nao pergunto mais.

## Como se fosse um especialista

(D1) O TOTP (RFC 6238).

```php
class TotpService
{
    public function generateSecret(): string;      // base32
    public function verify(string $secret, string $code, int $window = 1): bool;
}
```

Base32 (o formato do `otpauth://`) descodificado à mão — são vinte
linhas — e o HMAC-SHA1 do `hash_hmac` do PHP. A janela de ±1 passo
(±30 s) absorve o relógio.

(D2) A confiança do dispositivo: um cookie assinado.

```php
$payload = $deviceId . '|' . $expires;
$signature = hash_hmac('sha256', $payload, $this->key);
$cookie = $payload . '|' . $signature;
```

A verificação recalcula a assinatura e compara em **tempo
constante** (`hash_equals`). O `deviceId` vem do cliente; o
`expires` é o prazo de confiança.

(D3) O desafio do MFA: um token assinado, de cinco minutos.

```php
$payload = $clientId . '|' . $nonce . '|' . $expires;
```

(D4) O `authdata`: um JSON com a nossa chave.

```json
{"xkhm_mfa": {"secret": "...", "enabled": true, "created": "..."}}
```

A leitura devolve `null` se não for nosso — e **não escreve**.

(D5) A API.

```
POST /api/v1/auth/login  {email, password, deviceId}
  → sem 2FA, ou dispositivo de confiança → sessão
  → com 2FA e dispositivo novo → {mfaRequired: true, challenge: "..."}

POST /api/v1/auth/mfa   {challenge, code}
  → verifica o código → sessão + cookie de confiança

GET  /api/v1/client/security          → {enrolled: bool}
POST /api/v1/client/security/enrol    → {secret, uri}
POST /api/v1/client/security/confirm  {code} → {ok: true}
```

(D6) A app: o ecrã de Segurança, e o login que sabe pedir um código.
```

## Addressing FAILURE MODE 1 (o segredo)

Mitigation: `authdata` — a coluna que já existe e que o WHMCS 6/7 usa
para o 2FA.

## Addressing FAILURE MODE 2 (a lista de dispositivos)

Mitigation: **nenhuma lista**. A confiança é uma assinatura.

## Addressing FAILURE MODE 3 (o segredo de assinatura)

Mitigation: uma chave derivada, com um propósito próprio.

## Addressing FAILURE MODE 4 (a vida do desafio)

Mitigation: cinco minutos, assinado.

## Addressing FAILURE MODE 5 (o authdata de outra pessoa)

Mitigation: um JSON com a nossa chave; a inscrição recusa se o campo
não for nosso e não for vazio.

## Addressing FAILURE MODE 6 (o QR)

Mitigation: `qr_flutter`, e a chave em texto por baixo.

## Addressing FAILURE MODE 7 (quem não inscreve)

Mitigation: a inscrição é opt-in; sem ela, o login é o de sempre.

## [SOLUTION PROPOSAL]

Stage 1 — o TOTP e a assinatura.
Stage 2 — o serviço de MFA e os repositórios.
Stage 3 — a API.
Stage 4 — a app.
Stage 5 — os testes.

## Technical Approach

**Chosen:** o segredo do utilizador numa coluna que já existe; a
confiança do dispositivo provada por uma assinatura, sem lista; o
desafio assinado e curto.

**What will NOT be implemented:** 2FA por correio; a revogação de
dispositivos um a um; a administração do 2FA no painel; qualquer
alteração ao esquema.

### Affected files

| File | Operation | Description |
|------|-----------|-------------|
| `src/Service/TotpService.php` | create | O RFC 6238 |
| `src/Service/DeviceTrust.php` | create | O cookie assinado |
| `src/Service/MfaService.php` | create | A inscrição, o desafio, a verificação |
| `src/Repository/ClientRepositoryInterface.php` | modify | O `authdata` |
| `src/Http/Controller/Api/ApiV1Controller.php` | modify | Os pontos de entrada |
| `public/index.php` | modify | O wiring |
| `flutter/xkhm_app/lib/services/mfa.dart` | create | A API e o dispositivo |
| `flutter/xkhm_app/lib/screens/security_screen.dart` | create | O ecrã de segurança |
| `flutter/xkhm_app/lib/screens/login_screen.dart` | modify | O segundo factor |
| `flutter/xkhm_app/pubspec.yaml` | modify | O `qr_flutter` |
| `tests/Unit/TotpServiceTest.php` | create | O RFC 6238 |
| `tests/Playwright/test_mfa.py` | create | O fluxo |

## Testing Strategy

- O TOTP contra os vectores de teste do **RFC 6238** (aappendix
  oficial) — não contra a própria implementação.
- A assinatura: um cookie adulterado é recusado; um expirado é
  recusado.
- O fluxo: sem 2FA, login normal; com 2FA e dispositivo novo, o
  código é pedido; com o código certo, a sessão abre **e** o cookie
  é emitido; e o login seguinte já não o pede.

## Verification Criteria

- [ ] AC1 a AC9 evidenciados
- [ ] `make test`, `make lint`, `make e2e`, `make flutter-test` verdes

## Estimation

- Complexity: high (> 8h)
- Risk: high — é autenticação
- Blocking dependencies: none
