---
name: address-review
description: Atender los comentarios de revisión de un PR de Nexora Security — traer los hilos sin resolver, corregir cada hallazgo en un worktree aislado, verificar (php artisan test, pint, pytest), hacer commit a nombre del dueño de la rama, push, responder cada hilo con lo hecho y el SHA, y resolverlo. Los hallazgos con los que no se está de acuerdo reciben una respuesta explicando por qué y quedan sin resolver. Se usa con `/address-review <pr>` o desde `/pr-autopilot`.
metadata:
  short-description: Corregir los comentarios de revisión de un PR y resolver los hilos
---

# Address Review · Nexora

## Cuándo usarla

- `/address-review <número>`: el PR tiene comentarios de revisión (de Cami, de Santiago o de `/pr-review --comentar`) y hay que atenderlos.
- Desde `/pr-autopilot`.

Recibe exactamente un número de PR. Si no se da, preguntar.

Repo: `CamiloCalder0n/nexora-security`.

## Resultado garantizado

Cada hilo sin resolver termina en uno de estos estados:

1. **Corregido**: cambio hecho, commit, push; respuesta con el arreglo y el SHA; hilo **resuelto**.
2. **En desacuerdo**: el hallazgo está mal o no vale la pena; respuesta explicando por qué; hilo **sin resolver** (lo decide la otra persona). Nunca resolver un hilo que no se corrigió.
3. **Ya atendido**: un commit posterior ya lo arregló o el código ya no existe; respuesta con la evidencia; hilo **resuelto**.

Ningún hilo se salta en silencio y **nunca** se hace force-push.

## Fase 1 · Lista de trabajo

```bash
gh api graphql -f query='
query($owner:String!, $repo:String!, $pr:Int!) {
  repository(owner:$owner, name:$repo) {
    pullRequest(number:$pr) {
      headRefName
      baseRefName
      author { login }
      reviewThreads(first:100) {
        nodes {
          id isResolved isOutdated path line
          comments(first:20) { nodes { databaseId body author { login } url } }
        }
      }
    }
  }
}' -f owner=CamiloCalder0n -f repo=nexora-security -F pr=<número>
```

Quedarse con `isResolved: false`. Si no hay ninguno: "nada que atender" y parar.

Los comentarios de `/pr-review` vienen como `[severidad] [pasada] archivo:línea — hallazgo. Sugerencia: …`. Los de una persona pueden venir libres: interpretarlos igual.

## Fase 2 · Dueño y worktree aislado

**Regla de dueños (CLAUDE.md regla 4):** solo se corrige código del dueño de la sesión. Si el PR es de Cami y la sesión es de Santiago (o al revés), **no** se corrige su código: se responde el hilo con la sugerencia concreta y se deja para el dueño. Sigue siendo válido corregir archivos compartidos solo si el dueño lo autorizó.

No tocar el árbol de trabajo del usuario:

```bash
git fetch origin <headRefName>
git worktree add "$SCRATCHPAD/pr-<número>" <headRefName>
```

(Si ya existe uno viejo en esa ruta: `git worktree remove --force` y volver a crearlo.) Todo pasa dentro del worktree y se elimina al terminar, salga bien o mal.

## Fase 3 · Corregir

Agrupar por archivo. Leer el código alrededor antes de cambiar: el comentario describe el síntoma, hay que corregir la causa. Respetar:

- `docs/CONTRATO_API_AGENTE.md` (rutas, campos, códigos). Si el arreglo exige cambiar el contrato, **parar y avisar**: lo acuerdan Cami y Santiago.
- Capas Controller → Service → Repository → Model; un módulo no usa modelos de otro.
- Privacidad: sin biometría, video y evidencias solo locales, Telegram solo texto.
- Sin secretos ni archivos prohibidos (`.env`, `*.mp4`, `*.pt`, `*.db`, `evidencias/`).

Si el hallazgo está mal (leyó mal el código, contradice una regla de la guía o del contrato), no se cambia: queda **en desacuerdo** con la justificación.

## Fase 4 · Verificar

Dentro del worktree:

```bash
# si tocó backend/
cd backend && composer install -q && php artisan test && ./vendor/bin/pint --test
# (o con Docker desde la raíz del worktree: docker compose exec app php artisan test)
# si tocó agente/
cd agente && python3.12 -m venv .venv && .venv/bin/pip install -q -r requirements.txt && .venv/bin/pytest -q
```

Si algo falla por un arreglo, se repara el arreglo. Nunca se hace push en rojo.

## Fase 5 · Commit y push

- Autor = dueño de la rama, con su `git config` (Santiago Ruiz <sruiz557@unab.edu.co> o Cami). Comprobar con `git config user.name` antes del commit.
- Mensaje en Conventional Commits en español con la clave Jira de la rama: `fix(alertas): validar clasificación al cerrar alerta KAN-7`. Un commit por grupo lógico.
- **Sin rastro de IA**: ni `Co-Authored-By`, ni "Generated with…", ni emojis de robot, ni menciones a Claude/IA.
- `git push origin <headRefName>`. **Nunca force-push** (el PR ya es público y la otra persona puede tener la rama).
- Si hay otros PR que salen de esta rama (`gh pr list -R CamiloCalder0n/nexora-security --json baseRefName,headRefName`), no se rebasan solos: se listan como "necesita rebase".

## Fase 6 · Responder y resolver

Responder al primer comentario de cada hilo:

```bash
gh api repos/CamiloCalder0n/nexora-security/pulls/<número>/comments/<databaseId>/replies \
  -f body="Corregido en <sha-corto>: <una a tres frases de qué se cambió y por qué>"
```

Resolver los corregidos y los ya atendidos:

```bash
gh api graphql -f query='
mutation($threadId:ID!) {
  resolveReviewThread(input:{threadId:$threadId}) { thread { isResolved } }
}' -f threadId=<id del hilo>
```

Los que quedan en desacuerdo: responder `No lo cambio: <justificación>` y **dejar sin resolver**.

Las respuestas van en español, cortas, sin emojis ni menciones a IA.

## Salida

```text
ADDRESS_RESULT pr=<número> corregidos=<n> desacuerdo=<n> ya_atendidos=<n> push=<sha|ninguno>

| Hilo (archivo:línea) | Resultado | Commit |
| -------------------- | --------- | ------ |

Necesita rebase: <ramas o "ninguna">
```

La línea `ADDRESS_RESULT` la lee `/pr-autopilot`.
