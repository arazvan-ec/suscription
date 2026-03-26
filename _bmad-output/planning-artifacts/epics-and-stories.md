---
stepsCompleted: ['init', 'epics', 'stories']
inputDocuments:
  - _bmad-output/planning-artifacts/prd.md
  - _bmad-output/planning-artifacts/architecture.md
workflowType: 'epics-and-stories'
---

# Epics and Stories — enBandeja-service

## Epic 1: Fundacion del Servicio

**Objetivo:** Crear el proyecto Symfony con la estructura de capas, entidad Campaign, y migracion de base de datos.

### Story 1.1: Crear entidad Campaign y migracion

**Como** desarrollador
**Quiero** la entidad Campaign con todos sus campos y la migracion de base de datos
**Para** tener la persistencia lista antes de implementar la logica de negocio

**Acceptance Criteria:**
- Entidad `Campaign` con campos: id (UUID), editorial_id, scheduled_at, status (enum), mailchimp_campaign_id (nullable), retry_count, error_type (nullable enum), error_message (nullable), created_at, updated_at
- Enum `CampaignStatus`: scheduled, sending, done, cancelled, failed
- Enum `ErrorType`: transient, permanent
- `CampaignRepositoryInterface` en Domain con metodos: findScheduledByEditorialId, save
- `DoctrineCampaignRepository` en Infrastructure implementando la interface
- Migracion de Doctrine que crea la tabla `campaigns`
- Unique constraint en `editorial_id` + status `scheduled`
- Tests unitarios para la entidad (transiciones de estado validas)

**Dependencias:** Ninguna
**Estimacion:** S

---

### Story 1.2: Crear excepciones de dominio

**Como** desarrollador
**Quiero** excepciones `TransientErrorException` y `PermanentErrorException`
**Para** clasificar errores de forma consistente en toda la aplicacion

**Acceptance Criteria:**
- `TransientErrorException` en Domain con mensaje y codigo opcional
- `PermanentErrorException` en Domain con mensaje y razon
- Tests unitarios que verifican que son instanciables y extienden la jerarquia correcta

**Dependencias:** Ninguna
**Estimacion:** XS

---

### Story 1.3: Estructura de proyecto tres capas

**Como** desarrollador
**Quiero** la estructura de directorios Domain/Application/Infrastructure creada
**Para** que las siguientes stories tengan donde colocar su codigo

**Acceptance Criteria:**
- Directorios creados: `src/Domain/`, `src/Application/Handler/`, `src/Application/Service/`, `src/Application/Message/`, `src/Infrastructure/Client/`, `src/Infrastructure/Repository/`, `src/Infrastructure/Rendering/`, `src/Infrastructure/Command/`
- Directorio `templates/email/` creado
- Autoload PSR-4 verificado
- `php bin/phpunit` sigue pasando (0 tests o los existentes)

**Dependencias:** Ninguna
**Estimacion:** XS

---

## Epic 2: Ingesta de Eventos

**Objetivo:** Consumir `editorial.published` de RabbitMQ, crear campana en BBDD, y despachar mensaje delayed.

### Story 2.1: Configurar Symfony Messenger con RabbitMQ

**Como** desarrollador
**Quiero** Messenger configurado con AMQP transport y las colas necesarias
**Para** poder consumir eventos y despachar mensajes delayed

**Acceptance Criteria:**
- `messenger.yaml` configurado con AMQP transport
- Dos transports: `editorial_published` (cola de entrada) y `send_campaign` (cola delayed)
- Message classes: `EditorialPublished` (con `editorial_id`) y `SendCampaign` (con `campaign_id`)
- Routing configurado: EditorialPublished → editorial_published transport, SendCampaign → send_campaign transport
- Config de Supervisor para ambos consumers en el README o docs
- Verificar que `messenger:consume` arranca sin errores

**Dependencias:** Story 1.3
**Estimacion:** M

---

### Story 2.2: Implementar EditorialPublishedHandler

**Como** sistema
**Quiero** que al recibir un evento `editorial.published` se cree una campana y se programe el envio
**Para** iniciar el flujo de notificacion automaticamente

