---
id: game-tables.panel.overview
title: 'Mesas y campañas: cómo funcionan'
summary: Qué son las mesas, las sesiones y las campañas, por qué estados pasan, cómo se revisa lo que llega de la web y quién puede hacer cada cosa en el panel.
category: activities
audience: panel
order: 10
featured: true
keywords: [mesas, mesa, partida, partidas, rol, juego de rol, campaña, campañas, campana, sesión, sesiones, plantilla, jugadores, inscripción, inscripciones, inscritos, estados, programada, completa, en progreso, completada, cancelada, borrador, moderación, revisión, permisos, cómo funciona, introducción, empezar, director, máster, master, DM, GM]
related: [game-tables.panel.create-table, game-tables.panel.create-campaign, game-tables.panel.campaign-sessions, game-tables.panel.table-lifecycle, game-tables.panel.participants, game-tables.panel.moderation, core.panel.roles-and-permissions]
conditions:
  permissions:
    any: ['gametables:gametables.view_any', 'gametables:campaigns.view_any']
---
El módulo de mesas organiza las partidas de rol de {{site_name}}: partidas sueltas, campañas con sus sesiones y mesas dentro de un evento. Esta guía explica las piezas y cómo encajan. Las demás guías de mesas entran en el detalle de cada tarea.

## Las piezas

| Pieza | Qué es |
|---|---|
| **Mesa** | Una partida con fecha, hora, duración y plazas. Puede ser un one-shot, una aventura, una demo o un tutorial. La gente se inscribe como jugador o como espectador. |
| **Campaña** | Una historia larga que se juega en varias sesiones con un grupo más o menos fijo. Tiene su ficha (sistema, formato, horario, tono, avisos…), su dirección y su plantilla. |
| **Sesión de campaña** | Una mesa vinculada a una campaña con el campo **Campaña**. Se crea, se publica y se gestiona como cualquier mesa. |
| **Plantilla** | Los jugadores fijos de una campaña. Al publicarse una sesión, la plantilla queda inscrita sola. |
| **Evento** | Una jornada o quedada del calendario. Una mesa puede formar parte de un evento, y el evento puede abrir la creación de mesas desde la web con sus propias reglas. |

## Estados de una mesa

| Estado | Qué significa | A qué puede pasar |
|---|---|---|
| **Borrador** | No se ve en la web. Es el estado de una mesa creada en la web que aún no se ha publicado, o de una mesa que has despublicado. | **Programada** (al publicarla) o **Cancelada** |
| **Programada** | A la espera de jugarse. Si está publicada, admite inscripciones según sus fechas. | **Completa**, **En progreso** o **Cancelada** |
| **Completa** | Una marca que pones a mano para indicar que ya no buscas gente: nadie nuevo ocupa una plaza libre. | **Programada**, **En progreso** o **Cancelada** |
| **En progreso** | La partida se está jugando. | **Completada** o **Cancelada** |
| **Completada** | La partida terminó. Es definitivo. | Ninguno |
| **Cancelada** | La partida no se juega. Es definitivo. | Ninguno |

Si en **Configuración** está activado **Actualizar el estado de las mesas automáticamente** (viene activado), cada cinco minutos las mesas programadas o completas pasan a **En progreso** a su hora de inicio, y las que están en progreso pasan a **Completada** al terminar (inicio más duración). Ese cambio no envía correos. Si lo desactivas, el estado solo cambia con los botones **Iniciar** y **Completar**.

> [!IMPORTANT]
> **Completa** cierra las plazas de jugador aunque queden libres: los jugadores nuevos solo pueden apuntarse a la lista de espera, si está activada; sin ella, las inscripciones de jugador quedan cerradas. Nadie pasa solo de la lista mientras la mesa está **Completa**: tú das las plazas con **Promover** o aprobando inscripciones pendientes. Los espectadores siguen con sus propias plazas, y la plantilla de una campaña se inscribe en sus sesiones como siempre.

## Estados de una inscripción

