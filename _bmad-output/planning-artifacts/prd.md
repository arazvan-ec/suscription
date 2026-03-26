---
stepsCompleted: ['step-01-init', 'step-02-discovery', 'step-02b-vision', 'step-02c-executive-summary', 'step-03-success']
inputDocuments:
  - _bmad-output/planning-artifacts/product-brief-enbandeja-service.md
  - _bmad-output/brainstorming/brainstorming-session-2026-03-25-2210.md
  - _bmad-output/project-context.md
workflowType: 'prd'
---

# Product Requirements Document - enBandeja-service

**Author:** User
**Date:** 2026-03-25

## Executive Summary

enBandeja-service es un microservicio de orquestacion de notificaciones editoriales. Cuando un periodista publica un articulo, el servicio consume el evento `editorial.published` via RabbitMQ, programa la campana para la fecha de publicacion, y en el momento del envio resuelve los datos frescos del articulo y del periodista para construir y despachar el email a traves de la API de Mailchimp.

Hoy esta responsabilidad vive en Delorean, un proyecto de renderizado editorial. El flujo actual depende de polling a MySQL del CMS legacy, lo que acopla el envio de notificaciones a un CMS concreto y a un proyecto que no deberia gestionarlas. Con dos CMS activos, este acoplamiento es insostenible.

enBandeja reemplaza ese flujo con un servicio dedicado y event-driven que da a cualquier CMS — actual o futuro — un punto de entrada unico: publicar un evento con el `editorial_id`. El servicio se encarga del resto.

### Que Hace Esto Especial

- **Un evento, cualquier CMS.** Desacoplamiento real: ningun CMS sabe nada de emails, audiencias ni Mailchimp. Solo publica un evento.
- **Datos siempre frescos.** La resolucion de datos ocurre al enviar, no al recibir el evento. Nunca se envia un email con un titulo obsoleto, a una audiencia que ya no existe, o sobre un articulo despublicado.
- **Visibilidad donde hoy hay un agujero negro.** Cada campana queda registrada con su estado (scheduled, sending, done, failed, cancelled). El responsable de producto puede consultar que se envio, que fallo y por que.

## Clasificacion del Proyecto

- **Tipo:** API/Microservicio backend (Symfony 7.2)
- **Dominio:** Media/Publishing — notificaciones editoriales
- **Complejidad:** Media — integraciones externas (editorial-service, journalist-service, Mailchimp API), programacion temporal, manejo de errores
- **Contexto:** Greenfield — servicio nuevo que reemplaza logica extraida de Delorean

## Success Criteria

### User Success

- **Redactores:** La experiencia no cambia. Publican un articulo y el email sale automaticamente sin intervencion adicional. No perciben la migracion.
- **Suscriptores:** Reciben el mismo email que antes — texto generico, nombre del periodista, enlace al articulo. Sin cambios en contenido ni frecuencia.
- **Responsable de producto:** Puede consultar el estado de cualquier campana (pendiente, enviada, fallida, cancelada) directamente en la base de datos.

### Business Success

- **Delorean liberado.** La responsabilidad de notificaciones se elimina completamente de Delorean tras el corte. Zero logica de emails en Delorean.
- **Dos CMS, un flujo.** Ambos CMS publican `editorial.published` y enBandeja los procesa identicamente.
- **Zero emails perdidos.** Toda editorial publicada con un periodista que tiene audiencia genera una campana exitosa en Mailchimp.

### Technical Success

- **Idempotencia verificada.** Eventos duplicados no generan campanas duplicadas.
- **Resiliencia.** Errores transitorios se recuperan en 3 reintentos. Errores permanentes se cancelan y loguean sin gastar reintentos.
- **Reconciliacion.** El worker detecta y resuelve campanas en estado inconsistente (`sending`) al arrancar.
- **Datos frescos.** El worker valida el estado de la editorial al enviar — si esta despublicada, cancela sin enviar.

### Measurable Outcomes

- 100% de editoriales publicadas con periodista con audiencia generan una campana
- 0 emails duplicados por idempotencia en evento + verificacion contra Mailchimp
- < 5 minutos de margen entre la fecha de publicacion y el envio efectivo
- 0 emails enviados sobre articulos despublicados
- 100% de errores criticos registrados en log para accion manual

## Product Scope

### MVP - Minimum Viable Product

- Consumer de `editorial.published` via RabbitMQ
- Tabla `campaigns` con programacion por `scheduled_at`
- Worker con resolucion lazy (editorial-service + journalist-service)
- Construccion HTML con Twig
- Envio via Mailchimp Marketing API
- Idempotencia por `editorial_id`
- Clasificacion de errores (transitorios vs permanentes)
- Reintentos (max 3), cola de errores, log critico
- Reconciliacion al arrancar el worker
- Script de migracion en legacy para drenar pendientes

### Growth Features (Post-MVP)

- Newsletters manuales (Trigger B) — API para crear y programar campanas
- Dashboard de monitoreo y alertas
- Creacion automatica de audiencias en journalist-service
- Webhooks de Mailchimp para tracking de deliveries (open, click, bounce)

### Vision (Futuro)

- Servicio central de comunicacion: push notifications, notificaciones in-app
- Preferencias granulares de suscriptor (frecuencia, temas)
- A/B testing de contenido
- Analytics de engagement por periodista y por tipo de contenido