**Acceptance Criteria:**
- `EditorialPublishedHandler` consume mensajes `EditorialPublished`
- Verifica idempotencia: si ya existe campana `scheduled` para ese `editorial_id`, ignora
- Llama a editorial-service para obtener `scheduled_at` (fecha de publicacion)
- Crea registro en `campaigns` con status `scheduled`
- Calcula delay: `max(0, scheduled_at - now)` en milisegundos
- Despacha `SendCampaign(campaign_id)` con delay via Messenger stamp
- Test funcional: enviar mensaje → verificar campana creada en BBDD
- Test funcional: enviar mensaje duplicado → verificar que no crea segunda campana
- Test: si editorial-service falla → TransientErrorException (Messenger reintenta)

**Dependencias:** Story 1.1, Story 1.2, Story 2.1
**Estimacion:** L

---

## Epic 3: Integraciones Externas

**Objetivo:** Crear los HTTP clients para editorial-service, journalist-service, y Mailchimp API.

### Story 3.1: EditorialServiceClient

**Como** sistema
**Quiero** un client HTTP para editorial-service
**Para** resolver datos de la editorial (titulo, URL, fecha, journalist_id, estado)

**Acceptance Criteria:**
- `EditorialServiceClient` en Infrastructure/Client
- Metodo `getEditorial(string $editorialId): EditorialData` (value object con titulo, url, scheduledAt, journalistId, isPublished)
- Timeout de 5 segundos configurado
- Si HTTP 4xx → `PermanentErrorException`
- Si HTTP 5xx o timeout → `TransientErrorException`
- Base URL configurable via env var `EDITORIAL_SERVICE_URL`
- Tests unitarios con HTTP client mock

**Dependencias:** Story 1.2, Story 1.3
**Estimacion:** M

---

### Story 3.2: JournalistServiceClient

**Como** sistema
**Quiero** un client HTTP para journalist-service
**Para** resolver datos del periodista (nombre, audience_id)

**Acceptance Criteria:**
- `JournalistServiceClient` en Infrastructure/Client
- Metodo `getJournalist(string $journalistId): JournalistData` (value object con nombre, audienceId nullable)
- Timeout de 5 segundos configurado
- Si HTTP 4xx → `PermanentErrorException`
- Si HTTP 5xx o timeout → `TransientErrorException`
- Base URL configurable via env var `JOURNALIST_SERVICE_URL`
- Tests unitarios con HTTP client mock

**Dependencias:** Story 1.2, Story 1.3
**Estimacion:** M

---

### Story 3.3: MailchimpClient

**Como** sistema
**Quiero** un client HTTP para Mailchimp Marketing API v3
**Para** crear campanas y enviar emails a audiences

**Acceptance Criteria:**
- `MailchimpClient` en Infrastructure/Client
- Metodo `createCampaign(string $audienceId, string $subject, string $html): string` (retorna campaign_id)
- Metodo `sendCampaign(string $campaignId): void`
- Metodo `getCampaignStatus(string $campaignId): string` (para reconciliacion)
- Si HTTP 429 → `TransientErrorException` con info del header Retry-After
- Si HTTP 5xx → `TransientErrorException`
- Si HTTP 4xx → `PermanentErrorException`
- API key configurable via env var `MAILCHIMP_API_KEY`
- Server prefix configurable via env var `MAILCHIMP_SERVER_PREFIX`
- Tests unitarios con HTTP client mock

**Dependencias:** Story 1.2, Story 1.3
**Estimacion:** L

---

## Epic 4: Construccion y Envio

**Objetivo:** Implementar el flujo completo de envio: resolver datos, construir HTML, enviar via Mailchimp.

### Story 4.1: TwigEmailRenderer

**Como** sistema
**Quiero** construir el HTML del email con Twig
**Para** generar el contenido de la notificacion editorial

**Acceptance Criteria:**
- `TwigEmailRenderer` en Infrastructure/Rendering
- Metodo `render(string $journalistName, string $articleTitle, string $articleUrl): string`
- Template `templates/email/editorial_notification.html.twig`
- Template incluye: texto generico, nombre del periodista, enlace al articulo
- Si el template tiene error de sintaxis → `PermanentErrorException`
- Tests unitarios verificando HTML generado correctamente

