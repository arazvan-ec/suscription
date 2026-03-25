# Guia: Workflow Completo — Lo Mejor de Cada Fase

## Filosofia

No instalamos 5 sistemas que no hablan entre si. Instalamos uno (BMAD) y le robamos
las tecnicas clave a los demas. Las ideas son mas portables que los frameworks.

| Fase | Mejor en clase | Lo que robamos | Como lo implementamos |
|------|---------------|----------------|----------------------|
| 1-3. Analisis, Planning, Solutioning | BMAD | Todo (es nativo) | Instalacion directa |
| 4-5. Planificacion + Ejecucion | GSD | Fresh context per task, waves paralelas | Scripts bash con claude -p |
| 6. Verificacion | Articulo Anthropic | Evaluator separado + scoring + loop | Skill custom qa-evaluator |
| 7. Code Review | Claude Harness | Critic adversarial que desafia decisiones | Reemplaza bmad-code-review |
| 8. Aprendizaje | Compound Engineering | Documentar patrones, actualizar contexto | Skill custom compound-learn |

## Estructura del Proyecto

```
.claude/
  scripts/
    fresh-dev-story.sh      # Ejecuta una story con contexto limpio (tecnica GSD)
    parallel-stories.sh     # Ejecuta stories independientes en paralelo (waves)
    qa-loop.sh              # Loop evaluator-developer iterativo (tecnica Anthropic)
  skills/
    qa-evaluator/SKILL.md   # Skill de evaluacion QA con scoring
    adversarial-code-review/SKILL.md  # Code review adversarial (tecnica Harness)
    bmad-*/                 # 43 skills nativos de BMAD
_bmad/                      # Instalacion de BMAD Method v6
_bmad-output/
  project-context.md        # Contexto del proyecto (clave para sub-agentes)
  planning-artifacts/       # PRD, architecture, epics
  implementation-artifacts/ # Stories
docs/
  workflow-guide.md         # Esta guia
```

## Flujo Completo

### Fases 1-3: BMAD nativo

```bash
# En Claude Code:
bmad-analyst    # -> BP (Brainstorm) -> CB (Create Brief)
bmad-pm         # -> CP (Create PRD)
bmad-architect  # -> CA (Create Architecture) -> IR (Implementation Readiness)
bmad-pm         # -> EP (Create Epics and Stories)
bmad-sm         # -> SP (Sprint Planning) -> CS (Create Story)
```

### Fases 4-5: Ejecucion con contexto limpio (tecnica GSD)

```bash
# Una story individual
./.claude/scripts/fresh-dev-story.sh \
  _bmad-output/implementation-artifacts/story-health-endpoint.md

# Stories independientes en paralelo
./.claude/scripts/parallel-stories.sh \
  story-1.md story-2.md story-3.md
```

### Fase 6: Verificacion con loop QA (tecnica Anthropic)

```bash
# Implementa + evalua + itera hasta PASS (max 3 iteraciones)
./.claude/scripts/qa-loop.sh \
  _bmad-output/implementation-artifacts/story-health-endpoint.md 3
```

### Fase 7: Code review adversarial (tecnica Harness)

```bash
# En Claude Code, carga el skill y pasa el diff:
# "Revisa los cambios usando el skill adversarial-code-review"
```

## Test de Validacion (Parte 0)

Antes de usar el flujo completo, valida con una story simple:

1. **Story:** GET /health endpoint
2. **Implementar:** `fresh-dev-story.sh` con la story
3. **Evaluar:** Sub-agente QA con curl
4. **Loop:** `qa-loop.sh` completo
5. **Decidir:** Ajustar segun resultados

## Notas Importantes

- `claude -p` NO carga skills de `.claude/skills/`. Los scripts inyectan el contenido.
- Cada sub-agente arranca con contexto 100% limpio (200k tokens frescos).
- Solo paraleliza stories que NO dependen entre si.
- Si despues de 3 iteraciones QA no pasa, hay un problema de diseno -> intervencion humana.
- El adversarial review tiene threshold 7/10 (mas alto que QA con 6/10).
