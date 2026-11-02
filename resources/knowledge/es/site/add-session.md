---
id: game-tables.add-session
title: Añadir una sesión a tu campaña
summary: Crea una sesión de una campaña que diriges; al publicarse, su plantilla queda inscrita y recibe un correo.
category: campaigns
audience: site
order: 50
keywords: [añadir sesión, nueva sesión, crear sesión, sesión de campaña, programar sesión, próxima sesión, fecha de la sesión, plantilla, inscribir a la plantilla, campaña, máster, master, DM, GM, director, anadir sesion]
related: [game-tables.create-table, game-tables.manage-roster, game-tables.review-and-publish, game-tables.campaigns-overview]
conditions:
  capabilities: [game-tables.adds_campaign_sessions]
---
Cada sesión de una campaña es una mesa de tipo **Sesión de campaña**. Puedes crearlas para las campañas que diriges si están aprobadas y no han terminado, aunque la configuración general no te deje crear otras mesas.

## Abrir el formulario

Tienes dos caminos:

- **Desde Mis campañas**: pulsa **Añadir sesión** en la campaña. El formulario se abre con el tipo **Sesión de campaña** y la campaña ya elegidos.
- **Desde Crear mesa**: en **Tipo de partida**, elige **Sesión de campaña**. Debajo aparece **Campaña**: elige la tuya en **Selecciona la campaña**. Solo salen las campañas que diriges, aprobadas y sin terminar.

Si solo puedes crear sesiones, el tipo queda fijado en **Sesión de campaña** y verás «Solo puedes crear sesiones de las campañas que diriges.».

## Lo que se rellena solo

Al elegir la campaña:

- **Sistema de juego**: el de la campaña.
- **Máximo de jugadores**: el máximo de la campaña. Si la campaña no tiene máximo, el mayor entre el valor por defecto de la web y el tamaño de la plantilla.
- **Formato**, **Ubicación física** y **Enlace de la sesión online**: se proponen los de la campaña, si los tiene.

Puedes cambiar cualquiera de ellos. Verás el aviso «Al publicarse, la plantilla (N jugadores) quedará inscrita y recibirá un correo.».

> [!IMPORTANT]
> El **Máximo de jugadores** nunca puede ser menor que la plantilla: cada jugador de la plantilla tiene que caber en la sesión.

## Completar y guardar

1. Pon el **Título de la partida**, por ejemplo «Sesión 4: el puerto de Vel».
2. Elige la **Fecha y hora de inicio** y la **Duración (minutos)**.
3. Revisa el resto del formulario como en cualquier mesa: la guía «Crear una mesa» explica cada campo.
4. Pulsa **Crear mesa**.

La sesión pasa por la misma revisión que una mesa: según la configuración de la web y tu rol, se publica directamente o queda en **Borradores** de **Mis mesas** para que la envíes a revisión.

## Al publicarse

- **La plantilla queda inscrita**: cada jugador entra con plaza confirmada, aunque la mesa confirme a mano. Se respetan las ausencias que ya hubiera en esa sesión.
- **Cada jugador recibe un correo** «Nueva sesión de …» con la fecha, si la web tiene activado ese aviso.
- **Las plazas que sobren** quedan libres para otras personas si la campaña acepta jugadores nuevos. Si no, la sesión es **Solo para la plantilla de la campaña**.
- **Quien entre después en la plantilla** queda inscrito en ella mientras admita inscripciones.

En la ficha de la sesión, los jugadores de la plantilla ven **Inscrito por la campaña** y el botón **No puedo ir a esta sesión**. Tú ves su asistencia en la **Plantilla** de la campaña.

Las sesiones publicadas no salen en la lista **Publicadas** de **Mis mesas**: las encuentras en la ficha de la campaña, en **Mesas de la campaña**.

## Si algo no sale bien

- **«Elige la campaña de la sesión.»**: elige la campaña en el campo **Campaña**.
- **«No diriges ninguna campaña publicada.»**: tu campaña aún no está aprobada, o ha terminado. Pulsa **Crea una campaña** (se abre en otra pestaña) o elige otro tipo de mesa.
- **«Solo puedes vincular la mesa a una campaña que dirijas.»**: esa campaña ya no admite sesiones tuyas.
- **«El máximo de jugadores no puede ser menor que la plantilla de la campaña (N).»**: sube el **Máximo de jugadores**.
- **«Esta sesión tiene menos plazas (…) que la plantilla de la campaña (…). Sube el máximo de jugadores antes de publicarla.»**: la plantilla ha crecido desde que creaste la sesión. Edítala y sube el máximo.
- **No ves Añadir sesión en tu campaña**: la campaña está en revisión o ha terminado, o la web no admite crear desde la web ahora.

[[action:campaigns.my-campaigns|Ir a mis campañas]]
