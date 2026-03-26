---
stepsCompleted: [1, 2, 3, 4, 5, 6, 7, 8]
inputDocuments:
  - _bmad-output/planning-artifacts/prd.md
  - _bmad-output/planning-artifacts/product-brief-enbandeja-service.md
  - _bmad-output/project-context.md
workflowType: 'architecture'
project_name: 'enBandeja-service'
status: 'complete'
date: '2026-03-25'
---

# Architecture Decision Document — enBandeja-service

## Project Context Analysis

### Requirements Overview

**Functional Requirements:** 17 FRs agrupados en 5 areas:
- Ingesta de eventos (FR-100): consumer RabbitMQ, idempotencia, creacion de campanas
- Resolucion de datos (FR-200): llamadas a editorial-service y journalist-service, validacion
- Construccion y envio (FR-300): Twig HTML, Mailchimp API, deduplicacion
- Programacion y worker (FR-400): polling periodico, procesamiento paralelo, reconciliacion
- Manejo de errores (FR-500): clasificacion transitorios/permanentes, reintentos, cola de errores

**Non-Functional Requirements:** 9 NFRs:
- Rendimiento: < 5 min margen, 5s timeout por servicio
- Resiliencia: persistencia en BBDD, reconciliacion, no perdida de campanas
- Observabilidad: logs criticos e informativos, tabla consultable
- Seguridad: credenciales en env vars

**Complejidad:** Media — integraciones externas, programacion temporal, manejo de errores

### Technical Constraints

- Symfony 7.2 (PHP 8.4+)
- Doctrine ORM
- Symfony Messenger para RabbitMQ
- Twig para templates de email
- Mailchimp Marketing API v3

### Cross-Cutting Concerns

- **Idempotencia:** En ingesta de eventos y en creacion de campanas en Mailchimp
- **Clasificacion de errores:** Transitorios vs permanentes afecta toda la cadena
- **Logging:** Critico para failed, informativo para cancelled, en todos los flujos

## Core Architectural Decisions

### ADR-001: Symfony Messenger como transport layer

**Contexto:** Necesitamos consumir eventos `editorial.published` de RabbitMQ.
**Decision:** Usar Symfony Messenger con AMQP transport.
**Razon:** Nativo en Symfony 7.2, soporta acknowledgement manual, retry policies, y dead letter queues. No anade dependencia externa.
**Consecuencias:** El consumer es un message handler estandar de Symfony. La configuracion de retry y DLQ va en `messenger.yaml`.

### ADR-002: Doctrine ORM para persistencia

**Contexto:** Necesitamos persistir campanas con estados y timestamps.
**Decision:** Doctrine ORM con migraciones.
**Razon:** Estandar en Symfony, soporta UUID, enums, y timestamps. El equipo ya lo conoce.
**Consecuencias:** La tabla `campaigns` se define como entidad Doctrine. Los queries del worker usan DQL o QueryBuilder.

### ADR-003: Resolucion lazy de datos

**Contexto:** Los datos de la editorial y el periodista pueden cambiar entre la creacion de la campana y el envio.
**Decision:** Resolver datos al momento del envio, no al recibir el evento.
**Razon:** Garantiza datos frescos. Elimina la necesidad de eventos de actualizacion/despublicacion. Simplifica el modelo (la campana solo guarda `editorial_id`).
**Consecuencias:** El worker hace 2 llamadas HTTP por campana. Si un servicio esta caido, la campana se reintenta. Aceptable con timeout de 5s y procesamiento paralelo.

### ADR-004: HTTP client para integraciones internas

**Contexto:** enBandeja necesita datos de editorial-service y journalist-service.
**Decision:** Symfony HttpClient con timeout de 5 segundos.
**Razon:** Sincrono, simple, predecible. No necesitamos async porque el worker ya es un proceso background.
**Consecuencias:** Cada servicio tiene su propio client configurado en `services.yaml`. Timeout y base_uri configurables por entorno.

