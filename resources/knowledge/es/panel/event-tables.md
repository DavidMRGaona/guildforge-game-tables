---
id: game-tables.panel.event-tables
title: Mesas en un evento
summary: Abre un evento a las mesas creadas desde la web, con horario libre o por turnos, su ubicación, la fecha de apertura y quién puede crear antes que nadie.
category: activities
audience: panel
order: 90
keywords: [evento, eventos, jornada, jornadas, quedada, convención, mesas del evento, partidas del evento, turnos, franjas, horarios, máximo de mesas, ubicación del evento, apertura, crear partida, acceso anticipado, quién crea mesas, regla propia, elegibilidad]
related: [core.panel.manage-events, game-tables.panel.web-creation-settings, game-tables.panel.create-table, game-tables.panel.moderation]
conditions:
  permissions:
    all: ['events.update']
---
Un evento puede reunir varias mesas: una jornada de rol, unas jornadas abiertas o una quedada. Desde el panel puedes vincular cualquier mesa a un evento con su campo **Evento**. Esta guía explica cómo abrir además el evento para que la gente cree sus mesas desde la web, con las reglas que tú pongas.

## Abrir la configuración

1. En el menú lateral, abre **Contenido** y elige **Eventos**.
2. Entra en el evento.
3. Baja hasta la pestaña **Configuración de mesas**.
4. Al terminar, pulsa **Guardar configuración**. Sale el aviso «Configuración guardada».

## Activar las mesas

Activa **Habilitar mesas para este evento**. Sin esto, la ficha del evento en la web no ofrece **Crear partida** y nadie puede crear mesas en él desde la web. Al activarlo aparecen las demás secciones.

Si lo desactivas y guardas, toda la configuración del evento se borra.

## Programación

En **Modo de programación**:

- **Horario libre**: cada mesa elige su hora dentro del evento.
- **Por turnos**: cada mesa elige uno de los turnos que definas.

Con **Por turnos**, añade cada turno en **Turnos** con su **Nombre del turno** (por ejemplo «Mañana»), su **Hora de inicio**, su **Hora de fin** y, si quieres, un **Máximo de mesas**. Cuando un turno llega a su máximo, la web lo da por completo y no deja crear más mesas en él. Si el evento dura varios días, elige también el **Día** de cada turno (el primero, si no eliges otro); en un evento de un solo día, los turnos van en ese día. Si la **Hora de fin** no es posterior a la **Hora de inicio**, el turno acaba al día siguiente: por ejemplo, de 22:00 a 02:00.

## Ubicación

En **Modo de ubicación**:

- **Elección libre**: quien crea la mesa escribe dónde se juega.
- **Ubicación fija**: todas las mesas usan la de **Ubicación fija**, que se vuelve obligatoria.
- **Ubicación del evento**: las mesas usan la del evento.

## Creación de partidas

**Fecha de apertura general** es obligatoria: desde ese momento, quien pueda crear mesas ya puede crearlas en este evento. Antes de esa fecha, la web le dice desde cuándo podrá.

Las mesas que se crean en el evento desde la web cierran sus inscripciones al empezar el evento si se juegan su primer día, y al empezar la mesa si se juegan otro día (o si empiezan antes que el evento).

## Quién puede crear: regla propia

Por defecto, el evento usa las reglas generales de **Configuración** → **Creación desde web**. Para que este evento tenga las suyas, abre **Sobrescribir elegibilidad**:

1. Activa **Usar configuración de evento**.
2. Elige el **Nivel de acceso**: **Cualquier persona**, **Usuarios registrados**, **Usuarios con rol específico** o **Usuarios con permiso**.
3. Con roles, elígelos en **Roles permitidos**. Con permiso, escribe el **Permiso requerido**.

> [!IMPORTANT]
> Con una regla propia, el evento decide solo quién crea sus mesas: sirve aunque la creación desde la web esté desactivada o limitada en la configuración general. Solo afecta a las mesas de este evento, nunca a las campañas.

Lo que no cambia es la revisión: las mesas del evento se publican o esperan revisión según el **Modo de publicación** general.

## Acceso anticipado

Para dejar que algunas personas creen mesas antes de la **Fecha de apertura general**, abre **Acceso anticipado**:

1. Activa **Habilitar acceso anticipado**.
2. En **Tipo de acceso anticipado**, elige **Usuarios con rol específico** o **Usuarios con permiso**.
3. Elige los **Roles con acceso anticipado** o escribe el **Permiso de acceso anticipado**.
4. En **Días de ventaja**, di cuántos días antes pueden crear (de 1 a 30).

Por ejemplo: con apertura el día 10 y 3 días de ventaja, la junta puede crear mesas desde el día 7 y el resto desde el 10. Quien tiene acceso anticipado tiene que cumplir además la regla de quién puede crear.

## Si algo no sale bien

- La web no muestra **Crear partida** en el evento: comprueba que **Habilitar mesas para este evento** está activado y guardado, y que el evento está publicado y aún no ha terminado.
- Alguien no puede crear aunque el evento está abierto: no cumple la regla (la general o la del evento), o aún no ha llegado su fecha.
- **«El horario «…» ya no admite más mesas»**: ese turno llegó a su **Máximo de mesas**. Súbelo o añade otro turno.

[[action:filament.admin.resources.events.index|Ver los eventos]]
