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

Cuando anades un endpoint a un servicio existente, correr BMAD completo (analyst → pm →
architect → sm → stories) es overkill. OpenSpec te permite proponer un cambio, generar
specs con delta markers, y pasar directo a implementacion. Es el atajo correcto para
features en proyectos que ya existen.

### Por que ECC

ECC aporta lo que ni BMAD ni OpenSpec cubren: security scanning con multiples agentes,
continuous learning (extrae patrones de tus sesiones y los promueve a skills), y agents
especializados para debugging, performance, y accessibility. No duplica lo que BMAD hace
en planning.

### Como coexisten sin conflictos

- BMAD usa prefijo `bmad-*` para todos sus skills
- OpenSpec usa prefijo `openspec-*`
- ECC usa namespace de plugin (agents/, rules/, hooks/)
- Los hooks no se pisan porque operan en momentos distintos
- Los skills custom usan nombres descriptivos sin prefijo de sistema

---

## Instalacion (30 minutos)

### Paso 1: Crear proyecto Symfony

**Opcion A — Symfony API (recomendada para microservicios)**

    symfony new mi-proyecto --version="8.0.*"
    cd mi-proyecto
    composer require phpunit maker

O con Composer si no tienes Symfony CLI:

    composer create-project symfony/skeleton:"8.0.*" mi-proyecto
    cd mi-proyecto
    composer require phpunit maker

**Opcion B — Symfony webapp completa**

    symfony new mi-proyecto --version="8.0.*" --webapp
    cd mi-proyecto

**Opcion C — Skeleton de El Confidencial**

    composer create-project ec-awesomemakers1/skeleton-service mi-proyecto
    cd mi-proyecto
    docker compose up -d && composer install

Despues de cualquier opcion:

    git init && git add -A && git commit -m "init: proyecto base"


### Paso 2: Instalar BMAD

    npx bmad-method install

Seleccionar: BMM (core) + Claude Code + directorio actual.

O no-interactivo:

    npx bmad-method install --modules "bmm" --tools "claude-code" \
      --communication-language "Spanish" --document-output-language "Spanish" --yes

Verificar:

    ls .claude/skills/bmad-*   # Debes ver 43 directorios

    git add -A && git commit -m "setup: BMAD v6 (43 skills)"


### Paso 3: Instalar OpenSpec

    npm install -g @fission-ai/openspec
    openspec init

Verificar:

    ls .claude/skills/openspec-*   # 4 skills

    git add -A && git commit -m "setup: OpenSpec inicializado"


### Paso 4: Instalar ECC

Desde Claude Code:

    /plugin marketplace add affaan-m/everything-claude-code
    /plugin install everything-claude-code

Seleccionar PHP durante la instalacion.

Verificar:

    ls .claude/skills/       # bmad-* + openspec-*
    ls .claude/agents/       # planner.md, code-reviewer.md, etc (ECC)
    ls .claude/rules/        # common/, php/ (ECC)

    git add -A && git commit -m "setup: ECC (28 agents, 125 skills)"


### Paso 5: Instalar extensiones custom

Si tienes el template en GitHub:

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
- `fresh-exec.sh` — Lanza un sub-agente con contexto limpio para implementar una task.
  Lee project-context.md, architecture.md, y la task, lo inyecta todo inline en el prompt,
  y ejecuta claude -p. Auto-detecta skills de Symfony por keywords en la task.
- `worktree-exec.sh` — Igual que fresh-exec pero en un git worktree aislado. Los cambios
  van a una branch separada que puedes revisar antes de mergear.
- `parallel-tasks.sh` — Lanza multiples fresh-exec en paralelo para tasks independientes.
  Configurable con MAX_PARALLEL para evitar rate limiting.
- `qa-loop.sh` — Ciclo iterativo: implementa con fresh-exec → evalua con QA sub-agente →
  si FAIL, lanza fix sub-agente → re-evalua. Repite hasta PASS o max iteraciones (default 3).