### ADR-005: Mailchimp Marketing API v3

**Contexto:** Necesitamos crear campanas y enviar emails a audiences.
**Decision:** Usar la Marketing API v3 directamente via HTTP client (sin SDK).
**Razon:** Solo necesitamos 3-4 endpoints. Un SDK anade dependencia innecesaria. El HTTP client de Symfony es suficiente.
**Consecuencias:** Crear un `MailchimpClient` propio en la capa de infraestructura que encapsula las llamadas. Si Mailchimp cambia la API, solo cambia este archivo.

### ADR-006: Worker como Symfony Command con cron

**Contexto:** Necesitamos un proceso que recoja campanas programadas periodicamente.
**Decision:** Symfony Command ejecutado por cron cada minuto.
**Razon:** Simple, visible, facil de monitorizar. No necesitamos un daemon permanente para el volumen esperado.
**Consecuencias:** El cron ejecuta `php bin/console app:send-campaigns`. Si la ejecucion anterior no ha terminado, un lock file previene ejecucion concurrente.

### ADR-007: Clasificacion de errores en el dominio

**Contexto:** No todos los errores deben tratarse igual. Un Mailchimp 500 es reintentable; un periodista sin audiencia no lo es.
**Decision:** Excepciones de dominio: `TransientErrorException` y `PermanentErrorException`.
**Razon:** La clasificacion vive en el dominio, no en la infraestructura. El worker solo necesita saber si es transitorio o permanente.
**Consecuencias:** Cada integacion (Mailchimp, editorial-service, journalist-service) traduce sus errores HTTP a excepciones de dominio. El worker tiene un solo try/catch con dos ramas.

## Implementation Patterns & Consistency Rules

### Naming Patterns

**Base de datos:**
- Tabla: `campaigns` (plural, snake_case)
- Campos: `editorial_id`, `scheduled_at`, `mailchimp_campaign_id` (snake_case)

**Clases PHP:**
- Entidades: `Campaign` (singular, PascalCase)
- Repositorios: `CampaignRepository`
- Handlers: `EditorialPublishedHandler`
- Commands: `SendCampaignsCommand`
- Clients: `MailchimpClient`, `EditorialServiceClient`, `JournalistServiceClient`
- Exceptions: `TransientErrorException`, `PermanentErrorException`

**Eventos:**
- Messages de Messenger: `EditorialPublished`

### Structure Patterns

Tres capas: Domain / Application / Infrastructure.

- **Domain:** Entidades, value objects, excepciones de dominio, interfaces de repositorio
- **Application:** Handlers de mensajes, servicios de aplicacion (orquestacion)
- **Infrastructure:** Clients HTTP, Doctrine repositories, Twig rendering, Symfony commands

### Format Patterns

**Logs:**
```
[CRITICAL] Campaign failed after 3 retries | editorial_id=abc123 | error_type=transient | error=Mailchimp returned 500
[INFO] Campaign cancelled | editorial_id=abc123 | reason=editorial_not_published
[INFO] Campaign sent | editorial_id=abc123 | mailchimp_campaign_id=mc_xyz
```

### Error Handling Pattern

```
try {
    // procesar campana
} catch (PermanentErrorException $e) {
    // cancelar, log, no retry
} catch (TransientErrorException $e) {
    // incrementar retry, volver a scheduled o failed si >= 3
}
```

## Project Structure & Boundaries

