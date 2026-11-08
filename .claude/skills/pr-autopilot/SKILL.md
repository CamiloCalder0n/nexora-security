---
name: pr-autopilot
description: Llevar una rama de Nexora Security desde los commits locales hasta un PR abierto y listo para que el compañero lo apruebe — verificación, dos rondas de /pr-review con arreglos integrados en el commit de cada paso (fixup + autosquash, antes del push), push y apertura del PR sin rastro de IA. Úsala con `/pr-autopilot` en la rama actual o `/pr-autopilot <pr>` para un PR ya abierto que tiene comentarios.
metadata:
  short-description: Rama → dos revisiones → push → PR
---

# PR Autopilot · Nexora

## Cuándo usarla

- `/pr-autopilot` en una rama con commits locales que todavía **no** se ha publicado. Es el flujo normal de Santiago.
- `/pr-autopilot <número>` en un PR ya abierto: corre `/address-review <número>` y luego `/pr-review <número>` hasta que no queden hilos ni hallazgos (máximo 3 rondas), sin force-push.

Nunca hace merge, nunca crea tags (`v0.1`…`v1.0` los pone el equipo a mano) y nunca toca `main` directo.

## Flujo para una rama local

### 1 · Comprobaciones previas

```bash
git status --short                      # árbol limpio
git rev-parse --abbrev-ref HEAD         # no es main
git fetch origin main
git log origin/main..HEAD --oneline     # hay commits propios
git config user.name; git config user.email
git ls-remote --heads origin "$(git rev-parse --abbrev-ref HEAD)"   # vacío = la rama aún no está publicada
```

Si la rama ya está publicada, este flujo **no** reescribe historia: los arreglos van en commits nuevos (`fix(...)`/`refactor(...)` con la clave Jira).

Si `main` avanzó, `git rebase origin/main` (permitido porque la rama aún es local) y volver a verificar.

### 2 · Ronda 1 de revisión

Correr `/pr-review` (modo local). Por cada hallazgo bloqueante o importante (y los menores que tengan sentido):

1. Corregir.
2. Integrarlo **en el commit del paso al que pertenece**, no en un commit aparte:
   ```bash
   git add <archivos>
   git commit --fixup=<sha del commit del paso>
   ```
3. Al terminar la ronda:
   ```bash
   GIT_SEQUENCE_EDITOR=: git rebase -i --autosquash origin/main
   ```
4. Volver a correr las verificaciones (`php artisan test`, `pint --test`, `pytest`).

### 3 · Ronda 2 de revisión

Correr `/pr-review` otra vez sobre el resultado, mirando sobre todo lo que la ronda 1 pudo romper o no vio. Mismo procedimiento de fixup. Si la ronda 2 encuentra bloqueantes o importantes, se corrigen y se hace **otra** ronda; el criterio es **dos rondas seguidas con `veredicto=listo`**, la última sin cambios. Máximo 4 rondas; si no converge, parar y avisar al dueño con la lista pendiente.

### 4 · Revisión final de Git

```bash
git log origin/main..HEAD --format='%h %an <%ae> | %s%n%b'
```

- Todos los commits con el autor del dueño de la rama.
- Conventional Commits en español con clave Jira, claros sobre qué se hizo.
- Sin `Co-Authored-By`, "Generated with…", emojis de robot ni mención a IA.

### 5 · Push y PR

```bash
git push -u origin <rama>
gh pr create -R CamiloCalder0n/nexora-security --base main --head <rama> \
  --title "<título corto, como pide la guía>" --body-file <archivo>
```

Cuerpo del PR (en español, corto, sin rastro de IA):

```markdown
## Qué hace
- ...

## HU / Jira
- KAN-xx (HUyy): ...

## Cómo probarlo
```bash
...
```

## Toca archivos compartidos
- ninguno | `docker-compose.yml`: ... (avisado a Cami)

## Pendiente
- ...
```

Pedir revisión al compañero: `gh pr edit <n> -R CamiloCalder0n/nexora-security --add-reviewer CamiloCalder0n` (o `santiagoRuiz1314` si el PR es de Cami).

## Salida

```text
AUTOPILOT_RESULT rama=<rama> rondas=<n> pr=<url|ninguno> estado=<abierto|bloqueado>

| Ronda | Hallazgos | Corregidos | Veredicto |
| ----- | --------- | ---------- | --------- |
```
