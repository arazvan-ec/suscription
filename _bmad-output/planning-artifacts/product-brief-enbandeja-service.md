---
title: "Product Brief: enBandeja-service"
status: "final"
created: "2026-03-25"
updated: "2026-03-25"
inputs:
  - _bmad-output/brainstorming/brainstorming-session-2026-03-25-2210.md
  - _bmad-output/project-context.md
---

# Product Brief: enBandeja-service

## Executive Summary

Cada vez que un periodista publica un articulo, los suscriptores de ese periodista reciben un email de notificacion. Hoy eso funciona, pero vive dentro de Delorean — un proyecto cuya responsabilidad es renderizar editoriales, no enviar emails. El flujo depende de polling a una tabla MySQL del CMS legacy, lo que hace imposible que el nuevo CMS use el mismo mecanismo sin replicar el hack.

enBandeja-service es un microservicio dedicado a la orquestacion de notificaciones editoriales. Consume eventos `editorial.published` de cualquier CMS via RabbitMQ, resuelve los datos necesarios en el momento del envio, y despacha campanas a traves de la API de Mailchimp. Separa una responsabilidad que nunca debio vivir en Delorean y da a ambos CMS un punto de entrada unico y desacoplado.

El momento es ahora porque la convivencia de dos CMS hace insostenible el acoplamiento actual. Cada nuevo CMS o cambio en el legacy requiere adaptar un flujo que no fue disenado para ser extensible.

## El Problema

Un redactor publica un articulo en el CMS legacy. El CMS escribe un registro en MySQL indicando que hay que notificar. Un proceso separado lee esa tabla (polling), genera un mensaje, y Delorean — cuya funcion es pintar editoriales — consume ese mensaje y envia el email a la audiencia del periodista en Mailchimp.

Problemas concretos:

- **Responsabilidad incorrecta.** Delorean envia emails. No deberia. Es un proyecto de renderizado de contenido editorial con una responsabilidad parasita que crece.
- **Acoplamiento con MySQL del legacy.** El nuevo CMS no puede usar este flujo sin escribir en la misma tabla del legacy o inventar otro mecanismo igual de fragil.
- **Polling.** No hay garantias de entrega, no escala, y no hay visibilidad de que esta pendiente o que fallo.
- **Un CMS, un hack.** Cada CMS necesita su propia integracion. Con dos CMS ya es un problema. Con tres seria inmanejable.

El responsable de producto no tiene visibilidad de que emails se envian, cuales fallan, ni por que. Si algo falla, se entera por los usuarios.

## La Solucion

enBandeja-service consume eventos `editorial.published` de RabbitMQ — el mismo evento, con el mismo schema, independientemente de que CMS lo publique. Al recibir el evento, crea una campana programada en su base de datos. Cuando llega la hora de publicacion, un worker resuelve los datos frescos (titulo, URL del articulo, nombre y audiencia del periodista), construye el HTML del email con Twig, y lo envia a traves de la API de Mailchimp.

Mailchimp sigue haciendo lo que hace bien: gestionar audiencias, suscriptores, deliverability, tracking y compliance. enBandeja solo orquesta: decide cuando enviar, a quien, y con que contenido.

Los datos se resuelven en el momento del envio, no cuando se recibe el evento. Esto garantiza que nunca se envia un email con un titulo que ya cambio, a una audiencia que ya no existe, o sobre un articulo que se despublico. Sin caches obsoletos, sin sorpresas.

## Que Hace Diferente a Este Enfoque

- **Desacoplamiento real.** Cualquier CMS solo necesita publicar un evento. No sabe nada de emails, audiencias ni Mailchimp.
- **Datos siempre frescos.** Nunca se envia un email con datos obsoletos. Si el titulo cambio, se envia el nuevo. Si la editorial se despublico, se cancela. Sin logica extra, sin eventos adicionales.
- **Visibilidad.** La tabla de campanas es un registro de todo: que se programo, que se envio, que fallo y por que. El responsable de producto puede consultar el estado en cualquier momento.
- **Idempotencia y resiliencia.** Eventos duplicados se ignoran. Errores transitorios se reintentan. Errores permanentes se cancelan sin gastar reintentos. El worker se reconcilia con Mailchimp al arrancar.

## Quien lo Usa

**Redactores.** Publican articulos como siempre. No interactuan con enBandeja. El email sale automaticamente.

**Suscriptores de periodistas.** Reciben un email con un texto generico, el nombre del periodista y un enlace al articulo. La experiencia no cambia.

**Responsable de producto.** Gana visibilidad sobre el estado de las notificaciones: campanas pendientes, enviadas, fallidas. Hoy tiene un agujero negro; con enBandeja tiene una tabla consultable.

## Criterios de Exito

- **Paridad funcional.** Los emails salen como antes — mismo contenido, misma audiencia, misma experiencia para el suscriptor.
- **Delorean liberado.** La responsabilidad de notificaciones se elimina completamente de Delorean.
- **Dos CMS, un flujo.** Ambos CMS publican el mismo evento y enBandeja los trata igual.
- **Visibilidad operativa.** El responsable de producto puede consultar el estado de cualquier campana. Errores criticos se loguean para accion inmediata.
- **Resiliencia.** Ningun email se pierde por fallos transitorios (hasta 3 reintentos). Ningun email se envia dos veces (idempotencia + verificacion contra Mailchimp).

## Scope

**v1 incluye:**
- Consumir `editorial.published` de ambos CMS
- Tabla `campaigns` con programacion por fecha de publicacion
- Worker con resolucion lazy de datos (editorial-service + journalist-service)
- Construccion de HTML con Twig
- Envio via Mailchimp Marketing API
- Clasificacion de errores (transitorios vs permanentes)
- Reintentos, cola de errores, reconciliacion al arrancar
- Idempotencia por editorial_id
- Migracion en corte limpio (dia X se apaga Delorean, se enciende enBandeja). Script de migracion en legacy para drenar pendientes
- Log critico para errores que requieren intervencion manual

**v1 NO incluye:**
- Newsletters manuales (Trigger B)
- Creacion automatica de audiencias en Mailchimp
- Gestion propia de suscriptores o audiencias
- Dashboard de analytics o monitoreo (v2)
- Webhooks de Mailchimp para tracking de deliveries
- Eventos de despublicacion o actualizacion (la validacion al enviar lo cubre)
- Preferencias granulares de suscriptor (frecuencia, temas)
- A/B testing de contenido

## Vision

Si enBandeja funciona bien con notificaciones editoriales, se convierte en el servicio central de comunicacion con los usuarios. El siguiente paso natural es absorber las newsletters manuales (Trigger B), dando a los editores una API para crear y programar campanas. Mas adelante, podria gestionar otros canales (push notifications, notificaciones in-app) con el mismo patron: evento → orquestacion → despacho por canal.

La tabla de campanas se convierte en el registro central de toda comunicacion saliente, dando al equipo de producto datos que hoy no existen: que se envia, a quien, cuando, y con que resultado.