- `ci-reactor.sh` — Lee un log de CI, clasifica el fallo (HIGH/MEDIUM/LOW confianza),
  y arregla si la confianza es alta. Solo commitea si los tests pasan.

**4 skills de Symfony:**
- `symfony-api` — Patrones REST: controllers invokables, RFC 7807, health endpoints
- `symfony-messenger` — Patrones Messenger: messages, handlers, AMQP, DelayStamp, Supervisor
- `symfony-doctrine` — Patrones Doctrine: entidades en Domain, XML mapping, repository pattern
- `cdn-caching` — Patrones CDN: Cache-Control, Vary, Surrogate Keys

**3 skills puente:**
- `openspec-to-ecc` — Convierte artefactos OpenSpec en task files para fresh-exec.sh
- `readiness-gate` — Verifica que specs estan completos antes de implementar (PASS/CONCERNS/FAIL)
- `deep-research` — Investigacion tecnica estructurada con report de opciones y recomendacion

    git add -A && git commit -m "setup: extensiones custom (scripts + skills)"


### Paso 6: Generar project-context.md

Este es el paso MAS IMPORTANTE. El project-context es lo que reciben los sub-agentes
cuando implementan con fresh-exec.sh. Si es pobre, los sub-agentes toman decisiones
incoherentes con tu stack.

En Claude Code:

    bmad-generate-project-context

BMAD escanea tu proyecto y detecta el stack. Revisa el resultado y enriquece con contexto
que el scanner no puede saber. Para El Confidencial, anade:

- Microservicios: Jarvis (BFF/API), editorial-service, journalist-service, rss-service
- Comunicacion async via eventos RabbitMQ (ej: editorial.published)
- REST APIs con RFC 7807, tres capas domain/application/infrastructure
- JWT via web-token/jwt-framework, header X-Auth-Token
- CDN caching con Vary: X-User-Level
- Clients via HTTPlug con plugins de retry/timeout

Mantener bajo 1500 palabras (~2000 tokens). El scanner detecta lo que HAY en el codigo.
Tu anades lo que DEBERIA guiar las decisiones.

    git add -A && git commit -m "setup: project-context generado"


### Verificacion final

    bmad-help                      # BMAD detecta tu proyecto
    /opsx:propose "test idea"      # OpenSpec responde
    # Pedir un code review a ECC

Si todo responde, la instalacion esta completa.

---

## Flujo A: Proyecto nuevo (BMAD completo)

Usa este flujo cuando creas un microservicio nuevo o una feature compleja que requiere
investigacion, arquitectura, y planificacion formal. Es el flujo mas largo pero produce
los artefactos mas completos.

**Como funciona internamente:** BMAD tiene agentes especializados (Mary = analyst,
John = PM, Winston = architect, Bob = SM). Cada agente carga su SKILL.md que contiene
un flujo de steps con menus interactivos. Cuando invocas `bmad-analyst`, no estas
pidiendo que genere un documento — estas iniciando una conversacion guiada donde el
agente te hace preguntas para entender tu dominio.

**CRITICO: Los agentes de BMAD leen step files con instrucciones como "HALT and WAIT
for user input". Si el agente te presenta un menu, ESPERA a que el usuario responda.
NUNCA generes contenido de los pasos siguientes sin haber completado el paso actual
con input del usuario. Las preguntas son el valor del flujo.**

### Fase 1: Analisis

    bmad-analyst

Mary (Business Analyst) se activa. Te presenta un menu con codigos:
- `BP` (Brainstorm Project) — Sesion de brainstorming guiada con tecnicas creativas.
  Mary te hace preguntas sobre el problema, explora alternativas, y produce un
  brainstorming-report.md. **No te saltes esta fase.** Las mejores decisiones de
  arquitectura nacen de un buen brainstorming.
