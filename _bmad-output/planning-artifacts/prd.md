---
stepsCompleted: ['step-01-init', 'step-02-discovery', 'step-02b-vision', 'step-02c-executive-summary']
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
