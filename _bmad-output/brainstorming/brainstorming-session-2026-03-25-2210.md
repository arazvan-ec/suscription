---
stepsCompleted: [1, 2, 3, 4]
inputDocuments: []
session_topic: 'Disenar enBandeja-service: microservicio de notificaciones editoriales que consume eventos de dos CMS, programa envios para la fecha de publicacion, y orquesta campanas via Mailchimp API'
session_goals: 'Definir limites del servicio; Explorar flujo completo incluyendo publicacion futura; Identificar casos problematicos'
selected_approach: 'ai-recommended'
techniques_used: ['question-storming', 'morphological-analysis', 'chaos-engineering']
ideas_generated: [35]
context_file: '_bmad-output/project-context.md'
session_active: false
workflow_completed: true
---

# Brainstorming Session — enBandeja-service

## Session Overview

**Topic:** Disenar enBandeja-service — microservicio de notificaciones editoriales que consume eventos de dos CMS, programa envios para la fecha de publicacion, y orquesta campanas via Mailchimp API

**Goals:**
1. Definir limites claros del servicio (enBandeja vs Mailchimp vs fuera de scope v1)
2. Explorar el flujo completo del evento (incluyendo publicacion futura/programada)
3. Identificar casos problematicos y como manejarlos

### Context Guidance

_Proyecto Symfony 7.2 orientado a microservicios. Actualmente las notificaciones viven en Delorean (responsabilidad incorrecta). Hay dos CMS (legacy y nuevo) que necesitan un punto de entrada unico. Se mantiene Mailchimp para audiencias, envio, tracking y compliance. Dato clave: el envio debe programarse para la fecha de publicacion de la editorial, que puede ser futura._

### Session Setup

_Sesion facilitada por Mary (Business Analyst). Enfoque: explorar el dominio del servicio, sus limites, flujos y casos edge antes de pasar al product brief._

## Technique Selection

**Approach:** AI-Recommended Techniques
**Analysis Context:** Disenar microservicio de orquestacion de notificaciones con enfoque en limites, flujos y casos edge

**Recommended Techniques:**

- **Question Storming (deep):** Mapear todas las preguntas abiertas antes de disenar soluciones
- **Morphological Analysis (deep):** Descomponer el sistema en parametros y explorar combinaciones
- **Chaos Engineering (wild):** Romper deliberadamente el diseno para descubrir requisitos ocultos

**AI Rationale:** Secuencia que va de descubrir lo desconocido → explorar sistematicamente → stress-test. Ideal para migracion de arquitectura donde el dominio es conocido pero los edge cases no.

## Technique Execution Results

### Question Storming

**Foco:** Mapear el flujo completo del evento y descubrir lagunas de conocimiento.

**Descubrimientos clave:**

- **Datos del evento:** El evento `editorial.published` lleva solo `editorial_id`. enBandeja resuelve el resto llamando a otros servicios.
- **Cadena de dependencias:** enBandeja llama a editorial-service (titulo, url, fecha publicacion, journalist_id) y a journalist-service (nombre periodista, audience_id de Mailchimp).
- **Audiencia:** Cada periodista tiene su propia audience completa en Mailchimp. El audience_id vive en journalist-service.
- **Creacion de audiencias:** Hoy es manual. Plan futuro: handler async en journalist-service via RabbitMQ.
- **Contenido del email:** Texto generico + nombre periodista + link al articulo. HTML construido con Twig en enBandeja. Mailchimp solo entrega el HTML tal cual.
- **Unsubscribe y tracking:** Lo gestiona Mailchimp automaticamente.
- **Timing:** El envio siempre respeta la fecha de publicacion de la editorial. Margen de minutos aceptable.
- **Ambos CMS:** Publican exactamente el mismo evento `editorial.published` con el mismo schema.

### Morphological Analysis

**Foco:** Descomponer el sistema en parametros y tomar decisiones para cada uno.

**Matriz de decisiones:**

| Parametro | Decision |
|-----------|----------|
| Trigger | Evento `editorial.published` (mismo schema ambos CMS) |
| Almacenamiento | Tabla `campaigns` en BBDD propia de enBandeja |
| Timing | Worker consulta `scheduled_at <= NOW()` con status `scheduled` |
| Resolucion de datos | Lazy — al momento del envio, no al recibir el evento |
| Datos entrada | editorial-service + journalist-service |
| Contenido | HTML construido con Twig en enBandeja |
| Destino | Mailchimp API → audience completa del periodista |
| Validacion pre-envio | Comprobar que la editorial sigue publicada al momento de enviar |
| Resiliencia | Reintento + encolar si dependencia caida |

**Decision clave explorada:** Almacenamiento de campanas programadas.

- Descartado: Delayed message en RabbitMQ (sin visibilidad, dificil cancelar)
- Descartado: Symfony Scheduler (sin persistencia, se pierde si cae el worker)
- Elegido: Tabla `campaigns` en BBDD (visibilidad, cancelacion trivial, auditoria)

**Decision clave explorada:** Resolucion eager vs lazy.

- Elegido lazy porque: si el titulo cambia, se envia el actualizado. Si el periodista cambia de audiencia, se envia a la correcta. Si la editorial se despublica, se detecta al intentar resolver.

**Decision clave explorada:** Manejo de despublicacion.

