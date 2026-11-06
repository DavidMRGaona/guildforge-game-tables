---
id: game-tables.panel.create-table
title: Crear una mesa en el panel
summary: Rellena el formulario de una mesa pestaña a pestaña y decide cómo se apunta la gente, quién confirma las plazas y quién dirige.
category: activities
audience: panel
order: 20
keywords: [crear mesa, nueva mesa, crear partida, nueva partida, one-shot, oneshot, aventura, demo, tutorial, formulario, inscripciones, confirmar automáticamente, aprobar jugadores, aprobación, acceso anticipado, apertura, cierre, director, co-director, máster, master, DM, GM, persona externa, invitados, solo por invitación, plazas, espectadores]
related: [game-tables.panel.campaign-sessions, game-tables.panel.table-lifecycle, game-tables.panel.participants, game-tables.panel.event-tables, game-tables.panel.overview]
conditions:
  permissions:
    all: ['gametables:gametables.create']
---
Una mesa es una partida con fecha, plazas y unas reglas de inscripción. Esta guía recorre el formulario completo. Si la mesa es una sesión de una campaña, mira también la guía de sesiones de campaña: al elegir la campaña, el formulario se rellena en parte solo.

## Crear la mesa

1. En el menú lateral, abre **Mesas de rol** y elige **Mesas**.
2. Pulsa **Crear mesa**.
3. Recorre las pestañas en orden. Cada una se explica abajo.
4. Pulsa **Crear**. Con **Crear y crear otro** guardas y empiezas otra mesa.

Los campos marcados como obligatorios no dejan guardar si están vacíos. Si una pestaña tiene un error, se marca en rojo.

## Información básica

- **Título**: el nombre de la partida.
- **Campaña**: déjalo vacío para una mesa suelta. Si eliges una campaña, la mesa pasa a ser una sesión de esa campaña.
- **Sistema de juego**: solo salen los sistemas activos. Si eliges una campaña, se copia el suyo y no se puede cambiar.
- **Evento**: si la mesa forma parte de una jornada, elige el evento. Solo salen los eventos publicados que aún no han empezado.
- **Sinopsis**: el gancho de la partida, hasta 2000 caracteres.
- **Imagen**: opcional, hasta 2 MB.

## Dirección

Pulsa **Añadir director de juego** por cada persona que dirige. Una mesa puede no tener a nadie aquí, pero entonces nadie recibe los avisos de dirección.

- **Tipo**: **Usuario de la plataforma** si tiene cuenta (la eliges en **Usuario**) o **Persona externa** si no la tiene (escribe **Nombre**, **Apellidos**, **Email** y **Teléfono**).
- **Rol**: **Director principal** o **Co-director**.
- **Título personalizado**: cómo se le llama en esta partida, por ejemplo «Narrador» o «Guardián». Si lo dejas vacío, la web muestra su rol, **Director principal** o **Co-director** (no el título del sistema de juego, aunque la ayuda del campo lo diga).
- **Notificar por email**: recibe los avisos de inscripciones y bajas de la mesa.
- **Nombre visible públicamente**: su nombre sale en la ficha de la web.
- **Notas**: solo para el panel.

## Horario y ubicación

- **Fecha y hora de inicio** y **Duración (minutos)**: el final de la partida es el inicio más la duración. La duración va de 30 minutos al máximo que marque la configuración.
- **Formato**: **Presencial**, **Online** o **Híbrido**.
- **Ubicación**: sale con **Presencial** e **Híbrido**.
- **URL online**: sale con **Online** e **Híbrido**. Es el enlace de la sala o la mesa virtual.

## Capacidad y requisitos

- **Tipo de mesa**: **One-shot**, **Aventura**, **Sesión de campaña**, **Demo** o **Tutorial**.
- **Mínimo de jugadores** y **Máximo de jugadores**: el máximo son las plazas de jugador. Ninguno puede pasar del **Límite máximo de jugadores** de la configuración.
- **Máximo de espectadores**: con 0, no se admiten espectadores.
- **Edad mínima**: es orientativa. Sale en la ficha, pero no se comprueba al inscribirse, porque las cuentas no guardan la fecha de nacimiento.
- **Idioma**, **Nivel de experiencia** y **Creación de personajes**: obligatorios. Ayudan a elegir partida.

## Contenido y seguridad

- **Géneros** y **Tono**: describen la partida.
- **Herramientas de seguridad**: las que usarás en la mesa, como **Carta X** o **Líneas y velos**.
- **Avisos de contenido**: elige de la lista de avisos activos.
- **Avisos personalizados**: escribe un aviso que no esté en la lista y pulsa Intro.

