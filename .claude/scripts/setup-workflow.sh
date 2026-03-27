#!/usr/bin/env bash
# setup-workflow.sh — Replica el workflow BMAD+ECC+OpenSpec en otro repo
#
# Uso:
#   ./.claude/scripts/setup-workflow.sh /path/al/otro/repo
#
# Que hace:
#   1. Copia scripts de orquestacion (5)
#   2. Copia skills custom (7)
#   3. Copia workflow guide
#   4. Instala BMAD Method (43 skills)
#   5. Instala OpenSpec (4 skills)
#   6. Instala ECC (28 agents, 125 skills)
#   7. Genera project-context.md
#
# Requisitos:
#   - Node.js v20+
#   - npm
#   - Claude Code CLI (para ECC plugin)

set -euo pipefail

if [[ $# -lt 1 ]]; then
    echo "Uso: $0 /path/al/otro/repo"
    echo ""
    echo "Replica el workflow completo BMAD+ECC+OpenSpec en otro repositorio."
    exit 1
fi

TARGET="$(cd "$1" && pwd)"
SOURCE="$(cd "$(dirname "$0")/../.." && pwd)"

echo "=== Setup Workflow ==="
echo "Source: $SOURCE"
echo "Target: $TARGET"
echo "======================"
echo ""

# --- 1. Copiar scripts de orquestacion ---
echo ">>> Paso 1/7: Copiando scripts de orquestacion..."
mkdir -p "$TARGET/.claude/scripts"
for script in fresh-exec.sh worktree-exec.sh parallel-tasks.sh qa-loop.sh ci-reactor.sh; do
    if [[ -f "$SOURCE/.claude/scripts/$script" ]]; then
        cp "$SOURCE/.claude/scripts/$script" "$TARGET/.claude/scripts/"
    fi
done
chmod +x "$TARGET/.claude/scripts/"*.sh 2>/dev/null || true
echo "  5 scripts copiados."

# --- 2. Copiar skills custom ---
echo ">>> Paso 2/7: Copiando skills custom..."
for skill in symfony-api symfony-messenger symfony-doctrine cdn-caching \
             openspec-to-ecc readiness-gate deep-research; do
    if [[ -d "$SOURCE/.claude/skills/$skill" ]]; then
        mkdir -p "$TARGET/.claude/skills/$skill"
        cp "$SOURCE/.claude/skills/$skill/SKILL.md" "$TARGET/.claude/skills/$skill/"
    fi
done
echo "  7 skills copiados."

# --- 3. Copiar workflow guide ---
echo ">>> Paso 3/7: Copiando workflow guide..."
mkdir -p "$TARGET/docs"
cp "$SOURCE/docs/workflow-guide.md" "$TARGET/docs/"
echo "  docs/workflow-guide.md copiado."

# --- 4. Instalar BMAD Method ---
echo ">>> Paso 4/7: Instalando BMAD Method..."
if command -v npx &> /dev/null; then
    (cd "$TARGET" && npx bmad-method install \
        --directory "$TARGET" \
        --modules "bmm" \
        --tools "claude-code" \
        --communication-language "Spanish" \
        --document-output-language "Spanish" \
        --yes 2>&1) | tail -5
    echo "  BMAD instalado (43 skills)."
else
    echo "  WARN: npx no disponible. Instala BMAD manualmente: npx bmad-method install"
fi

# --- 5. Instalar OpenSpec ---
echo ">>> Paso 5/7: Instalando OpenSpec..."
if command -v npm &> /dev/null; then
    # Instalar globalmente si no existe
    if ! command -v openspec &> /dev/null; then
        npm install -g @fission-ai/openspec 2>&1 | tail -3
    fi
    (cd "$TARGET" && openspec init 2>&1) | tail -3
    echo "  OpenSpec instalado (4 skills)."
else
    echo "  WARN: npm no disponible. Instala OpenSpec manualmente:"
    echo "    npm install -g @fission-ai/openspec && cd $TARGET && openspec init"
fi

# --- 6. Instalar ECC ---
echo ">>> Paso 6/7: Instalando ECC..."
if command -v claude &> /dev/null; then
    echo "  ECC requiere instalacion interactiva desde Claude Code."
    echo "  Ejecuta estos comandos manualmente dentro de Claude Code:"
    echo ""
    echo "    claude"
    echo "    /plugin marketplace add affaan-m/everything-claude-code"
    echo "    /plugin install everything-claude-code"
    echo ""
    echo "  Selecciona PHP durante la instalacion selectiva."
else
    echo "  WARN: Claude Code CLI no disponible."
    echo "  Instala ECC manualmente desde Claude Code:"
    echo "    /plugin marketplace add affaan-m/everything-claude-code"
    echo "    /plugin install everything-claude-code"
fi

# --- 7. Generar project-context ---
echo ">>> Paso 7/7: Preparando project-context..."
mkdir -p "$TARGET/_bmad-output/planning-artifacts" "$TARGET/_bmad-output/implementation-artifacts"
echo "  Directorios _bmad-output creados."
echo "  Para generar el contexto, ejecuta en Claude Code: bmad-generate-project-context"

# --- Verificacion ---
echo ""
echo "=== Verificacion ==="
echo ""

SKILLS_COUNT=0
[[ -d "$TARGET/.claude/skills" ]] && SKILLS_COUNT=$(ls -d "$TARGET/.claude/skills/"*/ 2>/dev/null | wc -l)
SCRIPTS_COUNT=$(ls "$TARGET/.claude/scripts/"*.sh 2>/dev/null | wc -l)

echo "Skills instalados: $SKILLS_COUNT"
echo "Scripts instalados: $SCRIPTS_COUNT"
echo ""

# --- CLAUDE.md reminder ---
echo "=== Pasos manuales restantes ==="
echo ""
echo "1. Crear/adaptar CLAUDE.md en $TARGET con las convenciones del nuevo proyecto"
echo "   (puedes copiar $SOURCE/CLAUDE.md como base y modificar)"
echo ""
echo "2. Instalar ECC desde Claude Code (paso 6 arriba)"
echo ""
echo "3. Generar project-context.md:"
echo "   cd $TARGET && claude"
echo "   bmad-generate-project-context"
echo ""
echo "4. Verificar que todo responde:"
echo "   bmad-help"
echo "   /opsx:propose \"test\""
echo ""
echo "5. Commit inicial:"
echo "   git add -A && git commit -m \"setup: BMAD+ECC+OpenSpec workflow\""
echo ""
echo "=== Setup completo ==="
