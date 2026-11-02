---
id: game-tables.panel.table-lifecycle
title: Publicar, iniciar, completar y cancelar una mesa
summary: Los botones de la ficha de una mesa, a qué estado lleva cada uno, qué se avisa y qué hace solo el sistema con el reloj.
category: activities
audience: panel
order: 40
keywords: [publicar mesa, despublicar, ocultar mesa, iniciar, empezar partida, completar, terminar partida, finalizar, cancelar mesa, suspender partida, anular, borrar mesa, eliminar mesa, estado, programada, completa, en progreso, completada, cancelada, borrador, automático, cambio de estado]
related: [game-tables.panel.overview, game-tables.panel.participants, game-tables.panel.moderation, game-tables.panel.settings, game-tables.panel.campaign-sessions]
conditions:
  permissions:
    all: ['gametables:gametables.update']
---
Una mesa avanza por sus estados con los botones de la parte de arriba de su ficha. Solo salen los botones que tienen sentido en el estado actual de la mesa.

## Los botones

Abre la mesa desde **Mesas de rol** → **Mesas**. Arriba verás, según el caso:

| Botón | Cuándo sale | Qué hace |
|---|---|---|
| **Aprobar** / **Rechazar** | La mesa llegó de la web y está **Pendiente de revisión** | La publica o la devuelve a su autor (mira la guía de revisión) |
| **Publicar** | La mesa no está publicada y nada lo impide | La deja publicada y en **Programada** |
| **Despublicar** | La mesa está publicada y aún no ha empezado | La oculta de la web y la pasa a **Borrador** |
| **Iniciar** | La mesa está **Programada** o **Completa** | La pasa a **En progreso** |
| **Completar** | La mesa está **En progreso** | La pasa a **Completada** |
| **Cancelar mesa** | La mesa no está completada ni cancelada | La pasa a **Cancelada** y anula las inscripciones |
| **Borrar** | Siempre, con el permiso **Eliminar mesa** | La borra del todo |

**Iniciar**, **Completar**, **Cancelar mesa** y **Borrar** no se pueden deshacer. Por eso piden confirmación dos veces: el aviso y una casilla que tienes que marcar, como «Entiendo que la mesa no podrá volver a «Programada»».

## Publicar y despublicar

1. Pulsa **Publicar**. La mesa sale en la web y las inscripciones se abren según sus fechas.
2. Si es una sesión de campaña, la plantilla queda inscrita en ese momento.

**Despublicar** pide confirmación y devuelve la mesa a **Borrador**. Las inscripciones que ya tenía se conservan. Una mesa que ya ha empezado no se despublica: se cancela.

Una mesa creada en la web no se puede publicar desde aquí hasta que se aprueba: **Publicar** no sale y el interruptor **Publicado** está bloqueado. Una vez aprobada, se puede despublicar y volver a publicar como cualquier otra.

## Iniciar y completar

1. Pulsa **Iniciar**, marca la casilla y pulsa **Sí, iniciar mesa**.
2. Al terminar la partida, pulsa **Completar**, marca **Entiendo que no se puede deshacer** y pulsa **Sí, completar mesa**.

No hace falta pulsarlos si está activado **Actualizar el estado de las mesas automáticamente** en la configuración: cada cinco minutos, las mesas programadas o completas pasan a **En progreso** a su hora de inicio, y las que están en progreso pasan a **Completada** al final (inicio más duración). Pulsarlos antes adelanta el cambio. Ninguno de los dos envía correos.

Si tu web tiene encuestas, al completarse la mesa puede salir su encuesta al terminar.

## Marcar como completa

**Completa** no tiene botón: elígela en el campo **Estado** de la pestaña **Publicación** y pulsa **Guardar cambios**. Desde ahí solo puedes moverte entre **Programada** y **Completa**.

> [!IMPORTANT]
> **Completa** es solo una marca. No cierra las inscripciones: mientras queden plazas, la gente puede seguir apuntándose. Para cerrarlas, cambia el **Cierre de inscripciones**.

## Cancelar

1. Pulsa **Cancelar mesa**.
2. Lee el aviso: «Esta acción cancelará la mesa y notificará a todos los participantes inscritos. Esta acción no se puede deshacer.»
3. Marca la casilla y pulsa **Sí, cancelar mesa**.

Todas las inscripciones activas (confirmadas, pendientes y en lista de espera) pasan a **Cancelado**, y cada persona con email recibe el correo «Mesa cancelada: (título)». Si tu web tiene encuestas, se descarta la encuesta preparada para el final.

## Borrar

Pulsa **Borrar**, marca **Entiendo que la mesa se borrará definitivamente y que no se puede deshacer** y confirma. La mesa desaparece con todas sus inscripciones y no se avisa a nadie.

> [!WARNING]
> Si la partida no se va a jugar, cancélala en lugar de borrarla: así los inscritos reciben el aviso y queda constancia.

## Si algo no sale bien

- **«La mesa ya no está en un estado que permita este cambio»**: alguien, o la actualización automática, cambió la mesa mientras la tenías abierta. La ficha muestra ya el estado nuevo.
- **«Esta sesión tiene menos plazas (…) que la plantilla de la campaña (…). Sube el máximo de jugadores antes de publicarla.»**: sube el **Máximo de jugadores** y vuelve a publicar.
- No sale **Publicar**: la mesa llegó de la web y aún no está aprobada. Si está **Pendiente de revisión**, usa **Aprobar**.
- No sale **Despublicar**: la mesa ya empezó. Usa **Cancelar mesa**.

[[action:filament.admin.resources.game-tables.index|Ver las mesas|if=gametables:gametables.view_any]]
