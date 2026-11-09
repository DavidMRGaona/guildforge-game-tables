---
id: game-tables.manage-roster
title: Gestionar la plantilla de tu campaña
summary: Decide quién entra en tu campaña, responde a las solicitudes, invita por email, quita jugadores y gestiona su asistencia a cada sesión.
category: campaigns
audience: site
order: 60
keywords: [plantilla, gestionar plantilla, jugadores, solicitudes, aceptar solicitud, rechazar solicitud, invitar, invitación, reenviar invitación, cancelar invitación, quitar jugador, echar jugador, ausencia, marcar ausencia, volver a inscribir, asistencia, acepta jugadores nuevos, quién puede solicitar, máximo de jugadores, plazas, máster, master, DM, GM, director]
related: [game-tables.campaigns-overview, game-tables.add-session, game-tables.campaign-invitations, game-tables.join-campaign, core.account.mail-preferences]
conditions:
  capabilities: [game-tables.manages_roster]
---
La plantilla son los jugadores fijos de tu campaña. Desde su página decides quién entra, y ves y cambias la asistencia de cada jugador a las próximas sesiones.

## Cómo llegar

- En **Mis campañas**, pulsa **Plantilla** en la campaña. Al lado verás cuántas solicitudes tienes pendientes.
- En la ficha de la campaña, donde dice **Diriges esta campaña**, pulsa **Gestionar plantilla**.
- En los correos de solicitudes y de salidas, pulsa **Revisar solicitudes** o **Ver plantilla**.

Arriba de la página ves «N de M plazas» y quién puede solicitar.

## Ajustes de la campaña

| Ajuste | Qué hace |
|---|---|
| **Acepta jugadores nuevos** | Si lo desactivas, nadie puede solicitar unirse ni aceptar una invitación, no puedes aceptar las solicitudes pendientes, no puedes enviar ni reenviar invitaciones y las sesiones quedan **Solo para la plantilla de la campaña** para quien no es de ella. |
| **Quién puede solicitar** | **Cualquier cuenta**, o solo quien tiene el rol de {{association_role}}. Vale también para las invitaciones. |
| **Máximo de jugadores** | Las plazas de la plantilla. Déjalo vacío para no poner límite. No puede ser menor que la plantilla actual ni pasar del límite de la web. |

Pulsa **Guardar ajustes**. Verás «Ajustes guardados.». Con la campaña terminada no hay ajustes: «La campaña ha terminado: ya no se pueden añadir jugadores.».

## Responder a las solicitudes

En **Solicitudes pendientes** ves quién quiere entrar, cuándo lo pidió y su mensaje («Sin mensaje» si no escribió nada).

- **Aceptar**: entra en la plantilla («Solicitud aceptada.»). Queda inscrito en las sesiones publicadas que aún admiten inscripciones y recibe un correo con ellas.
- **Rechazar**: escribe si quieres un **Motivo (opcional)** y pulsa **Rechazar** («Solicitud rechazada.»). Le llega un correo con el motivo.

Para aceptar hace falta sitio en la plantilla y que la campaña siga aceptando jugadores nuevos. Si la campaña es solo para el rol {{association_role}}, la persona tiene que seguir teniéndolo. Si no se cumple, la solicitud sigue pendiente.

## Invitar a alguien

En **Invitar jugador** (solo mientras quede sitio y la campaña acepte jugadores nuevos; si no los acepta, verás el motivo en lugar del formulario):

1. Escribe el **Email de la persona**.
2. Marca **Confirmo que esta persona me ha dado permiso para usar su email para invitarla a la campaña.**
3. Pulsa **Enviar invitación**.

Verás «Invitación enviada a …». Le llega un correo para aceptar; si no tiene cuenta, puede crearla desde el enlace. La invitación caduca a los 14 días. Para aceptarla, la persona tiene que cumplir las mismas reglas que para solicitar unirse: si la campaña deja de aceptar jugadores nuevos, o es solo para el rol de {{association_role}} y la persona no lo tiene, no puede aceptarla mientras siga así.

En **Invitaciones pendientes** ves cada una con quién la envió, cuándo caduca y cuántos envíos lleva:

- **Reenviar**: vuelve a enviar el correo con un enlace nuevo («Invitación reenviada.»). Han de pasar 24 horas desde el último envío, y hay un máximo de envíos por email.
- **Cancelar**: la invitación deja de valer. Confirma con **Cancelar** («Invitación cancelada.»).

