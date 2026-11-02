---
id: game-tables.panel.moderation
title: Revisar mesas y campañas creadas en la web
summary: Encuentra lo que espera revisión, apruébalo para publicarlo o recházalo con un motivo para que su autor lo corrija.
category: activities
audience: panel
order: 80
keywords: [moderación, moderar, revisar, revisión, aprobar, rechazar, pendiente de revisión, pendientes, motivo del rechazo, mesa de la web, campaña de la web, creado desde web, publicar mesa de un usuario, validar, autorizar, borrador del autor]
related: [game-tables.panel.web-creation-settings, game-tables.panel.table-lifecycle, game-tables.panel.campaign-sessions, game-tables.panel.overview]
conditions:
  permissions:
    any: ['gametables:gametables.update', 'gametables:campaigns.update']
---
Si la web deja crear mesas o campañas, lo que crea la gente puede necesitar el visto bueno de alguien antes de publicarse. Esta guía explica cómo revisarlo. Revisar mesas requiere el permiso **Editar mesa**, y revisar campañas, **Editar campaña**.

## Cómo llega algo a revisión

1. El autor lo crea en la web y queda como **Borrador** en sus **Mis mesas** o **Mis campañas**. Mientras tanto puede cambiarlo o borrarlo.
2. Cuando está listo, lo envía a revisión. Pasa a **Pendiente de revisión** y ya no puede cambiarlo.
3. Quien tiene panel y el permiso de revisión recibe el correo «Nueva mesa pendiente de moderación: (título)» o «Nueva campaña pendiente de moderación: (título)».

Según el **Modo de publicación** de la configuración, a algunas personas no les hace falta revisión: lo que crean se publica directamente (mira la guía de ajustes de creación desde la web).

## Dónde encontrarlo

- En el escritorio, el widget **Pendientes de moderación** junta mesas y campañas, las más antiguas primero. **Ver** abre cada una.
- En **Mesas de rol** → **Mesas**, la pestaña **Pendientes de moderación** muestra solo las mesas en revisión, con su número.
- En **Mesas de rol** → **Campañas**, las campañas en revisión muestran **Aprobar** y **Rechazar** en su fila.

En la lista de mesas, la columna **Creado desde web** marca con un icono lo que llegó de la web, y el filtro **Estado de moderación** separa **Borrador**, **Pendiente de revisión** y **Aprobada**.

## Aprobar

1. Pulsa **Aprobar** en la fila o, en una mesa, también arriba en su ficha.
2. Confirma. El aviso dice «La mesa será publicada y visible para todos los usuarios.».

La mesa o la campaña queda aprobada y publicada, y el autor recibe «Tu mesa ha sido aprobada: (título)» o «Tu campaña ha sido aprobada: (título)». Si es una sesión de campaña, su plantilla queda inscrita al momento. Si es una mesa, las inscripciones se abren según sus fechas.

## Rechazar

1. Pulsa **Rechazar**.
2. Escribe el **Motivo del rechazo**. Es obligatorio: el autor lo va a leer.
3. Confirma.

El autor recibe «Tu mesa no ha sido aprobada: (título)» o «Tu campaña no ha sido aprobada: (título)» con el motivo. Lo que rechazas vuelve a ser un **Borrador** suyo, con el motivo a la vista, para que lo corrija y lo envíe otra vez. No se borra nada.

> [!TIP]
> Un buen motivo dice qué cambiar: «Falta la ubicación y la duración parece corta para una campaña de iniciación».

## Varias mesas a la vez

En la lista de mesas, marca las casillas de las filas, abre el menú de acciones en bloque y elige **Aprobar** o **Rechazar**. Solo se tocan las que están **Pendiente de revisión**; el resto se deja como está. Al rechazar en bloque, el mismo motivo va a todas. Las campañas se revisan de una en una.

## Lo que no se puede hacer

- **Publicar un borrador que el autor no ha enviado**: el interruptor **Publicado** está bloqueado con el aviso «El autor aún no la ha enviado a revisión.». Es su trabajo en curso.
- **Publicar algo en revisión sin aprobarlo**: **Publicado** también está bloqueado. Una mesa dice «Está pendiente de revisión: apruébala o recházala con «Aprobar» o «Rechazar».», y una campaña, «Está pendiente de revisión: apruébala o recházala desde la lista de campañas.». Aprobar es lo que avisa al autor y, en las sesiones, inscribe a la plantilla.

Una vez aprobada, la mesa o la campaña se gestiona como cualquier otra: se puede despublicar, volver a publicar y editar en el panel. Su autor ya no puede cambiarla desde la web; si necesita un cambio, lo hace alguien desde el panel.

También puedes editar en el panel una mesa en revisión antes de aprobarla, por ejemplo para corregir una errata.

## Si algo no sale bien

- **«La mesa ya no está en un estado que permita este cambio»** (o «La campaña ya no está…»): otra persona la ha revisado antes que tú.
- **«Esta sesión tiene menos plazas (…) que la plantilla de la campaña (…). Sube el máximo de jugadores antes de publicarla.»**: la sesión no cabe entera. Edita su **Máximo de jugadores**, guarda y aprueba otra vez. Mientras tanto sigue en revisión.
- No ves **Aprobar** ni **Rechazar**: te falta el permiso **Editar mesa** o **Editar campaña**, o el autor aún no la ha enviado.

[[action:filament.admin.resources.game-tables.index|Ver las mesas|if=gametables:gametables.view_any]]
[[action:filament.admin.resources.campaigns.index|Ver las campañas|if=gametables:campaigns.view_any]]
