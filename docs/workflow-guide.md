# Guia: Workflow Completo BMAD + ECC + OpenSpec

## Filosofia

No instalamos 5 sistemas que no hablan entre si. Instalamos tres que se complementan
y les anadimos extensiones custom donde ninguno llega. Las ideas son mas portables
que los frameworks.

| Fase | Mejor en clase | Que aporta | Como lo usamos |
|------|---------------|------------|----------------|
| 1-3. Analisis, Planning, Solutioning | BMAD | Flujo interactivo profundo con personas especializadas (Mary, John, Winston) que hacen preguntas, no generan contenido a ciegas | Instalacion directa, 43 skills nativos |
| 4-5. Ejecucion | GSD (tecnica) | Fresh context per task: cada story se implementa con 200k tokens limpios, sin context rot | Scripts bash con claude -p (no instalamos GSD, solo su tecnica clave) |
| 6. Verificacion | Articulo Anthropic (tecnica) | Evaluator separado que puntua con criterios + loop iterativo developer-evaluator | Script qa-loop.sh |
| 7. Code Review | BMAD v6 nativo | Review adversarial multi-capa (Blind Hunter + Edge Case Hunter + Acceptance Auditor) | bmad-code-review (ya viene con BMAD v6) |
| 8. Security | ECC | Scan de seguridad con tres agentes Opus en paralelo | Plugin nativo de ECC |
| 9. Features brownfield | OpenSpec | Spec-driven con delta markers (ADDED/MODIFIED/REMOVED) para proyectos existentes | CLI nativo de OpenSpec |
| 10. Aprendizaje | ECC | Extrae patrones de tus sesiones, los convierte en "instincts" con confidence scoring | Automatico via ECC |

### Por que BMAD y no generar directo

BMAD tiene skills con flujos interactivos paso a paso. Cada skill (bmad-analyst, bmad-pm,
bmad-architect) carga un SKILL.md con instrucciones detalladas, menus, y reglas de cuando
parar y esperar input del usuario. Esto es CRITICO: los agentes de BMAD hacen preguntas
para entender tu dominio antes de generar nada. Si te saltas las preguntas, el output es
generico e inutil.

**REGLA FUNDAMENTAL: Cuando uses un skill de BMAD, sigue su flujo interactivo completo.
NUNCA generes contenido sin que el agente te haya preguntado primero. Si el step file
dice "HALT and WAIT for user input", PARA y ESPERA.**

### Por que no instalar GSD completo

GSD espera su propio PROJECT.md y .planning/ con formato propio. Transformar los artefactos
de BMAD al formato GSD es fragil. Pero su tecnica clave (fresh context per task) se replica
con ~100 lineas de bash. El script fresh-exec.sh hace exactamente eso: lee el project-context,
la arquitectura, y la story, lo inyecta todo en un prompt, y lanza claude -p con contexto
100% limpio.

### Por que no instalar Harness

El review adversarial de Harness (Planner+Critic) era valioso en BMAD v4-v5 donde
bmad-code-review tendia a aprobar sin cuestionar. BMAD v6 ya incluye review multi-capa
nativo con tres revisores en paralelo que se contradicen entre si. No necesitamos Harness.

### Por que OpenSpec para brownfield

Cuando anades un endpoint a un servicio existente, correr BMAD completo (analyst > pm >
architect > sm > stories) es overkill. OpenSpec te permite proponer un cambio, generar
specs con delta markers, y pasar directo a implementacion. Es el atajo correcto para
features en proyectos que ya existen.

### Por que ECC

ECC aporta lo que ni BMAD ni OpenSpec cubren: security scanning con multiples agentes,
continuous learning (extrae patrones de tus sesiones y los promueve a skills), y agents
especializados para debugging, performance, y accessibility. No duplica lo que BMAD hace
en planning.

### Como coexisten sin conflictos

- BMAD usa prefijo bmad-* para todos sus skills
- OpenSpec usa prefijo openspec-*
- ECC usa namespace de plugin (agents/, rules/, hooks/)
- Los hooks no se pisan porque operan en momentos distintos
- Los skills custom usan nombres descriptivos sin prefijo de sistema

---

## Instalacion (30 minutos)

### Paso 1: Crear proyecto Symfony

**Opcion A -- Symfony API (recomendada para microservicios)**

    symfony new mi-proyecto --version="8.0.*"
    cd mi-proyecto
    composer require phpunit maker

**Opcion B -- Symfony webapp completa**

    symfony new mi-proyecto --version="8.0.*" --webapp
    cd mi-proyecto

**Opcion C -- Skeleton de El Confidencial**

    composer create-project ec-awesomemakers1/skeleton-service mi-proyecto
    cd mi-proyecto
    docker compose up -d && composer install

