---
id: game-tables.panel.participants
title: Gestionar los inscritos de una mesa
summary: Añade, confirma, rechaza, promueve y da de baja a los inscritos de una mesa, incluidas las personas sin cuenta, y consulta su historial.
category: activities
audience: panel
order: 50
keywords: [inscritos, inscripciones, participantes, jugadores, espectadores, confirmar, aprobar jugador, rechazar, pendiente, lista de espera, promover, subir de la lista, dar de baja, cancelar inscripción, no presentado, no vino, invitado, sin cuenta, email sin confirmar, posible duplicado, caducada, añadir participante, apuntar a alguien, historial]
related: [game-tables.panel.create-table, game-tables.panel.campaign-sessions, game-tables.panel.settings, game-tables.panel.table-lifecycle]
conditions:
  permissions:
    all: ['gametables:gametables.update']
---
Los inscritos de una mesa están en la pestaña **Participantes** de su ficha. Desde ahí se hace todo lo que la web no deja hacer a los jugadores ni a la dirección.

## Ver los inscritos

1. Abre **Mesas de rol** → **Mesas** y entra en la mesa.
2. Baja hasta la pestaña **Participantes**.

Cada fila muestra el **Nombre**, el **Rol** (**Jugador** o **Espectador**), el **Estado** y las fechas. Mientras haya gente esperando, la columna **Puesto en lista de espera** dice su orden. La columna **Avisos** marca dos casos de personas sin cuenta:

- **Email sin confirmar**: aún no ha pulsado el enlace del correo. Al pasar el ratón ves cuándo caduca.
- **Posible duplicado**: otra inscripción de esta mesa se hizo desde la misma conexión y navegador hace poco. Revísala antes de confirmar.

Puedes filtrar por **Rol**, por **Estado** y por **Email sin confirmar**.

## Añadir a alguien

1. Pulsa **Añadir participante**.
2. En **Tipo**, elige **Usuario de la plataforma** (y busca su **Usuario**) o **Persona externa** (escribe **Nombre**, y si quieres **Apellidos**, **Email** y **Teléfono**).
3. Elige el **Rol** y, si quieres, añade **Notas**.
4. Pulsa **Crear**.

Desde el panel no se aplican las reglas de inscripción: puedes añadir a alguien con las inscripciones cerradas, en una mesa **Solo el rol {{association_role}}** o **Solo por invitación**. Lo que sí cuenta son las plazas y la confirmación de la mesa:

- Si hay plaza y la mesa confirma sola, queda **Confirmado**. Si no confirma sola, queda **Pendiente**.
- Si la mesa está llena, o en estado **Completa**, un jugador va a la **Lista de espera**; luego puedes darle una plaza libre con **Promover**.

Una persona externa añadida desde el panel no tiene que confirmar su email. Quien se inscribe recibe su correo de inscripción y, si los avisos están activados, también la dirección.

## Las acciones de cada fila

Se abren con el menú **⋯** al final de la fila. Solo salen las que tienen sentido para esa inscripción. En una pantalla estrecha la lista oculta algunas columnas (el rol, los avisos y las fechas) para que quepa; se ven al ensanchar la ventana.

| Acción | Cuándo sale | Qué hace | Correo |
|---|---|---|---|
| **Confirmar** | Inscripción **Pendiente** | Le da la plaza. Si es una persona sin cuenta que no había confirmado el email, lo da también por confirmado. | «Inscripción confirmada: (mesa)» |
| **Marcar email como confirmado** | Persona sin cuenta con **Email sin confirmar** | Hace como si hubiera pulsado el enlace del correo: ocupa su plaza, o su puesto en la lista de espera. | Su correo de inscripción |
| **Rechazar** | Inscripción **Pendiente** | La rechaza. Puedes escribir un **Motivo (opcional)**. | «Inscripción rechazada: (mesa)», con el motivo |
| **Promover** | Inscripción en **Lista de espera** | La pasa a **Confirmado** ya. | «Plaza disponible en mesa: (mesa)» |
| **Cancelar inscripción** | Inscripción activa | La da de baja y libera su plaza, que pasa al primero de la lista de espera si corresponde. | «La organización ha cancelado tu inscripción en (mesa)» |
| **No presentado** | Inscripción **Confirmado** de una mesa que ya empezó | La marca como no presentada. No libera plaza ni promueve a nadie. | Ninguno |
| **Editar** | Siempre | Cambia las notas y, en una persona sin cuenta, su nombre, email y teléfono. | Ninguno |
| **Borrar** | Inscripción **Cancelado**, **Rechazado** o **Caducada** | La quita de la lista. Las demás hay que cancelarlas antes. | Ninguno |

Desde el panel puedes dar de baja a alguien en cualquier momento, también con la partida empezada. Los jugadores solo pueden hacerlo hasta el inicio.

## La lista de espera

La lista de espera existe si está activada en la configuración. Cuando se libera una plaza de jugador, el primero de la lista pasa a ella solo, hasta el inicio de la partida o hasta el cierre de inscripciones, si es anterior. Después, o mientras la mesa está **Completa**, ya no corre sola: usa **Promover**.

- En una mesa que confirma sola, el promovido queda **Confirmado**.
- En una mesa con confirmación manual, queda **Pendiente** hasta que alguien lo confirme, salvo que sea de la plantilla de la campaña.
- **Promover** a mano siempre confirma, pero con la lista de espera activada no deja pasar del máximo de plazas.

La lista corre solo para jugadores: una plaza de jugador libre nunca se da a un espectador.

## Personas sin cuenta

En las mesas **Abierto a todos**, quien no tiene cuenta se apunta con su nombre y su email, y recibe un enlace para confirmarlo. Mientras no lo pulsa, su inscripción lleva **Email sin confirmar**. Si no lo confirma dentro del plazo de la configuración, pasa a **Caducada**, recibe el correo «Inscripción caducada: (mesa)» y su plaza queda libre. La guía de ajustes explica si guarda plaza mientras tanto.

## El historial

La pestaña **Historial de inscripciones** guarda cada cambio de estado: **Fecha**, **Inscrito**, **Cambio**, **Hecho por** (**Sistema**, **El propio inscrito** o el nombre de quien lo hizo) y **Motivo**. Solo se puede leer. Se borra sola doce meses después del inicio de la mesa.

## Si algo no sale bien

- **«La mesa está completa»** o **«No quedan plazas de espectador disponibles»**: no hay plaza libre para confirmar o promover. Da de baja a alguien o sube el máximo.
- **«Esta inscripción ya no está pendiente.»**: alguien la ha resuelto mientras tanto.
- **«Ya existe una inscripción con este email»**: ya hay otra persona sin cuenta apuntada con ese email en esta mesa.
- **«Solo se puede marcar como no presentado a un participante confirmado, una vez empezada la mesa»**: espera al inicio de la partida.
- **«No se ha borrado: la inscripción ya no está cancelada, rechazada ni caducada»**: cancélala antes con **Cancelar inscripción**.

[[action:filament.admin.resources.game-tables.index|Ver las mesas|if=gametables:gametables.view_any]]
