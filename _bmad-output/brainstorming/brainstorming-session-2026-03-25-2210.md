---
stepsCompleted: [1]
inputDocuments: []
session_topic: 'Disenar enBandeja-service: microservicio de notificaciones editoriales que consume eventos de dos CMS, programa envios para la fecha de publicacion, y orquesta campanas via Mailchimp API'
session_goals: 'Definir limites del servicio; Explorar flujo completo incluyendo publicacion futura; Identificar casos problematicos'
selected_approach: ''
techniques_used: []
ideas_generated: []
context_file: '_bmad-output/project-context.md'
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
