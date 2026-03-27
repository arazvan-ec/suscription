# Workflow Completo: BMAD + ECC + OpenSpec

## Que es esto

Un sistema unificado que combina lo mejor de cada herramienta del ecosistema,
validado con instalacion real y cero conflictos entre sistemas.

    Proyecto nuevo  →  BMAD fases 1-3  →  ECC ejecucion + QA + review + learning
    Feature brownfield  →  OpenSpec  →  ECC ejecucion + QA + review + learning
    Bug / cambio menor  →  ECC directo (o bmad-quick-dev)

Tres sistemas nativos + extensiones custom:

| Componente | Que hace | Tipo |
|------------|----------|------|
| BMAD v6 (43 skills) | Planificacion profunda: research, PRD, arquitectura, stories | Nativo |
| ECC (28 agents, 125 skills) | Ejecucion, QA, security, code review, continuous learning | Nativo (plugin) |
| OpenSpec (4 skills) | Spec-driven para features en proyectos existentes | Nativo (CLI) |
| 4 skills Symfony | Patrones de API, Messenger, Doctrine, CDN | Custom |
| 3 skills puente | Conecta OpenSpec con ECC, gate checks, research | Custom |
| 5 scripts orquestacion | Fresh contexts, worktrees, paralelo, QA loop, CI reactor | Custom |

Validado: los tres sistemas coexisten sin conflictos. BMAD usa prefijo bmad-*,
OpenSpec usa openspec-*, ECC usa namespace de plugin. Hooks no se pisan.

---

## Como usar esta guia

Esta guia se carga automaticamente via CLAUDE.md en la raiz del proyecto.
Los skills de BMAD tienen flujo interactivo con menus — dejalos hacer preguntas,
no los fuerces a generar todo de golpe.