**Dependencias:** Story 1.2, Story 1.3
**Estimacion:** S

---

### Story 4.2: CampaignProcessor — flujo completo de envio

**Como** sistema
**Quiero** un servicio que orqueste todo el flujo de envio de una campana
**Para** centralizar la logica de resolucion de datos, construccion de HTML, y envio

**Acceptance Criteria:**
- `CampaignProcessor` en Application/Service
- Metodo `process(Campaign $campaign): void`
- Flujo:
  1. Llama a EditorialServiceClient → obtiene datos
  2. Si editorial no publicada → cancela campana (status `cancelled`), log info
  3. Llama a JournalistServiceClient → obtiene datos
  4. Si audience_id null → cancela campana (error permanente, status `cancelled`), log info
  5. Llama a TwigEmailRenderer → construye HTML
  6. Si mailchimp_campaign_id es null → crea campana en Mailchimp, guarda id
  7. Envia via MailchimpClient
  8. Status `done`
- `TransientErrorException` propaga al handler (Messenger gestiona retry)
- `PermanentErrorException` → cancela campana directamente
- Tests unitarios con mocks de todos los clients
- Tests para cada escenario: happy path, editorial despublicada, sin audiencia, error transitorio, error permanente

**Dependencias:** Story 3.1, Story 3.2, Story 3.3, Story 4.1
**Estimacion:** XL

---

### Story 4.3: SendCampaignHandler

**Como** sistema
**Quiero** que al recibir un mensaje `SendCampaign` se procese la campana
**Para** ejecutar el envio cuando el delay de RabbitMQ expira

**Acceptance Criteria:**
- `SendCampaignHandler` consume mensajes `SendCampaign`
- Carga campana de BBDD por `campaign_id`
- Si status != `scheduled` → ignora (log info)
- Cambia status a `sending`
- Llama a `CampaignProcessor::process()`
- Si `TransientErrorException`: incrementa retry_count
  - Si retry_count < 3 → vuelve a `scheduled`, re-despacha `SendCampaign` sin delay
  - Si retry_count >= 3 → status `failed`, log critico
- Si `PermanentErrorException` → ya fue manejada por CampaignProcessor
- Tests funcionales para cada escenario

**Dependencias:** Story 4.2, Story 2.1
**Estimacion:** L

---

## Epic 5: Reconciliacion y Operaciones

**Objetivo:** Manejar estados inconsistentes y dar herramientas operativas.

### Story 5.1: CampaignReconciler

**Como** operador
**Quiero** que al arrancar se reconcilien campanas en estado inconsistente
**Para** recuperar campanas que quedaron en `sending` por un crash

**Acceptance Criteria:**
- `CampaignReconciler` en Application/Service
- Busca campanas con status `sending`
- Para cada una: consulta Mailchimp por `mailchimp_campaign_id`
  - Si Mailchimp confirma envio → status `done`
  - Si Mailchimp no tiene la campana → vuelve a `scheduled`, despacha `SendCampaign` sin delay
  - Si `mailchimp_campaign_id` es null → vuelve a `scheduled`, despacha `SendCampaign` sin delay
- `ReconcileCampaignsCommand` ejecuta la reconciliacion
- Se puede invocar manualmente: `php bin/console app:reconcile-campaigns`
- Tests unitarios para cada escenario de reconciliacion

**Dependencias:** Story 3.3, Story 1.1
**Estimacion:** M

---

## Sprint Plan Sugerido

### Sprint 1: Fundacion + Integraciones
- Story 1.3: Estructura de proyecto (XS)
- Story 1.1: Entidad Campaign (S)
- Story 1.2: Excepciones de dominio (XS)
- Story 3.1: EditorialServiceClient (M)
- Story 3.2: JournalistServiceClient (M)

### Sprint 2: Messenger + Ingesta
- Story 2.1: Configurar Messenger (M)
- Story 2.2: EditorialPublishedHandler (L)
- Story 3.3: MailchimpClient (L)

### Sprint 3: Envio + Operaciones
- Story 4.1: TwigEmailRenderer (S)
- Story 4.2: CampaignProcessor (XL)
- Story 4.3: SendCampaignHandler (L)
- Story 5.1: CampaignReconciler (M)
