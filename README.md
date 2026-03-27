# EC Workflow Template

Template con el workflow BMAD+ECC+OpenSpec para proyectos Symfony.

## Uso rapido

Desde cualquier sesion de Claude Code en otro repo:

```
Clona el workflow template de arazvan-ec/suscription branch workflow-template
y configura este proyecto con el mismo workflow.
```

O manualmente:

```bash
git clone -b workflow-template --single-branch https://github.com/arazvan-ec/suscription.git /tmp/wf-template
cp -r /tmp/wf-template/.claude/scripts/ .claude/scripts/
cp -r /tmp/wf-template/.claude/skills/symfony-* .claude/skills/
cp -r /tmp/wf-template/.claude/skills/openspec-to-ecc .claude/skills/
cp -r /tmp/wf-template/.claude/skills/readiness-gate .claude/skills/
cp -r /tmp/wf-template/.claude/skills/deep-research .claude/skills/
cp /tmp/wf-template/docs/workflow-guide.md docs/
chmod +x .claude/scripts/*.sh
npx bmad-method install --modules bmm --tools claude-code --yes
npm install -g @fission-ai/openspec && openspec init
rm -rf /tmp/wf-template
```

ECC se instala desde Claude Code:
```
/plugin marketplace add affaan-m/everything-claude-code
/plugin install everything-claude-code
```

## Contenido

- 5 scripts de orquestacion (fresh-exec, worktree, parallel, QA loop, CI reactor)
- 7 skills custom (4 Symfony + 3 puente)
- Workflow guide completo
- CLAUDE.md base para adaptar
- setup-workflow.sh para automatizar la instalacion