```
src/
├── Domain/
│   ├── Campaign.php                    # Entidad Doctrine
│   ├── CampaignStatus.php              # Enum: scheduled, sending, done, cancelled, failed
│   ├── ErrorType.php                   # Enum: transient, permanent
│   ├── CampaignRepositoryInterface.php # Interface del repositorio
│   ├── TransientErrorException.php
│   └── PermanentErrorException.php
│
├── Application/
│   ├── Handler/
│   │   └── EditorialPublishedHandler.php  # Consumer del evento RabbitMQ
│   ├── Service/
│   │   ├── CampaignProcessor.php          # Orquesta: resolver datos, construir HTML, enviar
│   │   └── CampaignReconciler.php         # Reconcilia campanas 'sending' al arrancar
│   └── Message/
│       └── EditorialPublished.php         # Message class para Messenger
│
├── Infrastructure/
│   ├── Client/
│   │   ├── EditorialServiceClient.php     # HTTP client para editorial-service
│   │   ├── JournalistServiceClient.php    # HTTP client para journalist-service
│   │   └── MailchimpClient.php            # HTTP client para Mailchimp API
│   ├── Repository/
│   │   └── DoctrineCampaignRepository.php # Implementacion Doctrine del repositorio
│   ├── Rendering/
│   │   └── TwigEmailRenderer.php          # Construye HTML con Twig
│   └── Command/
│       └── SendCampaignsCommand.php       # Symfony Command para el cron worker
│
templates/
└── email/
    └── editorial_notification.html.twig   # Template del email

config/
├── packages/
│   ├── messenger.yaml                     # Config de Messenger + AMQP transport
│   └── doctrine.yaml                      # Config de Doctrine
└── services.yaml                          # Wiring de servicios, clients HTTP

migrations/
└── Version*.php                           # Migracion para crear tabla campaigns
```

### Architectural Boundaries

- **Domain** no importa nada de Infrastructure ni de Symfony.
- **Application** importa Domain pero no Infrastructure (usa interfaces).
- **Infrastructure** implementa las interfaces de Domain.
- **SendCampaignsCommand** es el unico entry point del worker. Llama a `CampaignProcessor`.
- **EditorialPublishedHandler** es el unico entry point del consumer. Crea campanas via repositorio.

### Integration Points

| Punto | Protocolo | Direccion |
|-------|-----------|-----------|
| RabbitMQ → EditorialPublishedHandler | AMQP via Messenger | Entrada |
| CampaignProcessor → EditorialServiceClient | HTTP GET | Salida |
| CampaignProcessor → JournalistServiceClient | HTTP GET | Salida |
| CampaignProcessor → MailchimpClient | HTTP POST/PUT | Salida |
| Cron → SendCampaignsCommand | CLI | Entrada |

## Architecture Validation Results

### Coherence Validation

- **ADR-001 + ADR-006:** Messenger consumer y cron worker son dos entry points independientes. No compiten. ✅
- **ADR-003 + ADR-004:** Resolucion lazy requiere HTTP calls en el worker. HttpClient con timeout lo soporta. ✅
- **ADR-005 + ADR-007:** MailchimpClient traduce errores HTTP a excepciones de dominio. Coherente. ✅
- **Tres capas + Estructura:** La estructura de directorios refleja las capas. Sin violaciones de dependencia. ✅

### Requirements Coverage

| Requirement Group | Covered by |
|-------------------|-----------|
| FR-100 (Ingesta) | EditorialPublishedHandler + CampaignRepository |
| FR-200 (Resolucion) | CampaignProcessor + EditorialServiceClient + JournalistServiceClient |
| FR-300 (Envio) | CampaignProcessor + TwigEmailRenderer + MailchimpClient |
| FR-400 (Worker) | SendCampaignsCommand + CampaignProcessor + CampaignReconciler |
| FR-500 (Errores) | TransientErrorException + PermanentErrorException + CampaignProcessor |
| NFR-01/02 (Rendimiento) | Cron cada minuto + timeout 5s |
| NFR-03/04/05 (Resiliencia) | Doctrine persistence + CampaignReconciler |
| NFR-06/07/08 (Observabilidad) | Monolog structured logging + tabla campaigns |
| NFR-09 (Seguridad) | .env vars para credenciales |

### Architecture Readiness Assessment

**PASS.** Todas las decisiones son coherentes, todos los requisitos tienen cobertura, y la estructura es implementable con Symfony 7.2 estandar. No hay gaps detectados.