| Estado | Qué significa |
|---|---|
| **Pendiente** | Espera a que alguien la confirme, porque la mesa no confirma sola. |
| **Confirmado** | Tiene plaza. |
| **Lista de espera** | La mesa estaba llena. Si se libera una plaza, pasa a ella por orden. |
| **Cancelado** | Se dio de baja o la canceló la organización. |
| **Rechazado** | La organización o la dirección la rechazó. |
| **No presentado** | Tenía plaza y no vino. |
| **Caducada** | Era de una persona sin cuenta que no confirmó su email a tiempo. |

## Lo que llega de la web

Según los ajustes, la gente puede crear mesas y campañas desde la web. Lo que crea pasa por estos estados de revisión, que la lista de mesas enseña en la columna **Creado desde web** y en el filtro **Estado de moderación**:

| Estado | Qué significa |
|---|---|
| **Borrador** | El autor lo está preparando. Nadie lo ve todavía y en el panel no se puede publicar. |
| **Pendiente de revisión** | El autor lo ha enviado. Espera a que alguien lo apruebe o lo rechace. |
| **Aprobada** | Está aprobado y publicado. |

Lo que se crea en el panel no pasa por revisión. La guía de revisión explica cómo aprobar o rechazar, y la guía de ajustes de creación desde la web explica cuándo se publica sin revisión.

## Web o panel

En la web, quien dirige puede gestionar parte de su trabajo sin entrar en el panel: la plantilla y las sesiones de sus campañas, y los inscritos de sus mesas si la mesa tiene activado **El director gestiona las inscripciones**. En el panel se puede hacer todo: crear y cambiar cualquier mesa o campaña, cambiar su estado, gestionar los inscritos y revisar lo que llega de la web.

## Quién hace qué

Ninguno de estos permisos se concede solo: se dan a cada rol en **Roles**. La administración los tiene todos.

| Permiso | Qué permite |
|---|---|
| **Ver listado de mesas** y **Ver mesa** | Ver las mesas en el panel |
| **Crear mesa** | Crear mesas y sesiones de campaña en el panel |
| **Editar mesa** | Cambiar mesas, publicarlas, iniciarlas, completarlas, cancelarlas, gestionar sus inscritos y aprobar o rechazar las mesas de la web (también recibe el correo de cada mesa pendiente) |
| **Eliminar mesa** | Borrar mesas |
| **Ver listado de campañas** y **Ver campaña** | Ver las campañas en el panel |
| **Crear campaña** | Crear campañas en el panel |
| **Editar campaña** | Cambiar campañas, gestionar su plantilla, solicitudes e invitaciones, y aprobar o rechazar las campañas de la web (también recibe el correo de cada campaña pendiente) |
| **Eliminar campaña** | Borrar campañas |
| **Ver sistemas de juego** y **Gestionar sistemas de juego** | Ver o cambiar los sistemas de juego y las editoriales |
| **Ver avisos de contenido** y **Gestionar avisos de contenido** | Ver o cambiar los avisos de contenido |
| **Configurar módulo de mesas** | Cambiar la página de configuración del módulo |
| **Editar evento** (del núcleo) | Configurar las mesas de un evento en su pestaña **Configuración de mesas** |

## Dónde está cada cosa

- En el menú lateral, el grupo **Mesas de rol** tiene **Mesas**, **Campañas**, **Avisos de contenido** y **Configuración**. El grupo **Catálogo de juegos** tiene **Editoriales** y **Sistemas de juego**.
- En la lista de mesas, la pestaña **Pendientes de moderación** reúne las mesas que esperan revisión.
- En el escritorio, el widget **Pendientes de moderación** aparece mientras haya mesas o campañas por revisar.
- Dentro de una mesa están las pestañas **Participantes** e **Historial de inscripciones**. Dentro de una campaña, **Plantilla**, **Solicitudes** e **Invitaciones**.
- Dentro de un evento, la pestaña **Configuración de mesas** decide si el evento admite mesas creadas desde la web y con qué reglas.

Si tu web tiene encuestas, los formularios de mesas y campañas traen además el campo **Encuesta al terminar**, con su propia ayuda.

[[action:filament.admin.resources.game-tables.index|Ver las mesas|if=gametables:gametables.view_any]]
[[action:filament.admin.resources.campaigns.index|Ver las campañas|if=gametables:campaigns.view_any]]