Requisitos antes de usar los flujos:
1. BMAD instalado (npx bmad-method install)
2. Scripts ejecutables (chmod +x .claude/scripts/*.sh)
3. project-context.md generado (bmad-generate-project-context)

ECC y OpenSpec son opcionales — los flujos B y C requieren su instalacion.

---

## Flujo A: Proyecto nuevo (BMAD completo)

Usa este flujo cuando creas un microservicio nuevo o una feature compleja
que requiere investigacion, arquitectura, y planificacion formal.

### 1. Analisis

    bmad-analyst

Escribir `BP` (Brainstorm Project). Describir la idea.
Mary investiga, hace preguntas, produce brainstorming-report.md.

Luego `CB` (Create Brief). Produce product-brief.md.

Para investigacion tecnica profunda:

    deep-research "como implementar sistema de notificaciones push
    para seguidores de periodistas en arquitectura de microservicios"

### 2. Planning

    bmad-pm

Escribir `CP` (Create PRD). John entrevista sobre requirements.
Produce PRD.md con FRs y NFRs.

Si hay UI:

    bmad-ux-designer

Escribir `CU` (Create UX Design).

### 3. Solutioning

    bmad-architect

Escribir `CA` (Create Architecture). Winston lee el PRD y project-context,
propone architecture.md con ADRs.

Gate check:

    bmad-check-implementation-readiness

Resultado: PASS / CONCERNS / FAIL. Si FAIL, iterar antes de seguir.

### 4. Crear stories

    bmad-pm

Escribir `EP` (Create Epics and Stories).

    bmad-sm

Escribir `SP` (Sprint Planning) y `CS` (Create Story).

    git add -A && git commit -m "plan: [feature] - PRD, arquitectura, stories"

### 5. Implementar con fresh context

Por cada story, en serie:

    ./.claude/scripts/fresh-exec.sh \
      _bmad-output/implementation-artifacts/story-nombre.md

O con QA loop (implement + evaluate + fix):

    ./.claude/scripts/qa-loop.sh \
      _bmad-output/implementation-artifacts/story-nombre.md 3

Para stories independientes, en paralelo:

    ./.claude/scripts/parallel-tasks.sh \
      story-a.md story-b.md story-c.md

### 6. Code review

BMAD v6 ya incluye review adversarial multi-capa:

    bmad-code-review

Usa tres capas en paralelo: Blind Hunter, Edge Case Hunter, Acceptance Auditor.

Para review adversarial adicional:

    bmad-review-adversarial-general

Para edge cases exhaustivo:

    bmad-review-edge-case-hunter

ECC tambien tiene su propio code-reviewer y security-reviewer como agents.

### 7. Security scan (ECC)

    npx ecc-agentshield scan

### 8. Siguiente story

    bmad-sm

Escribir `CS` (Create Story). Repetir desde paso 5.

---

## Flujo B: Feature en proyecto existente (OpenSpec)

Usa este flujo cuando anades una feature a un microservicio existente.
Mas ligero que el flujo A, con delta markers para brownfield.

### 1. Proponer el cambio

    /opsx:propose "Anadir endpoint GET /followers/:journalistId
    que devuelva la lista de seguidores con paginacion"

OpenSpec crea:
- openspec/changes/[feature]/proposal.md (por que)
- openspec/changes/[feature]/specs/ (que cambia, con ADDED/MODIFIED/REMOVED)
- openspec/changes/[feature]/design.md (como)
- openspec/changes/[feature]/tasks.md (checklist de implementacion)

Para features complejas, investigar antes:

    deep-research "patrones de paginacion en APIs REST con Doctrine"

### 2. Gate check

    readiness-gate

Verifica que los specs estan completos antes de implementar.
Resultado: PASS / CONCERNS / FAIL.

### 3. Implementar

Con QA loop:

    ./.claude/scripts/qa-loop.sh \
      openspec/changes/[feature]/tasks.md 3

O directamente con fresh context:

    ./.claude/scripts/fresh-exec.sh \
      openspec/changes/[feature]/tasks.md

El script fresh-exec.sh auto-detecta skills de Symfony relevantes por keywords
en el archivo de tasks e inyecta los apropiados (symfony-api, symfony-messenger,
symfony-doctrine, cdn-caching).

### 4. Verificar y archivar

    /opsx:verify      # valida que specs se cumplieron
    /opsx:archive     # archiva el cambio completado

### 5. Code review

    bmad-code-review   # review adversarial multi-capa

### 6. Security

    npx ecc-agentshield scan

---

## Flujo C: Bugs y cambios menores

Para cambios que no requieren planificacion formal:

    bmad-quick-dev

Barry (Solo Dev) guia: clarifica intension → planifica → implementa → revisa.

O directamente en Claude Code con los skills ECC cargados.

---

## Post-merge: CI/CD reactivo

Si el CI falla despues de un merge, el ci-reactor puede diagnosticar y arreglar:

    ./.claude/scripts/ci-reactor.sh path/to/ci-log.txt

El reactor clasifica el fix en HIGH/MEDIUM/LOW confianza.
Solo commitea automaticamente si es HIGH y los tests pasan.

---

## Cheatsheet rapido

| Quiero... | Comando |
|-----------|---------|
| Planificar proyecto nuevo | bmad-analyst → bmad-pm → bmad-architect |
| Especificar feature brownfield | /opsx:propose "descripcion" |
| Investigar antes de planificar | deep-research "tema" |
| Verificar specs antes de codear | readiness-gate |
| Implementar con contexto limpio | ./.claude/scripts/fresh-exec.sh task.md |
| Implementar + QA automatico | ./.claude/scripts/qa-loop.sh task.md 3 |
| Implementar en paralelo | ./.claude/scripts/parallel-tasks.sh a.md b.md |
| Code review adversarial | bmad-code-review |
| Edge case analysis | bmad-review-edge-case-hunter |
| Bug fix rapido | bmad-quick-dev |
| CI fallo, diagnosticar | ./.claude/scripts/ci-reactor.sh log.txt |
| Ver estado del proyecto BMAD | bmad-help |

---

## Estructura de archivos

    .claude/
    ├── skills/                              # 50+ skills total
    │   ├── bmad-* (43)                      # BMAD nativo
    │   ├── openspec-* (4)                   # OpenSpec nativo (tras instalacion)
    │   ├── symfony-api/SKILL.md             # Custom
    │   ├── symfony-messenger/SKILL.md       # Custom
    │   ├── symfony-doctrine/SKILL.md        # Custom
    │   ├── cdn-caching/SKILL.md             # Custom
    │   ├── openspec-to-ecc/SKILL.md         # Custom puente
    │   ├── readiness-gate/SKILL.md          # Custom gate check
    │   └── deep-research/SKILL.md           # Custom investigacion
    ├── agents/                              # ECC (tras instalacion del plugin)
    ├── hooks/                               # ECC hooks
    ├── rules/                               # ECC rules
    └── scripts/                             # Custom orquestacion
        ├── fresh-exec.sh
        ├── worktree-exec.sh
        ├── parallel-tasks.sh
        ├── qa-loop.sh
        └── ci-reactor.sh

    _bmad/                                   # BMAD core content
    _bmad-output/                            # BMAD artefactos
    ├── project-context.md
    ├── planning-artifacts/
    └── implementation-artifacts/

    openspec/                                # OpenSpec (tras instalacion)
    ├── config.yaml
    ├── specs/
    └── changes/

---

## Cuando usar que flujo

| Situacion | Flujo | Coste estimado |
|-----------|-------|----------------|
| Microservicio nuevo desde cero | A (BMAD completo) | $20-40 por feature |
| Feature compleja con diseno tecnico | A (BMAD completo) | $15-30 |
| Feature en servicio existente | B (OpenSpec + ECC) | $10-25 |
| Feature pequena bien entendida | C (bmad-quick-dev) | $2-5 |
| Bug fix | C (bmad-quick-dev) | $1-3 |
| Cambio de config / typo | Claude Code directo | $0.10 |

---

## Notas importantes

### Los skills de Symfony necesitan validacion A/B

Los 4 skills custom (symfony-api, messenger, doctrine, cdn-caching) estan escritos
con las convenciones de El Confidencial. Antes de confiar en ellos, haz el test A/B:
implementa la misma story con y sin el skill y compara la calidad.

### fresh-exec.sh auto-detecta skills por keywords

El script busca palabras clave en el archivo de task:
- "controller" o "endpoint" o "API" → inyecta symfony-api
- "consumer" o "Messenger" o "event" → inyecta symfony-messenger
- "entity" o "repository" o "migration" → inyecta symfony-doctrine
- "cache" o "CDN" o "Vary" → inyecta cdn-caching

### Que hacer si algo falla

| Problema | Accion |
|----------|--------|
| Skills de BMAD no aparecen | Reiniciar Claude Code (skills cargan al inicio) |
| OpenSpec /opsx: no responde | Verificar que openspec init se ejecuto |
| fresh-exec.sh timeout | Aumentar timeout: FRESH_EXEC_TIMEOUT=1200 |
| QA loop no converge en 3 | Problema de diseno, intervenir manualmente |
| Rate limiting en paralelo | Reducir MAX_PARALLEL=2 |