- Descartado para v1: evento `editorial.unpublished` o `editorial.updated`
- Elegido: Validar estado de la editorial al momento del envio (ya lo tienes gratis con lazy)

### Chaos Engineering

**Foco:** Romper deliberadamente el diseno para descubrir requisitos ocultos.

**Escenarios y decisiones:**

| Escenario | Problema | Decision |
|-----------|----------|----------|
| Tormenta de eventos | Redactor publica/despublica 5 veces | Idempotente: ignora si ya existe campana `scheduled` para ese `editorial_id` |
| Mailchimp rate limit | 15 editoriales de golpe, Mailchimp devuelve 429 | Reaccionar al 429 con header `Retry-After` |
| editorial-service lento | Worker bloqueado esperando respuesta | Procesamiento paralelo + timeout 5 segundos |
| Periodista sin audiencia | `audience_id` es null | Error permanente: cancelar campana + log |
| Audiencia vacia | Mailchimp no acepta campana con 0 suscriptores | Error permanente: cancelar campana + log |
| Template Twig roto | Excepcion al renderizar HTML | Error permanente: cancelar directo, 0 reintentos |
| Mailchimp falla | 5xx, timeout, error de red | Error transitorio: hasta 3 reintentos, luego cola de errores + log critico + reintento manual |
| Duplicacion en Mailchimp | Respuesta se pierde, se reintenta, doble envio | Guardar `campaign_id` de Mailchimp en tabla, verificar antes de crear |
| Worker cae a mitad | Status queda en `sending`, se reprocesa al levantar | Reconciliacion al arrancar: consultar a Mailchimp si se envio o no |
| Consistencia temporal | Titulo cambia mientras el worker resuelve datos | Aceptable con margen de minutos |

**Clasificacion de errores descubierta:**

- **Errores transitorios** (Mailchimp caido, timeout, red) → reintenta hasta 3 veces
- **Errores permanentes** (sin audiencia, audiencia vacia, template roto) → cancela directo, 0 reintentos

## Idea Organization and Prioritization

### Tema 1: Flujo core del servicio
_Lo minimo que enBandeja necesita para funcionar._

- Consumir evento `editorial.published` de RabbitMQ
- Crear registro en tabla `campaigns` con `editorial_id` y `scheduled_at`
- Worker que recoge campanas con `scheduled_at <= NOW()` y status `scheduled`
- Resolver datos lazy: llamar a editorial-service y journalist-service
- Validar que la editorial sigue publicada
- Construir HTML con Twig (texto generico + nombre periodista + link)
- Enviar via Mailchimp API a la audience del periodista

### Tema 2: Modelo de datos
_Tabla campaigns como entidad central._

- Campos: id, editorial_id, scheduled_at, status, mailchimp_campaign_id, created_at, updated_at
- Status: scheduled → sending → done | cancelled | failed
- Idempotencia: unique constraint en editorial_id + status scheduled
- Guardar mailchimp_campaign_id para reconciliacion y evitar duplicados

### Tema 3: Resiliencia y manejo de errores
_Como el sistema se recupera de fallos._

- Clasificacion binaria: errores transitorios (reintenta) vs permanentes (cancela)
- Hasta 3 reintentos para errores transitorios
- Cola de errores + log critico para campanas que agotan reintentos
- Reintento manual desde cola de errores
- Timeout de 5 segundos para llamadas a servicios externos
- Reaccionar a 429 de Mailchimp con Retry-After
- Reconciliacion al arrancar worker: campanas en status `sending` se verifican contra Mailchimp

### Tema 4: Integraciones externas
_Contratos con otros servicios._

- editorial-service: datos del articulo (titulo, url, fecha publicacion, journalist_id, estado)
- journalist-service: datos del periodista (nombre, audience_id de Mailchimp)
- Mailchimp Marketing API: crear campana, setear contenido HTML, enviar a audience
- RabbitMQ: consumir evento `editorial.published`

### Tema 5: Fuera de scope v1
_Explicitamente descartado para la primera version._

- Trigger B: newsletters manuales desde CMS
- Evento editorial.unpublished o editorial.updated
- Creacion automatica de audiencias en Mailchimp (futuro en journalist-service)
- Templates en Mailchimp (el HTML se construye en enBandeja)
- Dashboard de analytics propio (usa Mailchimp)
- Webhooks de Mailchimp para tracking de deliveries
- Gestion propia de suscriptores/audiencias

## Session Summary and Insights

**Logros de la sesion:**

- Flujo completo mapeado desde evento hasta entrega de email
- 9 escenarios de fallo analizados con decision para cada uno
- Clasificacion clara de errores (transitorios vs permanentes)
- Limites del servicio definidos (que hace enBandeja vs que delega a Mailchimp)
- Scope v1 acotado: solo Trigger A (editorial automatica)
- Decisiones de arquitectura: almacenamiento en BBDD, resolucion lazy, validacion al enviar

**Insight clave de la sesion:** La decision de resolver datos lazy simplifica enormemente el sistema — elimina la necesidad de reaccionar a eventos de actualizacion/despublicacion porque la validacion al enviar te protege gratis. Menos eventos, menos complejidad, misma seguridad.

**Siguiente paso recomendado:** Crear el Product Brief con todo este material para pasar a las fases de PRD y arquitectura.