- `CB` (Create Brief) — Crea el product brief. Mary sintetiza lo descubierto en el
  brainstorming en un documento ejecutivo de 1-2 paginas.

Para investigacion profunda antes de planificar:

    deep-research "como implementar sistema de notificaciones push
    para seguidores de periodistas en arquitectura de microservicios"

El skill deep-research busca en web, analiza el codebase, y produce un report
estructurado con opciones, pros/cons, y recomendacion. El report se guarda en
`_bmad-output/planning-artifacts/` para que los agentes siguientes lo referencien.

**Artefactos producidos:** brainstorming-report.md, product-brief.md

### Fase 2: Planning

    bmad-pm

John (PM) se activa. Escribe `CP` (Create PRD). John NO genera un PRD de golpe.
Te entrevista sobre requirements: que problema resuelves, para quien, que es exito,
que esta fuera de scope. Produce un PRD con FRs y NFRs basado en tus respuestas.

El PRD tiene un workflow de 11 pasos con menus A/P/C en cada uno:
- [A] Advanced Elicitation — Profundiza con tecnicas como Socratic o Pre-mortem
- [P] Party Mode — Mesa redonda de todos los agentes BMAD debatiendo
- [C] Continue — Acepta el contenido y avanza al siguiente paso

**Si eliges C en todo sin leer, el PRD sera generico. El valor esta en las fases
donde cuestionas y ajustas.**

Si hay UI: `bmad-ux-designer` → Sally crea UX specs.

**Artefactos producidos:** PRD.md (FRs, NFRs, success criteria, user journeys, scope)

### Fase 3: Solutioning

    bmad-architect

Winston (Architect) se activa. Escribe `CA` (Create Architecture). Winston lee el PRD
y el project-context.md, y propone architecture.md con ADRs (Architecture Decision Records).

Cada ADR documenta: contexto, decision, razon, consecuencias. Esto es lo que los
sub-agentes de implementacion necesitan para tomar decisiones coherentes.

Gate check antes de implementar:

    bmad-check-implementation-readiness

Verifica que PRD, arquitectura, y stories estan alineados. Resultado:
- **PASS** — Todo coherente, listo para implementar
- **CONCERNS** — Hay gaps menores, lista que falta
- **FAIL** — Gaps criticos, volver a iterar

**No implementes si el gate check falla.** El coste de implementar con specs
incompletos es mayor que el coste de iterar una vez mas.

**Artefactos producidos:** architecture.md (ADRs, estructura de proyecto, patrones)

### Fase 4: Crear stories

    bmad-pm          # EP (Create Epics and Stories)
    bmad-sm          # SP (Sprint Planning) → CS (Create Story)

Bob (SM) descompone en stories autocontenidas. Cada story tiene acceptance criteria,
dependencias, y estimacion. Los archivos story se guardan en
`_bmad-output/implementation-artifacts/`.

**Importante:** Una story debe tener suficiente contexto para que alguien que SOLO lea
ese archivo pueda implementarla. Si referencia cosas como "segun la arquitectura" sin
detalles, el sub-agente de fresh-exec no tendra el contexto necesario (aunque el script
inyecta architecture.md automaticamente).

    git add -A && git commit -m "plan: [feature] - PRD, arquitectura, stories"

### Fase 5: Implementar con fresh context

**Como funciona fresh-exec.sh:** El script lee el project-context.md, architecture.md,
y el archivo de story. INYECTA el contenido directamente en el prompt (no referencia
archivos que el sub-agente tendria que buscar, porque `claude -p` NO carga skills
de .claude/skills/). Lanza un sub-agente con 200k tokens completamente limpios.

Ademas, el script auto-detecta skills de Symfony por keywords en la story:
- "controller" o "endpoint" o "API" → inyecta symfony-api
- "consumer" o "Messenger" o "event" → inyecta symfony-messenger
- "entity" o "repository" o "migration" → inyecta symfony-doctrine
- "cache" o "CDN" o "Vary" → inyecta cdn-caching

