---
id: game-tables.panel.campaign-roster
title: Plantilla, solicitudes e invitaciones de una campaña
summary: Gestiona los jugadores fijos de una campaña desde el panel, responde a las solicitudes, invita por email y entiende qué pasa en sus sesiones.
category: activities
audience: panel
order: 70
keywords: [plantilla, jugadores fijos, grupo, roster, solicitudes, solicitud, pedir entrar, aceptar, rechazar, invitar, invitación, invitaciones, reenviar invitación, cancelar invitación, quitar jugador, echar de la campaña, sacar de la plantilla, campaña, campana, correos de campaña]
related: [game-tables.panel.create-campaign, game-tables.panel.campaign-sessions, game-tables.panel.settings, game-tables.panel.participants]
conditions:
  permissions:
    all: ['gametables:campaigns.update']
---
La plantilla son los jugadores fijos de una campaña. Quien está en ella queda inscrito solo en cada sesión que se publica. Desde el panel la gestionas en tres pestañas de la ficha de la campaña. La dirección de la campaña tiene las mismas herramientas en la web.

## Abrir la plantilla

1. En el menú lateral, abre **Mesas de rol** y elige **Campañas**.
2. Entra en la campaña.
3. Baja hasta las pestañas **Plantilla**, **Solicitudes** e **Invitaciones**.

Los cambios en la plantilla solo se pueden hacer con la campaña publicada (o aprobada, si llegó de la web) y mientras no esté **Completada** ni **Cancelada**. Las plazas las marca el **Máximo de jugadores** de la campaña.

## Plantilla

Muestra el **Nombre**, el **Email** y **En la plantilla desde** de cada jugador.

Para quitar a alguien:

1. Pulsa **Quitar** en su fila.
2. Lee el aviso: «Perderá sus plazas en las próximas sesiones y recibirá un correo.»
3. Confirma.

En el panel no hay un botón para añadir directamente: la gente entra aceptando su solicitud o su invitación.

## Solicitudes

Aquí llegan las peticiones de quienes pulsan **Solicitar unirse** en la web. La lista enseña al principio las pendientes; cambia el filtro **Estado** para ver las **Aceptada**, **Rechazada** o **Retirada**.

- **Aceptar**: confirma y la persona entra en la plantilla, si queda sitio.
- **Rechazar**: puedes escribir un **Motivo (opcional)**, que se le envía por correo.

Solo pueden pedir entrar quienes cumplen **Quién puede solicitar**, y solo mientras la campaña acepta jugadores nuevos y le quedan plazas. Las invitaciones siguen las mismas reglas.

## Invitaciones

Para invitar a alguien, con cuenta o sin ella:

1. Pulsa **Invitar jugador**.
2. Escribe su **Email**.
3. Marca «Confirmo que esta persona me ha dado permiso para usar su email para invitarla a la campaña.».
4. Pulsa el botón para enviarla.

La persona recibe el correo «(quien invita) te invita a la campaña «(campaña)»». Si no tiene cuenta, puede crearla desde el enlace. La invitación caduca a los 14 días. La lista muestra **Email**, **Invitada por**, **Envíos** y **Caduca**.

- **Reenviar**: manda el correo otra vez. Han de pasar 24 horas desde el último envío, y a un mismo email no se le pueden mandar más de tres en 30 días por campaña. Si la persona la rechazó, no se le puede volver a invitar durante 30 días.
- **Cancelar**: la invitación deja de valer.

Mientras la campaña no acepta jugadores nuevos, **Invitar jugador** y **Reenviar** aparecen desactivados y la pestaña explica por qué; las invitaciones pendientes no se pueden aceptar hasta que vuelva a aceptarlos. Si la campaña es solo para el rol {{association_role}}, no se puede invitar a una cuenta que no lo tenga, y quien no tiene cuenta tendrá que tenerlo al aceptar.

Una invitación no reserva plaza. Si hay más invitaciones pendientes que plazas libres, la pestaña lo avisa: entrará quien acepte primero.

## Qué pasa en las sesiones

- Quien entra en la plantilla queda inscrito en las sesiones publicadas cuyas inscripciones siguen abiertas: confirmado si hay plaza, o en la lista de espera, con prioridad, si no. Su correo de bienvenida le dice en qué sesiones está.
- Quien sale o es quitado pierde su plaza en las sesiones que aún no han empezado. Las sesiones ya jugadas no cambian.
- Si una sesión estaba llena, la plaza que se libera pasa al primero de la lista de espera.

La guía de sesiones de campaña explica la inscripción automática en detalle.

## Correos

Cada correo se puede apagar en **Configuración** → **Notificaciones de campañas**, salvo la invitación, que siempre se envía.

| Ajuste | Quién lo recibe y cuándo |
|---|---|
| **Sesión programada (a la plantilla)** | Cada jugador inscrito en una sesión nueva |
| **Solicitud recibida (a la dirección)** | La dirección, cuando alguien pide entrar |
| **Solicitud aceptada** | Quien pidió entrar, al aceptarle |
| **Solicitud rechazada** | Quien pidió entrar, al rechazarle |
| **Añadido a la plantilla** | Quien acepta una invitación, con las sesiones en las que queda inscrito |
| **Invitación aceptada (a la dirección)** | La dirección, cuando alguien acepta su invitación |
| **Quitado de la plantilla** | Quien es quitado |
| **Salida de la plantilla (a la dirección)** | La dirección, cuando alguien sale por su cuenta |

## Si algo no sale bien

- **«Esta campaña no está publicada.»**: publícala antes de cambiar la plantilla.
- **«La campaña ha terminado: su plantilla ya no cambia.»**: la campaña está **Completada** o **Cancelada**.
- **«La plantilla está completa.»**: sube el **Máximo de jugadores** de la campaña o quita a alguien.
- **«Quien dirige la campaña no puede entrar en la plantilla como jugador.»**: esa cuenta es de un director.
- **«Esa cuenta ya forma parte de la plantilla.»**: ya está dentro.
- **«La campaña no acepta jugadores nuevos: no se pueden enviar invitaciones mientras siga así.»**: activa **Acepta nuevos jugadores** en la campaña para invitar.
- **«Esta campaña solo admite socios y esa cuenta no es de un socio.»**: la campaña es solo para el rol {{association_role}} y esa cuenta no lo tiene.
- **«Han de pasar 24 horas desde el último envío para volver a enviarla.»**: espera y reenvíala después.
- **«Ya no se pueden enviar más invitaciones a este email en esta campaña por ahora.»**: se alcanzó el límite de envíos o la persona la rechazó hace poco.
- **«Esta solicitud ya no está pendiente.»**: alguien la ha resuelto o la persona la retiró.

[[action:filament.admin.resources.campaigns.index|Ver las campañas|if=gametables:campaigns.view_any]]
