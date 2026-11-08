---
id: game-tables.panel.settings
title: 'Ajustes de mesas: inscripciones, invitados y avisos'
summary: Qué decide cada campo de la configuración del módulo de mesas, salvo la creación desde la web, y cómo afecta a las mesas y campañas.
category: activities
audience: panel
order: 110
keywords: [ajustes, configuración, configurar mesas, lista de espera, acceso anticipado, invitados, sin cuenta, confirmar email, plazo, caducar, valores por defecto, duración por defecto, notificaciones, correos, avisos, recordatorio, límites, máximo de jugadores, automatización, estado automático, correos de campaña, plantilla]
related: [game-tables.panel.web-creation-settings, game-tables.panel.participants, game-tables.panel.campaign-roster, game-tables.panel.table-lifecycle]
conditions:
  permissions:
    all: ['gametables:gametables.settings']
---
La configuración del módulo decide cómo se comportan todas las mesas y campañas: la lista de espera, las personas sin cuenta, los valores con que empieza cada mesa nueva y los correos. La parte de creación desde la web tiene su propia guía.

## Abrir la configuración

En el menú lateral, abre **Sistema**, luego **Configuración** y elige **Mesas**. La página se llama **Configuración del módulo**. Al terminar, pulsa **Guardar**.

## Acceso anticipado para el rol {{association_role}}

- **Acceso anticipado para el rol {{association_role}}**: activado, quienes tienen el rol {{association_role}} pueden apuntarse antes de la apertura de cada mesa, tantos días como diga su campo **Días de acceso anticipado para el rol {{association_role}}**. Desactivado, nadie se adelanta.
- **Días de acceso anticipado**: el valor con que empieza ese campo en cada mesa nueva (de 0 a 30). Las mesas creadas desde la web usan este valor.

El acceso anticipado solo cuenta en las mesas que tienen **Apertura de inscripciones**. El rol se elige en la configuración del sitio.

## Inscripciones

**Lista de espera**: viene desactivada. Con ella desactivada, una mesa llena rechaza a más jugadores. Activada:

- Con las plazas de jugador llenas, los jugadores pueden apuntarse a la lista.
- Cada plaza liberada pasa al primero de la lista, hasta el inicio de la partida o el cierre de inscripciones si es antes. Después, solo se promueve a mano.
- En las mesas con confirmación manual, las inscripciones pendientes ocupan plaza, y el promovido queda pendiente de que lo confirmen.
- Ni siquiera desde el panel se puede confirmar o promover por encima de las plazas.

## Inscripciones de invitados

Los invitados son personas sin cuenta que se apuntan con su email en una mesa **Abierto a todos**. Reciben un enlace para confirmar el email.

| Campo | Qué decide |
|---|---|
| **Plaza mientras no confirma** | **Reserva la plaza con plazo**: guarda la plaza hasta que confirme o venza el plazo. **No ocupa plaza hasta confirmar**: no guarda nada; al confirmar entra si queda sitio (o en la lista de espera). |
| **Plazo de confirmación (horas)** | Cuánto tiene para pulsar el enlace (de 1 a 168), nunca más allá del cierre de inscripciones. Al vencer, la inscripción pasa a **Caducada** y se le avisa. |
| **Máximo de plazas sin confirmar por mesa** | Cuántos invitados sin confirmar pueden guardar plaza a la vez en una mesa. Los siguientes esperan sin ocupar plaza. |
| **Email con cuenta registrada** | Qué pasa si el email ya es de una cuenta: **Pedir que inicie sesión** o **Permitir como invitado salvo que esa cuenta ya esté inscrita**. |
| **Inscripciones de invitado por minuto y conexión** y **por hora y conexión** | Frenan los envíos masivos desde una misma conexión. |
| **Ventana de «Posible duplicado» (horas)** | Durante cuántas horas dos inscripciones de la misma conexión y navegador se marcan como **Posible duplicado** en **Participantes**. |

Las inscripciones sin confirmar se revisan cada cinco minutos, aunque la automatización de estados esté desactivada.

## Automatización

**Actualizar el estado de las mesas automáticamente**: viene activado. Cada cinco minutos, las mesas programadas o completas pasan a **En progreso** a su hora de inicio, y las mesas en progreso pasan a **Completada** al terminar. No se envían correos. Desactivado, el estado solo cambia con **Iniciar** y **Completar**.

## Valores por defecto

Son los valores con que empieza cada mesa nueva. Cambiarlos no toca las mesas que ya existen.

- **Duración por defecto (minutos)**, **Mínimo de jugadores por defecto**, **Máximo de jugadores por defecto** y **Máximo de espectadores por defecto**.
- **Confirmar inscripciones automáticamente**: si las mesas nuevas confirman solas.
- **Tipo de inscripción por defecto**: **Abierto a todos** o **Solo el rol {{association_role}}**.

Las mesas creadas desde la web no eligen su confirmación ni su tipo de inscripción: toman estos dos valores. Desde el panel se pueden cambiar después en cada mesa.

## Notificaciones

- **Notificar al inscribirse** y **Notificar al cancelar**: avisan de cada inscripción y de cada baja que hace el propio inscrito a los directores con **Notificar por email** y al **Email de notificaciones** de la mesa.
- **Notificar promoción de lista de espera**: avisa a los directores y al email de la mesa cuando alguien pasa de la lista. La persona promovida recibe siempre su correo.
- **Enviar recordatorio antes de la mesa**: un correo a cada inscripción confirmada, las horas antes del inicio que diga **Antelación del recordatorio (horas)**. Viene desactivado.

## Notificaciones de campañas

Los correos de la plantilla de las campañas, uno por interruptor: **Sesión programada (a la plantilla)**, **Solicitud recibida (a la dirección)**, **Solicitud aceptada**, **Solicitud rechazada**, **Añadido a la plantilla**, **Invitación aceptada (a la dirección)**, **Quitado de la plantilla** y **Salida de la plantilla (a la dirección)**. La guía de la plantilla explica quién recibe cada uno. La invitación a una campaña se envía siempre.

## Límites

- **Límite máximo de jugadores**: el tope del máximo de jugadores de mesas y campañas.
- **Límite máximo de espectadores** y **Duración máxima (minutos)**: los topes de las mesas.

Los valores por defecto no pueden pasar de estos límites.

## Si algo no sale bien

- **«No puede superar el límite máximo de jugadores (…)»** (o de espectadores, o la duración máxima): baja el valor por defecto o sube el límite en la misma pantalla.
- **«No puede superar el máximo de jugadores por defecto (…)»**: el mínimo por defecto es mayor que el máximo por defecto.

[[action:filament.admin.configuracion.pages.game-tables-settings|Abrir la configuración de mesas]]
