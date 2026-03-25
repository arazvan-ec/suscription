# Project Context

## Overview

**Project Name:** bmad-workflow-lab
**Type:** Symfony 7.2 API/Microservice
**Purpose:** Laboratory project to validate the BMAD + hybrid workflow (GSD fresh contexts, Anthropic QA evaluator, Harness adversarial review).

## Tech Stack

| Component | Technology | Version |
|-----------|-----------|---------|
| Language | PHP | 8.4+ |
| Framework | Symfony | 7.2.* |
| Testing | PHPUnit | 13.x |
| Package Manager | Composer | 2.x |
| Node.js (tooling) | Node.js | 22.x |

## Project Structure

```
/
├── bin/                    # Symfony console and PHPUnit binaries
├── config/                 # Symfony configuration (services, routes, packages)
├── public/                 # Web entry point (index.php)
├── src/
│   ├── Controller/         # HTTP controllers
│   └── Kernel.php          # Application kernel
├── tests/                  # PHPUnit tests
├── _bmad/                  # BMAD Method installation (core + modules)
├── _bmad-output/           # BMAD artifacts output
│   ├── planning-artifacts/ # PRD, architecture, epics
│   └── implementation-artifacts/ # Stories, implementation docs
├── .claude/
│   ├── skills/             # BMAD skills (43 skills)
│   └── scripts/            # Custom automation scripts
├── composer.json
└── phpunit.dist.xml
```

## Architecture Decisions

### API Design
- RESTful endpoints
- JSON responses
- RFC 7807 Problem Details for error responses
- Semantic HTTP status codes

### Code Organization
- Three-layer architecture: Domain / Application / Infrastructure
- PSR-4 autoloading under `App\` namespace
- Tests mirror src structure under `App\Tests\`

### Testing Strategy
- PHPUnit for unit and functional tests
- TDD approach: write tests first
- Functional tests for HTTP endpoints using Symfony's test client

## Conventions

### Coding Standards
- PSR-12 coding style
- Type declarations on all parameters and return types
- Final classes by default
- Value objects for domain concepts

### Git Conventions
- Atomic commits: one logical change per commit
- Commit message format: `type: description`
  - Types: init, feat, fix, refactor, test, docs, setup, ext
- Branch naming: `feature/`, `fix/`, `refactor/`

### API Conventions
- Endpoints: `GET /resource`, `POST /resource`, `GET /resource/{id}`
- Error format: RFC 7807 (application/problem+json)
- Health check: `GET /health`

## Development Workflow

This project uses a hybrid workflow combining:

1. **BMAD Method** (phases 1-3): Analysis, Planning, Solutioning
2. **GSD technique** (phase 4-5): Fresh context per task via `claude -p`
3. **Anthropic article technique** (phase 6): QA evaluator with scoring loop
4. **Harness technique** (phase 7): Adversarial code review

## Dependencies

### Production
- symfony/framework-bundle: Core Symfony framework
- symfony/console: CLI commands
- symfony/dotenv: Environment variable management
- symfony/yaml: YAML configuration parsing
- symfony/runtime: Runtime component

### Development
- phpunit/phpunit: Testing framework
- symfony/test-pack: Symfony test utilities

## Environment

- **Local development:** `php -S localhost:8000 -t public/` or `symfony serve`
- **Tests:** `php bin/phpunit`
- **Console:** `php bin/console`
