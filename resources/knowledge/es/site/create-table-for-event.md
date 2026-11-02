---
id: game-tables.create-table-for-event
title: Crear una mesa en un evento
summary: Propón una partida dentro de un evento, como unas jornadas, con sus turnos, su ubicación y sus fechas de apertura.
category: events-and-games
audience: site
order: 45
keywords: [crear partida, mesa en evento, jornadas, evento, turno, turnos, horario, franja, quedan mesas, completo, apertura, acceso anticipado, podrás crear, dirigir en jornadas, proponer partida, ubicación del evento, crear partida evento]
related: [game-tables.create-table, game-tables.review-and-publish, core.events.find-events]
conditions:
  capabilities: [game-tables.can_create_tables]
---
Algunos eventos de {{site_name}}, como unas jornadas, están abiertos a que la gente proponga sus propias partidas. Cada evento decide cuándo se abre la creación, quién puede crear, si las mesas van por turnos y dónde se juegan.

## Desde la ficha del evento

En la ficha del evento verás uno de estos botones o avisos:

| Lo que ves | Qué significa |
|---|---|
| **Crear partida** | Puedes crear una mesa ahora. |
| «Podrás crear a partir del …» | La creación aún no se ha abierto para ti. |
| «Acceso anticipado activo desde …» | El evento tiene acceso anticipado para algunos roles; la fecha es desde cuándo puedes crear tú. |
| **Ver mesa** o **Ver N mesas** | Las mesas que ya tiene el evento. |

Si no ves nada de esto, el evento no admite mesas o tu cuenta no puede crearlas en él.

## Crear la mesa

1. Pulsa **Crear partida**. El formulario se abre con el aviso «Creando partida para el evento:» y su nombre.
2. Rellena el formulario como el de cualquier mesa (lo explica la guía «Crear una mesa»), con estas diferencias:
   - Si el evento va **por turnos**, en lugar de la fecha eliges el **Horario de la partida**. Cada turno dice cuántas mesas admite aún, por ejemplo «Mañana (quedan 3 mesas)», o «(completo)» si ya no admite más. Si los turnos caen en días distintos, cada uno lleva delante su día, por ejemplo «sáb 15 · Mañana».
   - Si el evento fija la ubicación, el campo **Ubicación física** viene relleno y bloqueado: «La ubicación está definida por el evento».
3. Pulsa **Crear mesa**.

> [!TIP]
> También puedes elegir **Parte de un evento** en el formulario normal. Pero en un evento por turnos, crea la mesa desde **Crear partida**: así eliges el turno de una lista, y la hora de inicio coincide con la del turno.

## Lo que decide el evento

- **Quién puede crear**: el evento puede tener su propia regla, distinta de la general de la web.
- **Cuándo se abre la creación**: una fecha de apertura general y, a veces, unos días antes para ciertos roles o permisos.
- **Los turnos**: cada turno tiene su hora y, a veces, un máximo de mesas. Una mesa rechazada en la revisión deja libre su sitio en el turno, y al reenviarla solo lo recupera si el turno sigue teniendo hueco.
- **La ubicación**: libre, fija para todas las mesas o la del evento.
- **El cierre de inscripciones**: si tu partida es el primer día del evento, sus inscripciones cierran cuando empieza el evento, aunque la partida sea más tarde; si es otro día, cierran cuando empieza la partida.

Después, la mesa pasa por la misma revisión que cualquier otra. Lo explica la guía «Revisión y publicación de lo que creas».

## Si algo no sale bien

- **«Este evento no admite la creación de mesas»**: el evento no tiene activadas las mesas.
- **«Este evento no está abierto a nuevas mesas»**: el evento no está publicado o ya ha terminado.
- **«Podrás crear mesas a partir del …»**: vuelve ese día.
- **«Elige uno de los horarios del evento»**: la hora de inicio no coincide con ningún turno. Créala desde **Crear partida** y elige el turno.
- **«El horario «…» ya no admite más mesas»**: ese turno se ha llenado; elige otro.
- **«Solo puedes vincular la mesa a un evento abierto a nuevas mesas»**: el evento ha dejado de admitir mesas mientras rellenabas el formulario.

[[action:gametables.create|Crear una mesa]]