**Por que fresh context importa:** Si implementas 5 stories seguidas en la misma sesion,
la story 5 tiene peor calidad que la 1 por context rot (el contexto acumulado ocupa
tokens que deberian estar disponibles para la implementacion). Fresh context elimina
este problema.

Por cada story, en serie:

    ./.claude/scripts/fresh-exec.sh \
      _bmad-output/implementation-artifacts/story-nombre.md

Con QA loop (implement + evaluate + fix, max 3 iteraciones):

    ./.claude/scripts/qa-loop.sh \
      _bmad-output/implementation-artifacts/story-nombre.md 3

**Como funciona qa-loop.sh:** Implementa la story con fresh-exec → lanza un sub-agente QA
que evalua el resultado con criterios ponderados → si FAIL, lanza un sub-agente fix que
recibe el QA report y arregla SOLO los bugs listados → re-evalua. Si despues de 3
iteraciones no pasa, hay un problema de diseno que requiere intervencion humana.

Para stories independientes (que NO dependen entre si), en paralelo:

    ./.claude/scripts/parallel-tasks.sh \
      story-a.md story-b.md story-c.md

Para implementar en un git worktree aislado (los cambios van a una branch separada):

    ./.claude/scripts/worktree-exec.sh \
      _bmad-output/implementation-artifacts/story-nombre.md

### Fase 6: Code review

BMAD v6 incluye review adversarial multi-capa nativo:

    bmad-code-review

Lanza tres revisores en paralelo:
- **Blind Hunter** — Revisa sin saber que deberia hacer el codigo, busca code smells
- **Edge Case Hunter** — Busca exhaustivamente edge cases no manejados
- **Acceptance Auditor** — Verifica que los acceptance criteria de la story se cumplen

Para review adversarial adicional (pone en duda cada decision):

    bmad-review-adversarial-general

Para edge cases exhaustivo:

    bmad-review-edge-case-hunter

### Fase 7: Security scan

    npx ecc-agentshield scan
    # Con analisis profundo de tres agentes Opus:
    npx ecc-agentshield scan --opus --stream

### Fase 8: Siguiente story

    bmad-sm    # CS (Create Story)

Repetir desde fase 5.

---

## Flujo B: Feature en proyecto existente (OpenSpec)

Usa este flujo cuando anades una feature a un microservicio que ya existe. Es mas ligero
que el flujo A porque no necesitas PRD ni arquitectura completa — el proyecto ya tiene
ambos. OpenSpec trabaja con delta markers (ADDED/MODIFIED/REMOVED) que indican exactamente
que cambia respecto al estado actual.

**Por que OpenSpec y no BMAD para brownfield:** BMAD esta disenado para pensar un producto
desde cero. Cuando ya tienes un servicio con 50 endpoints y quieres anadir uno mas, correr
analyst → pm → architect es desproporcionado. OpenSpec te permite proponer el cambio,
especificarlo con precision, y pasar directo a implementar.

### 1. Investigar si hace falta

Si la feature involucra patrones que no has usado antes:

    deep-research "patrones de paginacion cursor-based en APIs REST con Doctrine"

### 2. Proponer el cambio

    /opsx:propose "Anadir endpoint GET /followers/:journalistId
    que devuelva la lista de seguidores con paginacion cursor-based"

OpenSpec analiza tu codebase existente y genera:
- `openspec/changes/[feature]/proposal.md` — por que este cambio
- `openspec/changes/[feature]/specs/` — que cambia, con marcadores ADDED/MODIFIED/REMOVED
- `openspec/changes/[feature]/design.md` — como implementarlo
- `openspec/changes/[feature]/tasks.md` — checklist de implementacion

### 3. Gate check

    readiness-gate

