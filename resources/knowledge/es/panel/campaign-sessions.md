---
id: game-tables.panel.campaign-sessions
title: Crear una sesión de campaña
summary: Crea una sesión desde el panel, qué copia de la campaña, cómo se inscribe sola la plantilla al publicarla y qué pasa si no caben todos.
category: activities
audience: panel
order: 30
keywords: [sesión, sesiones, sesion, sesión de campaña, nueva sesión, programar sesión, campaña, campana, plantilla, jugadores fijos, inscripción automática, heredado, excluir director, directores heredados, lista de espera, solo para la plantilla, ausencia, correo nueva sesión]
related: [game-tables.panel.create-table, game-tables.panel.campaign-roster, game-tables.panel.create-campaign, game-tables.panel.participants, game-tables.panel.table-lifecycle]
conditions:
  permissions:
    all: ['gametables:gametables.create']
---
Una sesión de campaña es una mesa vinculada a una campaña. Se crea con el mismo formulario que cualquier mesa: lo que cambia es que copia datos de la campaña y que, al publicarse, inscribe sola a la plantilla.

## Crear la sesión

1. En el menú lateral, abre **Mesas de rol** y elige **Mesas**.
2. Pulsa **Crear mesa**.
3. Escribe el **Título**, por ejemplo «Sesión 4: el puerto».
4. En **Campaña**, elige la campaña. El formulario se rellena como se explica abajo, y el **Tipo de mesa** pasa solo a **Sesión de campaña**.
5. Revisa la **Fecha y hora de inicio**, la **Duración (minutos)** y el resto de pestañas, como en cualquier mesa.
6. En **Publicación**, activa **Publicado** si quieres que la plantilla quede inscrita ya. Si no, publícala después con **Publicar**.
7. Pulsa **Crear**.

## Qué copia de la campaña

| Dato | Qué pasa |
|---|---|
| **Tipo de mesa** | Pasa a **Sesión de campaña** y queda bloqueado mientras haya campaña. |
| **Sistema de juego** | Se copia el de la campaña y queda bloqueado. |
| **Máximo de jugadores** | Se pone el de la campaña. Si la campaña no tiene máximo, el mayor entre el valor por defecto y el tamaño de la plantilla. Puedes subirlo. |
| **Formato**, **Ubicación** y **URL online** | Se proponen los de la campaña. Puedes cambiarlos para esta sesión. Si la campaña no tiene alguno, se queda lo que hubieras escrito. |
| Directores | Si la pestaña **Dirección** está vacía, se traen los de la campaña. |

En **Campaña** solo salen las campañas creadas en el panel y las de la web ya aprobadas. Una campaña de la web en borrador o en revisión no sale hasta que se apruebe, salvo si la mesa ya estaba vinculada a ella.

Para que la mesa deje de ser una sesión, vacía **Campaña**. El **Tipo de mesa** vuelve al que tenía antes de elegir la campaña o, si ya era una sesión, se queda vacío para que elijas otro. Al revés, una mesa de tipo **Sesión de campaña** no se guarda sin campaña.

Los directores de la campaña salen en **Dirección** con «(heredado)» y el aviso **Heredado de la campaña**. Sus datos no se cambian aquí, sino en la campaña. Si alguno no va a estar en esta sesión, activa **Excluir de esta mesa**: seguirá en la campaña, pero no en esta mesa, y su nombre pasa a llevar «(excluido)». También puedes añadir directores solo para esta sesión con **Añadir director de juego**.

En una sesión no sale **El director gestiona las inscripciones**: sus inscritos se gestionan desde el panel, y la plantilla la lleva la dirección de la campaña.

## El máximo nunca baja de la plantilla

Una sesión tiene que tener plazas para toda la plantilla. Si el **Máximo de jugadores** es menor que el número de jugadores fijos, el formulario no deja guardar y **Publicar** se niega. La regla deja de aplicarse cuando la sesión ya ha empezado o terminado.

## La plantilla se inscribe sola

Cuando la sesión queda publicada y en **Programada** o **Completa**, cada jugador de la plantilla queda inscrito:

- Con plaza, como **Confirmado**, aunque la mesa tenga **Confirmar automáticamente** desactivado.
- Sin plaza, en la **Lista de espera**, por delante de quien no es de la plantilla.

Esto pasa al crear la sesión ya publicada, al pulsar **Publicar**, al aprobar una sesión creada en la web y al cambiar la sesión a otra campaña. Solo pasa mientras las inscripciones de la sesión siguen abiertas.

Se respeta lo que ya había: quien estaba inscrito sigue como estaba, y no se vuelve a inscribir a quien se dio de baja de esa sesión, fue rechazado o quedó como no presentado.

Si en la configuración está activado **Sesión programada (a la plantilla)**, cada jugador inscrito recibe el correo «Nueva sesión de (campaña): (fecha)». Si entró en la lista de espera, el correo se lo dice.

> [!IMPORTANT]
> En el panel, **Cancelar inscripción** sobre un jugador de la plantilla cuenta como una ausencia a esa sesión: sigue en la campaña, pero no se le vuelve a inscribir en ella aunque despubliques y vuelvas a publicar. Recibe el correo «Ausencia anotada: (sesión)», y puede volver a apuntarse desde la web mientras haya plaza.

## Cuando la plantilla cambia

- Quien entra en la plantilla después queda inscrito en las sesiones publicadas cuyas inscripciones siguen abiertas.
- Quien sale o es quitado pierde su plaza en las sesiones que aún no han empezado. Su plaza pasa al primero de la lista de espera.

## Quién más puede apuntarse

Si la campaña acepta jugadores nuevos (**Acepta nuevos jugadores** activado y estado **Reclutando** o **Activa**), las plazas que sobran se ocupan como en cualquier mesa. Si no, la sesión es solo para la plantilla: quien no está en ella ve «Solo para la plantilla de la campaña» y no puede apuntarse, ni con cuenta ni sin ella. Desde el panel sí puedes añadir a quien quieras con **Añadir participante**.

## Si algo no sale bien

- **«El máximo de jugadores no puede ser menor que la plantilla de la campaña (…)»**: sube el **Máximo de jugadores**.
- **«Esta sesión tiene menos plazas (…) que la plantilla de la campaña (…). Sube el máximo de jugadores antes de publicarla.»**: la plantilla creció desde que creaste la sesión. Sube el máximo y publica de nuevo.
- Un jugador de la plantilla no aparece inscrito: comprueba que la sesión está publicada, en **Programada** o **Completa** y con las inscripciones abiertas, y que no tenía ya una baja, un rechazo o un no presentado en esa sesión.

[[action:filament.admin.resources.game-tables.create|Crear una sesión]]