Todo esto es informativo: sale en la ficha para que cada persona decida si la partida es para ella.

## Inscripciones

Esta pestaña decide quién se apunta, cuándo y quién confirma las plazas.

| Campo | Qué hace |
|---|---|
| **Tipo de inscripción** | **Abierto a todos**: cualquier cuenta y también personas sin cuenta, que se apuntan con su email y lo confirman con un enlace. **Solo el rol {{association_role}}**: solo quienes tienen el rol {{association_role}}, y nunca personas sin cuenta. **Solo por invitación**: nadie se apunta solo; la organización añade a cada persona desde la pestaña **Participantes**. |
| **Días de acceso anticipado para el rol {{association_role}}** | Cuántos días antes de la **Apertura de inscripciones** pueden apuntarse quienes tienen el rol {{association_role}}. Solo cuenta si la mesa tiene apertura y si el acceso anticipado está activado en la configuración. |
| **Apertura de inscripciones** | Desde cuándo se puede apuntar la gente. Vacío: desde que la mesa se publica. |
| **Cierre de inscripciones** | Hasta cuándo. Vacío: hasta el inicio de la mesa (o hasta su final, si admite inscripciones durante la partida). No puede ser posterior a ese límite. |
| **Confirmar automáticamente** | Activado: quien se apunta y tiene plaza queda **Confirmado** al momento. Desactivado: queda **Pendiente** hasta que alguien lo confirme. |
| **Permitir inscripciones durante la partida** | Admite inscripciones desde el inicio hasta el final de la partida, solo con plazas libres: sin lista de espera ni bajas. |
| **El director gestiona las inscripciones** | Los directores con cuenta ven los inscritos en la web y aprueban o rechazan las solicitudes pendientes desde allí. No sale en las sesiones de campaña. |
| **Email de notificaciones** | Una dirección más que recibe los avisos de inscripciones y bajas, aparte de los directores. |

### Quién aprueba a los jugadores

Con **Confirmar automáticamente** desactivado, cada inscripción queda **Pendiente**. La confirman:

- En el panel, quien tiene el permiso **Editar mesa**, con **Confirmar** en la pestaña **Participantes**.
- En la web, los directores con cuenta, si la mesa tiene **El director gestiona las inscripciones**. Reciben el aviso de cada solicitud, y de cada persona que pasa pendiente desde la lista de espera, con un enlace a **Gestionar inscritos**.

Si la lista de espera está activada en la configuración, en las mesas con confirmación manual las inscripciones pendientes ocupan plaza: quien llega después va a la lista de espera.

> [!TIP]
> Para una partida que quieres llenar tú a mano, elige **Solo por invitación** y añade a cada persona con **Añadir participante**.

## Publicación

- **Publicado**: decide si la mesa se ve en la web. Puedes dejarlo desactivado y publicarla más tarde con **Publicar**.
- **Estado**: en una mesa nueva, **Programada** o **Completa**. Iniciar, completar y cancelar tienen sus propios botones (mira la guía de publicar, iniciar, completar y cancelar).
- **Notas**: notas internas, hasta 1000 caracteres.
- **Encuesta al terminar**: si tu web tiene encuestas, elige aquí qué encuesta sale cuando la mesa termine. El campo tiene su propia ayuda.

## Qué pasa al guardar

La mesa queda guardada con sus directores. Si está publicada y en **Programada**, sale en la lista y en el calendario de la web, y las inscripciones se abren según sus fechas. Si es una sesión de campaña publicada, su plantilla queda inscrita al momento.

## Si algo no sale bien

- **«El cierre de inscripciones no puede ser posterior al inicio de la mesa (…)»**: adelanta el cierre o déjalo vacío. Con **Permitir inscripciones durante la partida**, el límite es el final de la partida.
- **«Para que el director gestione las inscripciones, cada director tiene que estar vinculado a una cuenta del sitio. Elige la cuenta de …»**: cambia esa persona a **Usuario de la plataforma** o desactiva **El director gestiona las inscripciones**.
- **«El máximo de jugadores no puede ser menor que la plantilla de la campaña (…)»**: la mesa es una sesión y su campaña tiene más jugadores fijos que plazas. Sube el máximo.
- No encuentras un sistema de juego o un aviso de contenido: puede estar desactivado en el catálogo.

[[action:filament.admin.resources.game-tables.create|Crear una mesa]]