El skill readiness-gate verifica que los specs estan completos:
- Tiene proposal.md con rationale claro?
- Tiene spec files con delta markers especificos?
- Tiene design.md con approach de implementacion?
- Tiene tasks.md con checklist concreto?

Resultado: PASS / CONCERNS / FAIL. **No implementes con FAIL.**

### 4. Puente OpenSpec → Ejecucion

Si usas fresh-exec.sh directamente, el script openspec-to-ecc convierte los artefactos
de OpenSpec en un task file consolidado que fresh-exec entiende.

### 5. Implementar

Con QA loop:

    ./.claude/scripts/qa-loop.sh \
      openspec/changes/[feature]/tasks.md 3

O directamente con fresh context:

    ./.claude/scripts/fresh-exec.sh \
      openspec/changes/[feature]/tasks.md

### 6. Verificar y archivar

    /opsx:verify      # Valida que los specs se cumplieron en el codigo
    /opsx:archive     # Archiva el cambio como completado

### 7. Code review y security

    bmad-code-review
    npx ecc-agentshield scan

---

## Flujo C: Bugs y cambios menores

Para cambios que no requieren planificacion formal. No necesitas specs, ni stories,
ni gate checks.

    bmad-quick-dev

Barry (Solo Dev) guia el proceso: clarifica la intencion → planifica rapidamente →
implementa → revisa el resultado. Es un flujo interactivo pero ligero.

O directamente en Claude Code sin skills — para cambios de config, typos, o fixes obvios.

---

## Post-merge: CI/CD reactivo

**Como funciona ci-reactor.sh:** Lee un log de CI (archivo o URL), clasifica cada fallo
en tipo (test failure, lint error, build error, dependency issue) y confianza
(HIGH/MEDIUM/LOW). Solo para HIGH confianza hace el fix y commitea (si los tests pasan).
Para MEDIUM/LOW reporta findings sin tocar codigo.

    ./.claude/scripts/ci-reactor.sh path/to/ci-log.txt

Para integracion con GitLab CI:

    auto-fix:
      stage: fix
      when: on_failure
      script:
        - ./.claude/scripts/ci-reactor.sh "$CI_JOB_URL"
      allow_failure: true

---

## Cheatsheet rapido

| Quiero... | Comando |
|-----------|---------|
| Planificar proyecto nuevo | bmad-analyst → bmad-pm → bmad-architect |
| Especificar feature brownfield | /opsx:propose "descripcion" |
| Investigar antes de planificar | deep-research "tema" |
| Verificar specs antes de codear | readiness-gate |
| Implementar con contexto limpio | ./.claude/scripts/fresh-exec.sh task.md |
| Implementar en worktree aislado | ./.claude/scripts/worktree-exec.sh task.md |
| Implementar + QA automatico | ./.claude/scripts/qa-loop.sh task.md 3 |
| Implementar en paralelo | ./.claude/scripts/parallel-tasks.sh a.md b.md |
| Code review adversarial | bmad-code-review |
| Edge case analysis | bmad-review-edge-case-hunter |
| Security scan | npx ecc-agentshield scan |
| Bug fix rapido | bmad-quick-dev |
| CI fallo, diagnosticar | ./.claude/scripts/ci-reactor.sh log.txt |
| Ver estado del proyecto BMAD | bmad-help |
| Ver que OpenSpec esta activo | /opsx:explore |

---

## Cuando usar que flujo

| Situacion | Flujo | Por que |
|-----------|-------|---------|
| Microservicio nuevo desde cero | A (BMAD completo) | Necesitas definir dominio, arquitectura, y contratos desde cero |
| Feature compleja con diseno tecnico | A (BMAD completo) | Requiere ADRs y stories detalladas |
| Feature en servicio existente | B (OpenSpec) | El servicio ya tiene arquitectura, solo necesitas el delta |
| Feature pequena bien entendida | C (bmad-quick-dev) | No necesita specs, Barry te guia rapido |
| Bug fix | C (bmad-quick-dev) | Fix directo con TDD |
| Cambio de config / typo | Claude Code directo | No necesita ni workflow ni agente |