Si ves «Hay más invitaciones pendientes que plazas libres; entrará quien acepte primero.», no todos podrán entrar.

## Los jugadores y su asistencia

En **Jugadores** tienes una tabla: cada fila es un jugador (con la fecha en la que entró) y cada columna, una próxima sesión con sus plazas ocupadas. Usa **Anteriores** y **Siguientes** si hay muchas sesiones.

Cada casilla dice cómo está ese jugador en esa sesión:

| Estado | Qué significa | Qué puedes hacer |
|---|---|---|
| **Va** | Tiene plaza. | **Marcar ausencia** |
| **En espera** | Está en la lista de espera, con prioridad. | **Marcar ausencia** |
| **Ausente** | No va a esa sesión. | **Volver a inscribir**, si queda plaza |
| **No inscrito** | No tiene inscripción en esa sesión. | Nada |

Pulsa la casilla y confirma. Verás «Ausencia marcada.» o «Ausencia quitada.», y el jugador recibe un correo. Una ausencia libera su plaza para el primero de la lista de espera.

Algunas casillas no se pueden cambiar, y lo dicen debajo:

- **Por su cuenta**: se inscribió él mismo, no por la plantilla.
- **Cerrada**: la sesión ya no admite inscripciones.
- **Completa**: no queda plaza para volver a inscribirlo.

## Quitar a un jugador

1. Pulsa **Quitar** en su fila.
2. Confirma con **Quitar**.

Verás «Jugador quitado de la plantilla.». Deja la plantilla y pierde sus plazas en las sesiones que aún no han empezado, y le llega un correo. Puedes quitar jugadores también con la campaña terminada.

## Correos que recibes

Según la configuración de la web, te llega un correo cuando alguien solicita unirse, cuando alguien acepta tu invitación y cuando alguien sale de la plantilla.

Si no los quieres, desactiva «Avisos como director de juego» en la pestaña **Correos** de tu perfil. Dejan de llegarte también los avisos de tus mesas. Las solicitudes pendientes siguen en la página de la plantilla.

[[action:profile.show?tab=mail|Elegir mis correos]]

## Si algo no sale bien

- **«La plantilla está completa.»**: sube el **Máximo de jugadores** o quita a alguien.
- **«Esta campaña no acepta jugadores nuevos ahora mismo, así que no se puede aceptar la solicitud. Seguirá pendiente hasta que vuelva a aceptarlos.»**: activa **Acepta jugadores nuevos** en los ajustes de la campaña y vuelve a aceptarla.
- **«Esta campaña solo admite el rol {{association_role}} y esa cuenta no lo tiene, así que no se puede aceptar la solicitud. Seguirá pendiente.»**: la persona ha dejado de tener el rol {{association_role}} desde que lo pidió.
- **«El máximo de jugadores no puede ser menor que la plantilla actual (N).»**: pon un máximo mayor.
- **«Confirma que esta persona te ha dado permiso para invitarla.»**: marca la casilla del permiso.
- **«Han de pasar 24 horas desde el último envío para volver a enviarla.»**: espera para reenviar.
- **«Ya no se pueden enviar más invitaciones a este email en esta campaña por ahora.»**: has llegado al máximo de envíos, o esa persona rechazó la invitación hace poco.
- **«Esa cuenta ya forma parte de la plantilla.»**: ya está dentro.
- **«La campaña no acepta jugadores nuevos: no se pueden enviar invitaciones mientras siga así.»**: activa **Acepta jugadores nuevos** para invitar.
- **«Esta campaña solo admite el rol {{association_role}} y esa cuenta no lo tiene.»**: la campaña es solo para quien tiene el rol de {{association_role}} y esa cuenta no lo tiene.
- **«Quien dirige la campaña no puede entrar en la plantilla como jugador.»**: esa persona dirige la campaña.
- **«Has enviado demasiadas invitaciones seguidas. Espera un minuto y vuelve a intentarlo.»**: espera un momento.
- **«Esta solicitud ya no está pendiente.»**: la persona la ha retirado o ya se ha respondido.
- **«La sesión ya ha empezado.»** o **«No hay nada que cambiar en esa sesión.»**: la asistencia de esa sesión ya no se puede cambiar.
- **«La campaña ha terminado: su plantilla ya no cambia.»**: ya no entra nadie nuevo.

[[action:campaigns.my-campaigns|Ir a mis campañas]]