Despues de cualquier opcion:

    git init && git add -A && git commit -m "init: proyecto base"

### Paso 2: Instalar BMAD

    npx bmad-method install --modules "bmm" --tools "claude-code" \
      --communication-language "Spanish" --document-output-language "Spanish" --yes

Verificar: ls .claude/skills/bmad-* (43 directorios)

### Paso 3: Instalar OpenSpec

    npm install -g @fission-ai/openspec
    openspec init

### Paso 4: Instalar ECC

Desde Claude Code:

    /plugin marketplace add affaan-m/everything-claude-code
    /plugin install everything-claude-code

### Paso 5: Instalar extensiones custom

    git clone -b workflow-template --single-branch \
      https://github.com/arazvan-ec/suscription.git /tmp/wf-template
    cp -r /tmp/wf-template/.claude/scripts/ .claude/scripts/
    cp -r /tmp/wf-template/.claude/skills/symfony-* .claude/skills/
    cp -r /tmp/wf-template/.claude/skills/openspec-to-ecc .claude/skills/
    cp -r /tmp/wf-template/.claude/skills/readiness-gate .claude/skills/
    cp -r /tmp/wf-template/.claude/skills/deep-research .claude/skills/
    cp /tmp/wf-template/docs/workflow-guide.md docs/
    chmod +x .claude/scripts/*.sh
    rm -rf /tmp/wf-template

Lo que se instala:

**5 scripts de orquestacion:**
- fresh-exec.sh -- Sub-agente con contexto limpio. Auto-detecta skills Symfony por keywords.
- worktree-exec.sh -- Igual que fresh-exec pero en git worktree aislado.
- parallel-tasks.sh -- Multiples fresh-exec en paralelo. MAX_PARALLEL configurable.
- qa-loop.sh -- Ciclo implement > evaluate > fix. Repite hasta PASS o max iteraciones.
- ci-reactor.sh -- Diagnostica CI failures. Solo commitea si HIGH confianza y tests pasan.

**4 skills Symfony:** symfony-api, symfony-messenger, symfony-doctrine, cdn-caching
**3 skills puente:** openspec-to-ecc, readiness-gate, deep-research

### Paso 6: Generar project-context.md

Este es el paso MAS IMPORTANTE. El project-context es lo que reciben los sub-agentes
cuando implementan con fresh-exec.sh. Si es pobre, los sub-agentes toman decisiones
incoherentes con tu stack.

    bmad-generate-project-context

Mantener bajo 1500 palabras.

---

## Flujo A: Proyecto nuevo (BMAD completo)

Usa este flujo cuando creas un microservicio nuevo o una feature compleja que requiere
investigacion, arquitectura, y planificacion formal.

**Como funciona internamente:** BMAD tiene agentes especializados (Mary = analyst,
John = PM, Winston = architect, Bob = SM). Cada agente carga su SKILL.md con un flujo
de steps con menus interactivos. Cuando invocas bmad-analyst, no estas pidiendo que
genere un documento -- estas iniciando una conversacion guiada donde el agente te hace
preguntas para entender tu dominio.

**CRITICO: Los agentes de BMAD leen step files con instrucciones como "HALT and WAIT
for user input". Si el agente te presenta un menu, ESPERA a que el usuario responda.
NUNCA generes contenido de los pasos siguientes sin haber completado el paso actual
con input del usuario. Las preguntas son el valor del flujo.**

### Fase 1: Analisis

    bmad-analyst

Mary (Business Analyst) se activa. Te presenta un menu:
- BP (Brainstorm Project) -- Sesion de brainstorming guiada con tecnicas creativas.
  Mary te hace preguntas sobre el problema, explora alternativas. No te saltes esta fase.
- CB (Create Brief) -- Sintetiza lo descubierto en product brief de 1-2 paginas.

Para investigacion profunda:

    deep-research "tema a investigar"

**Artefactos producidos:** brainstorming-report.md, product-brief.md

### Fase 2: Planning

    bmad-pm

John (PM) se activa. Escribe CP (Create PRD). John NO genera un PRD de golpe.
Te entrevista sobre requirements. El PRD tiene 11 pasos con menus A/P/C:
- [A] Advanced Elicitation -- Profundiza con Socratic o Pre-mortem
- [P] Party Mode -- Mesa redonda de todos los agentes BMAD
- [C] Continue -- Acepta y avanza

**Si eliges C en todo sin leer, el PRD sera generico.**

**Artefactos producidos:** PRD.md

### Fase 3: Solutioning

    bmad-architect

Winston (Architect) lee el PRD y project-context, propone architecture.md con ADRs.

Gate check:

    bmad-check-implementation-readiness

PASS / CONCERNS / FAIL. **No implementes si falla.**

**Artefactos producidos:** architecture.md

### Fase 4: Crear stories

    bmad-pm          # EP (Create Epics and Stories)
    bmad-sm          # SP (Sprint Planning) > CS (Create Story)

Cada story debe ser autocontenida.

### Fase 5: Implementar con fresh context

**Como funciona fresh-exec.sh:** Lee project-context.md, architecture.md, y la story.
INYECTA el contenido en el prompt (claude -p NO carga skills). Lanza sub-agente con
200k tokens limpios. Auto-detecta skills Symfony por keywords:
- "controller/endpoint/API" > symfony-api
- "consumer/Messenger/event" > symfony-messenger
- "entity/repository/migration" > symfony-doctrine
- "cache/CDN/Vary" > cdn-caching

**Por que fresh context importa:** Si implementas 5 stories seguidas, la story 5
tiene peor calidad que la 1 por context rot. Fresh context elimina este problema.

    ./.claude/scripts/fresh-exec.sh story.md

Con QA loop:

    ./.claude/scripts/qa-loop.sh story.md 3

**Como funciona qa-loop.sh:** Implementa con fresh-exec > evalua con QA sub-agente >
si FAIL, lanza fix sub-agente > re-evalua. 3 iteraciones max.

En paralelo (solo stories independientes):

    ./.claude/scripts/parallel-tasks.sh story-a.md story-b.md

En worktree aislado:

    ./.claude/scripts/worktree-exec.sh story.md

### Fase 6: Code review

    bmad-code-review

Tres revisores en paralelo: Blind Hunter, Edge Case Hunter, Acceptance Auditor.

### Fase 7: Security

    npx ecc-agentshield scan

---

## Flujo B: Feature en proyecto existente (OpenSpec)

**Por que OpenSpec y no BMAD:** BMAD piensa productos desde cero. Cuando ya tienes
un servicio con 50 endpoints y quieres anadir uno, OpenSpec trabaja con delta markers.

### 1. Proponer

    /opsx:propose "descripcion del cambio"

### 2. Gate check

    readiness-gate

### 3. Implementar

    ./.claude/scripts/qa-loop.sh openspec/changes/[feature]/tasks.md 3

### 4. Verificar

    /opsx:verify
    /opsx:archive

### 5. Review

    bmad-code-review
    npx ecc-agentshield scan

---

## Flujo C: Bugs y cambios menores

    bmad-quick-dev

Barry guia: clarifica > planifica > implementa > revisa.

---

## Post-merge: CI/CD reactivo

    ./.claude/scripts/ci-reactor.sh path/to/ci-log.txt

Clasifica HIGH/MEDIUM/LOW. Solo commitea en HIGH si tests pasan.

---

## Cheatsheet

| Quiero... | Comando |
|-----------|---------|
| Planificar proyecto nuevo | bmad-analyst > bmad-pm > bmad-architect |
| Feature brownfield | /opsx:propose "descripcion" |
| Investigar | deep-research "tema" |
| Gate check | readiness-gate |
| Implementar limpio | ./.claude/scripts/fresh-exec.sh task.md |
| Implementar + QA | ./.claude/scripts/qa-loop.sh task.md 3 |
| Paralelo | ./.claude/scripts/parallel-tasks.sh a.md b.md |
| Code review | bmad-code-review |
| Bug fix | bmad-quick-dev |
| CI fallo | ./.claude/scripts/ci-reactor.sh log.txt |

---

## Cuando usar que flujo

| Situacion | Flujo | Por que |
|-----------|-------|---------|
| Microservicio nuevo | A (BMAD) | Necesitas dominio, arquitectura, contratos desde cero |
| Feature compleja | A (BMAD) | Requiere ADRs y stories detalladas |
| Feature existente | B (OpenSpec) | Solo necesitas el delta |
| Feature pequena | C (quick-dev) | Barry te guia rapido |
| Bug fix | C (quick-dev) | Fix directo con TDD |
| Config/typo | Claude directo | No necesita workflow |

---

## Que hacer si algo falla

| Problema | Accion |
|----------|--------|
| BMAD genera sin preguntar | Los step files dicen HALT and WAIT -- asegurar que se leen completos |
| Skills no aparecen | Reiniciar Claude Code |
| fresh-exec.sh timeout | FRESH_EXEC_TIMEOUT=1200 |
| QA loop no converge | Problema de diseno, intervenir manualmente |
| Rate limiting | MAX_PARALLEL=2 |
| project-context largo | Mantener bajo 1500 palabras |