---

## Estructura de archivos

    .claude/
    ├── skills/                              # 50+ skills total
    │   ├── bmad-* (43)                      # BMAD nativo — NO modificar
    │   ├── openspec-* (4)                   # OpenSpec nativo
    │   ├── symfony-api/SKILL.md             # Custom: REST patterns, RFC 7807
    │   ├── symfony-messenger/SKILL.md       # Custom: Messenger, AMQP, Supervisor
    │   ├── symfony-doctrine/SKILL.md        # Custom: Entities, XML mapping
    │   ├── cdn-caching/SKILL.md             # Custom: Cache-Control, Vary, Surrogate Keys
    │   ├── openspec-to-ecc/SKILL.md         # Custom: puente OpenSpec → fresh-exec
    │   ├── readiness-gate/SKILL.md          # Custom: gate check pre-implementacion
    │   └── deep-research/SKILL.md           # Custom: investigacion estructurada
    ├── agents/                              # ECC agents (via plugin)
    ├── hooks/                               # ECC hooks
    ├── rules/                               # ECC rules (common/ + php/)
    └── scripts/                             # Custom orquestacion
        ├── fresh-exec.sh                    # Sub-agente con contexto limpio
        ├── worktree-exec.sh                 # Sub-agente en worktree aislado
        ├── parallel-tasks.sh                # Ejecucion paralela con throttling
        ├── qa-loop.sh                       # Ciclo implement-evaluate-fix
        └── ci-reactor.sh                    # Diagnostico y fix de CI failures

    _bmad/                                   # BMAD core content (NO modificar)
    _bmad-output/                            # BMAD artefactos generados
    ├── project-context.md                   # Contexto para sub-agentes (CLAVE)
    ├── brainstorming/                       # Sesiones de brainstorming
    ├── planning-artifacts/                  # PRD, architecture, research, epics
    └── implementation-artifacts/            # Stories individuales

    openspec/                                # OpenSpec (tras instalacion)
    ├── config.yaml
    ├── specs/
    └── changes/                             # Cambios propuestos y archivados

---

## Notas importantes

### Los skills de Symfony necesitan validacion A/B

Los 4 skills custom estan escritos con las convenciones de El Confidencial. Antes de confiar
en ellos, haz el test A/B: implementa la misma story con y sin el skill y compara calidad.
Solo mantiene los skills que demuestren mejora real.

### fresh-exec.sh auto-detecta skills por keywords

El script busca palabras clave en el archivo de task e inyecta el SKILL.md correspondiente
en el prompt del sub-agente. Puedes ver que skills se inyectaron en la salida del script.

### ECC continuous learning

ECC extrae patrones de tus sesiones y los convierte en "instincts" con confidence scoring.
Con el tiempo, los instincts utiles se promueven a skills completos. Esto reemplaza
cualquier mecanismo manual de documentar patrones.

### Que hacer si algo falla

| Problema | Accion |
|----------|--------|
| Skills de BMAD no aparecen | Reiniciar Claude Code (skills cargan al inicio) |
| BMAD genera sin preguntar | Asegurar que el SKILL.md se lee completo; los step files dicen "HALT and WAIT" |
| ECC y BMAD hooks conflictan | Mergear manualmente en settings.json |
| OpenSpec /opsx: no responde | Verificar que openspec init se ejecuto |
| fresh-exec.sh timeout | Aumentar: FRESH_EXEC_TIMEOUT=1200 |
| QA loop no converge en 3 | Problema de diseno, intervenir manualmente |
| Rate limiting en paralelo | Reducir MAX_PARALLEL=2 |
| project-context.md muy largo | Mantener bajo 1500 palabras, separar patterns en archivo aparte |
| Stories sin suficiente contexto | Ajustar fresh-exec.sh para inyectar mas archivos |
