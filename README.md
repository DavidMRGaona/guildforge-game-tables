# Mesas de rol

Módulo de GuildForge para gestionar mesas de rol y campañas: inscripciones de usuarios e invitados, lista de espera, avisos de contenido, herramientas de seguridad, correos, creación de mesas y campañas desde la web con moderación y gestión desde el panel.

## Características

- **Mesas**: sesiones de juego de tipo one-shot, aventura, sesión de campaña, demo o tutorial; presenciales, online o híbridas.
- **Campañas**: agrupan sesiones, con estado (reclutando, activa, en pausa, completada, cancelada) y frecuencia.
- **Directores de juego**: director principal y co-directores, propios de la mesa o heredados de la campaña.
- **Plantilla de la campaña**: los jugadores fijos de una campaña. Se inscriben solos en cada sesión que se publica, solicitan entrar desde la web o aceptan una invitación por email de la dirección. Ver [Plantillas de campaña](#plantillas-de-campaña) e [Invitaciones](#invitaciones).
- **Inscripciones**: como jugador o espectador, con confirmación automática o manual por mesa. Los directores pueden gestionarlas desde la web si la mesa lo permite. Ver [Gestión de inscripciones por los directores](#gestión-de-inscripciones-por-los-directores).
- **Invitados**: quien no tiene cuenta se inscribe con nombre y email en las mesas abiertas a todos, y cancela con un enlace con token.
- **Lista de espera** (ajuste, desactivada por defecto): con las plazas de jugador completas, los jugadores se apuntan a la lista y cada plaza liberada pasa al primero mientras las inscripciones sigan abiertas. Ver [Lista de espera](#lista-de-espera).
- **Tipos de inscripción**: abierta a todos, solo socios (con acceso anticipado opcional) o solo por invitación.
- **Avisos de contenido** con severidad (leve, moderada, severa) y **herramientas de seguridad** (Carta X, Líneas y velos, Puerta abierta, Estrellas y deseos, Flor de apoyo, Script change, Rosas y espinas, otras).
- **Catálogo**: sistemas de juego y editoriales.
- **Creación desde la web**: los usuarios crean mesas y campañas y las envían a revisión; administradores y editores reciben un correo y las aprueban o rechazan desde el panel.
- **Integración con eventos**: configuración de mesas por evento, enlace a las mesas del evento y botón para crear una mesa desde su página.
- **Calendario**: vista `/mesas/calendario` y mesas en el calendario del core, cuya ficha muestra imagen, sinopsis, lugar, sistema, director, plazas y modalidad.
- **Perfil**: pestañas con las mesas en las que participa el usuario, las que ha creado y sus campañas.
- **Correos** de inscripción, confirmación, rechazo, lista de espera, cancelación y moderación.

## Requisitos

- GuildForge core `^3.14` (pestañas del perfil que se ocultan, avisos legales editables de los correos y si la cuenta debe verificar su email; ver [Verificación de email](#verificación-de-email))
- PHP >= 8.5, Laravel >= 13, Filament 5

El core rechaza el módulo si no se cumplen (`module.json` → `requires`).

## Instalación

1. Descarga `game-tables-X.Y.Z.zip` de la última release de `DavidMRGaona/guildforge-game-tables` en GitHub.
2. En el panel, activa el modo mantenimiento (**Configuración del sitio** → pestaña **Mantenimiento**).
3. En **Módulos**, pulsa **Instalar**, sube el ZIP y confirma con **Instalar / actualizar**.
4. Pulsa **Habilitar** en el módulo: la primera vez ejecuta sus migraciones y seeders (editoriales, sistemas de juego y avisos de contenido).
5. Desactiva el modo mantenimiento.

Las versiones nuevas se aplican desde **Actualizaciones de módulos** (también con el modo mantenimiento activo) o con `php artisan module:update game-tables`.

La migración `2026_10_19_000002` crea un índice único (un invitado activo por email y mesa) y falla si ya hay inscripciones de invitado activas con el mismo email en la misma mesa: compruébalas y corrígelas antes de actualizar.

### En desarrollo

Con el repositorio clonado en `src/modules/game-tables`:

```bash
php artisan module:discover
php artisan module:enable game-tables
```

Los componentes Vue se compilan con las herramientas del core, desde `src/`:

```bash
docker exec guildforge_node npm run build:module -- game-tables
```

En las releases los assets los compila el CI y van dentro del ZIP.

## Configuración

Se configura en el panel: **Mesas de rol** → **Configuración** (permiso `gametables.settings`, que los administradores tienen siempre; el mismo formulario está en **Módulos** → **Configuración**, solo para administradores). Los valores iniciales son los que trae el módulo en `config/settings.php`; lo que se guarda en el panel prevalece sobre ellos clave a clave.

| Sección | Qué controla |
|---------|--------------|
| **Inscripciones** | **Lista de espera**: si las mesas con las plazas de jugador completas ofrecen la lista de espera (desactivada: la mesa completa rechaza a los jugadores). Ver [Lista de espera](#lista-de-espera) |
| **Inscripciones de invitados** | **Plaza mientras no confirma** (`guest_confirmation.mode`: `hold` reserva la plaza con plazo, `wait` no ocupa plaza hasta confirmar; por defecto `hold`), **Plazo de confirmación** en horas (`guest_confirmation.hold_hours`, 2), **Máximo de plazas sin confirmar por mesa** (`guest_confirmation.max_unconfirmed_held`, 2), **Email con cuenta registrada** (`guest_accounts.mode`: `require_login` o `allow_guest`; por defecto `require_login`), inscripciones de invitado por minuto y por hora y conexión (`guest_rate_limit.per_minute` 5, `guest_rate_limit.per_hour` 20) y la ventana de «Posible duplicado» en horas (`duplicate_signal.window_hours`, 24). Ver [Inscripciones de invitados](#inscripciones-de-invitados) |
| **Automatización** | **Actualizar el estado de las mesas automáticamente** (clave `automation.auto_status_enabled`, activado por defecto): cada cinco minutos las mesas programadas o completas pasan a «En progreso» a su hora de inicio y las mesas en progreso pasan a «Completada» al terminar. Ver [Cambio de estado automático](#cambio-de-estado-automático) |
| **Acceso anticipado para socios** | **Acceso anticipado para socios** (clave `membership_integration.enabled`): si quienes tienen el rol de socio del core pueden inscribirse antes de la apertura según los días de acceso anticipado de cada mesa (desactivado, se inscriben con la apertura general); no depende del módulo de gestión de socios. **Días de acceso anticipado**: con cuántos empieza cada mesa nueva del panel |
| **Valores por defecto** | Duración y número de jugadores y espectadores con los que empiezan los formularios de mesa del panel y de la web; confirmación automática y tipo de inscripción de las mesas nuevas del panel y de las creadas desde la web. Al guardar, cada valor por defecto se comprueba contra los **Límites** del mismo envío (no contra los guardados antes) y el mínimo de jugadores no puede superar el máximo; si no, el campo muestra el error y no se guarda nada. Los topes fijos siguen por encima: 20 jugadores, 50 espectadores, 720 minutos y 30 días de acceso anticipado |
| **Notificaciones** | Correos de inscripción y de cancelación a los directores y al email de la mesa, y correos de promoción desde la lista de espera (al participante y a los directores) |
| **Notificaciones de campañas** | Ocho interruptores, todos activados por defecto (`campaign_notifications.*`): `session_scheduled`, `join_request_received`, `join_request_accepted`, `join_request_rejected`, `roster_added`, `invitation_accepted`, `roster_removed` y `player_left`. El correo de invitación no tiene interruptor: es la invitación misma. Ver [Correos de las plantillas](#correos-de-las-plantillas) |
| **Límites** | Máximo de jugadores, de espectadores y de duración en los formularios de mesa del panel y de la web (también al validar el envío) |
| **Creación desde web** | Activar la creación desde la web, qué se puede crear (mesas, campañas o ambas), quién puede crear (cualquier persona, usuarios registrados, roles concretos separados por comas o un permiso) y el modo de publicación (`auto`, `approval`, `role_based`) |

Quién crea mesas en el panel lo decide el permiso `gametables.create` (ver [Permisos](#permisos)); quién las crea desde la web, **Creación desde web**.

### Configuración por evento

El formulario de eventos tiene la pestaña **Configuración de mesas**:

- **Habilitar mesas para este evento**: sin esto no se pueden crear mesas desde la web para el evento.
- **Programación**: horario libre o por turnos (nombre, hora de inicio y fin, máximo de mesas por turno).
- **Ubicación**: elección libre, ubicación fija o la del evento.
- **Apertura de la creación** de mesas.
- **Elegibilidad propia** del evento (nivel de acceso, roles o permiso), en lugar de la global.
- **Acceso anticipado** por roles o permiso, con los días de antelación.

## Estados

### Mesa

| Estado | Valor | Descripción |
|--------|-------|-------------|
| Borrador | `draft` | No publicada |
| Programada | `scheduled` | Publicada; admite inscripciones según sus fechas |
| Completa | `full` | Marcada como completa; se sigue mostrando y admite inscripciones mientras queden plazas |
| En progreso | `in_progress` | Sesión en juego |
| Completada | `completed` | Sesión terminada |
| Cancelada | `cancelled` | Mesa cancelada: se cancelan sus inscripciones y se avisa a los participantes |

La publicación es un indicador aparte (`is_published`). **Publicar** deja la mesa en `scheduled` y **Despublicar** la devuelve a `draft`, solo antes de que empiece (`draft`, `scheduled` o `full`): una mesa en curso, completada o cancelada no se despublica, porque en borrador ya no podría iniciarse ni completarse; para quitar de la web una mesa en curso, se cancela. Las inscripciones se admiten mientras la mesa está publicada y en `scheduled` o `full` (o en `in_progress` si tiene activado **Permitir inscripciones durante la partida**), dentro de sus fechas de inscripción y nunca desde la hora de inicio: la fecha de cierre solo puede adelantar ese límite, y el panel no deja guardarla posterior al inicio. Con **Permitir inscripciones durante la partida** el límite es la hora de final (inicio + duración) y la ventana la decide la hora, no el estado: entre el inicio y el final se admiten inscripciones aunque nadie haya pulsado **Iniciar**, solo con plaza libre (sin lista de espera). Aparte, los socios pueden inscribirse antes de la fecha de apertura si la mesa tiene días de acceso anticipado y **Acceso anticipado para socios** está activado. Socio es quien tiene el rol de socio del core: el que se elige en **Configuración del sitio** (`socio` por defecto); no depende del módulo de socios. Si ese ajuste no apunta a un rol válido, nadie cuenta como socio: no hay acceso anticipado y las mesas «Solo socios» no admiten a nadie. Además:

- Las mesas «Solo socios» solo admiten socios; los invitados solo pueden inscribirse en las mesas abiertas a todos.
- Sin plazas de jugador libres no se admiten jugadores nuevos, salvo en la lista de espera si está activada.
- Los espectadores solo necesitan una plaza de espectador libre, aunque las de jugador estén completas; no tienen lista de espera.
- Ocupan plaza las inscripciones confirmadas y, con la lista de espera activada y en mesas con confirmación manual, también las pendientes, de jugador y de espectador. En los demás casos una inscripción pendiente no ocupa plaza.
- Nadie puede tener dos inscripciones activas en la misma mesa, contando la lista de espera (los invitados se distinguen por su email, ya normalizado). Las inscripciones de invitado sin confirmar el email también cuentan como activas.
- Las mesas «Por invitación» no admiten inscripciones desde la web, ni de invitados ni de usuarios con sesión, sean socios o no y sean cuales sean sus fechas: la página dice «Esta mesa es solo por invitación: la organización inscribe a los jugadores» y la organización añade a los participantes desde el panel.
- Los participantes (usuarios desde la página de la mesa e invitados con el enlace de su correo) solo pueden cancelar su inscripción, también la de la lista de espera, antes de la hora de inicio, incluso en las mesas que admiten inscripciones durante la partida; después, el botón y la página del enlace explican que la partida ya ha comenzado. Desde el panel, **Cancelar inscripción** no tiene ese límite, pero tras el inicio no pasa a nadie de la lista de espera.
- Desde la web solo se inscribe como jugador o espectador; los directores se asignan en el panel.

La página de la mesa y el servidor usan el mismo cálculo: el servidor cuenta las plazas ocupadas y libres de jugador y de espectador y los jugadores que esperan en la lista, y la página muestra esas cifras tal cual (las tarjetas de `/mesas`, del calendario del módulo y del core, de las mesas de una campaña y de las mesas creadas en el perfil muestran las plazas de jugador con ese mismo cálculo: solo ocupan plaza las inscripciones confirmadas de jugador y, con la lista de espera activada en mesas con confirmación manual, también las pendientes de jugador; las canceladas, rechazadas, de no presentados, en espera o de espectadores nunca ocupan plaza en ellas); el servidor decide qué ofrece la página a cada rol (inscripción, lista de espera o el motivo del rechazo) y vuelve a aplicarlo al recibir la inscripción; a los invitados solo se les comprueba el email al enviar. Una inscripción que no cumple las reglas no se crea y se responde con el motivo (error de formulario en la web, 422 a los clientes JSON). Los administradores que añaden participantes desde el panel no están sujetos a ellas. En la lista de participantes del panel, el estado solo cambia con las acciones de fila **Confirmar**, **Rechazar**, **Promover**, **Cancelar inscripción** y **No presentado**, que pasan por el servicio de inscripciones y el bloqueo de la mesa; con la lista de espera activada, **Confirmar** y **Promover** nunca superan las plazas de jugador ni de espectador (desactivada no las comprueban, como antes). Las ve quien puede editar la mesa, igual que la página en la que están.

- **Cancelar inscripción** (pide confirmación; para inscripciones pendientes, confirmadas o en la lista de espera, las que se pueden cancelar) hace lo mismo que la cancelación del propio participante: libera la plaza y pasa al primero de la lista de espera cuando corresponde, que recibe su aviso de promoción de siempre. El participante, usuario o invitado, recibe un correo propio («La organización ha cancelado tu inscripción en…»); los directores no reciben el aviso de cancelación (sí el de promoción, si alguien pasa de la lista).
- **No presentado** (pide confirmación; solo para participantes confirmados, una vez empezada la mesa) no envía correos ni promueve a nadie.
- **Editar** cambia las notas y el contacto del invitado (el email en minúsculas, y no el de otro invitado activo de la misma mesa: «Ya existe una inscripción con este email»), no el estado. El rol, el tipo de participante y el usuario no se editan: un cambio de rol o de persona es otra plaza, así que se cancela la inscripción y se añade de nuevo, que pasa por el bloqueo y las reglas de aforo.
- **Borrar** (en una fila o en bloque) solo es para inscripciones canceladas, rechazadas o caducadas, que ya no ocupan ni esperan plaza; para dar de baja a alguien se usa **Cancelar inscripción**, que libera la plaza y avisa a la lista de espera. En bloque se borran solo las seleccionadas que estén canceladas, rechazadas o caducadas, las demás se quedan, y el aviso dice cuántas se han borrado y cuántas hay que cancelar antes. El estado se comprueba al borrar: una inscripción que se reactiva mientras tanto (la persona vuelve a inscribirse) no se borra. Lo ve quien puede editar la mesa, igual que las acciones de estado.

En el panel, la cabecera de la mesa tiene las acciones **Publicar**, **Despublicar**, **Iniciar**, **Completar** y **Cancelar mesa**. **Iniciar** (mesas programadas o completas) y **Completar** (mesas en progreso) pasan por el mismo servicio que el cambio automático: **Completar** emite `GameTableCompleted`, y si la mesa ya ha cambiado de estado entretanto avisan («La mesa ya no está en un estado que permita este cambio») sin cambiar nada. Ninguna mesa en borrador, cancelada o completada se puede iniciar ni completar.

### Gestión de inscripciones por los directores

Cada mesa suelta tiene en el panel la casilla **El director gestiona las inscripciones** (desactivada por defecto). Con ella activada, los directores de la mesa ven y resuelven las inscripciones desde la web, sin pasar por el panel:

- Solo existe para mesas sueltas: las sesiones de una campaña no la ofrecen, y si una mesa con la casilla activada se une a una campaña, la casilla se desactiva sola.
- Exige que todos los directores estén vinculados a una cuenta del sitio; si alguno no lo está, el panel no deja guardarla y dice a quién falta vincular.
- El modo de confirmación (automática o manual) sigue siendo una decisión de la organización: los directores no lo cambian.
- **Mis mesas** (`/mesas/mis-mesas`) separa los borradores de las mesas publicadas y, en las que el usuario dirige con la casilla activada, enlaza a **Gestionar inscritos** (`/mesas/mis-mesas/{slug}/inscritos`) con el número de solicitudes pendientes.
- En **Gestionar inscritos** los directores ven las inscripciones solo con el nombre y el estado (nunca el email ni el teléfono) y pueden aprobar o rechazar las pendientes. Siempre dentro de las plazas de la mesa, también con la lista de espera desactivada, y nunca aprueban a un invitado que no ha confirmado su email ni en una partida terminada o cancelada. Aprobar o rechazar una inscripción que ya no está pendiente avisa y no cambia nada. La organización conserva todos sus permisos en el panel.
- Al rechazar, el motivo es opcional (en el panel y en la web) y, si se escribe, aparece en el correo de rechazo que recibe el participante.
- El correo de nueva inscripción que reciben los directores enlaza a **Gestionar inscritos** cuando la inscripción está pendiente.

#### Historial de inscripciones

La pestaña **Historial de inscripciones** de la mesa en el panel (solo para la organización, no para los directores) registra cada cambio de estado: quién lo hizo (una cuenta, el propio inscrito o el sistema, como en las promociones desde la lista de espera y las caducidades), cuándo y con qué motivo. Los nombres se leen en el momento de mostrarlos, así que una cuenta anonimizada aparece como anonimizada; los de los invitados se conservan tal cual. El comando `game-tables:prune-participant-status-log`, programado a diario, borra el historial 12 meses después del inicio de la mesa.

### Cambio de estado automático

Con **Automatización** → **Actualizar el estado de las mesas automáticamente** activado (por defecto), el comando `game-tables:update-statuses`, que el módulo programa cada cinco minutos sin ejecuciones solapadas, hace lo mismo que **Iniciar** y **Completar**:

- Las mesas **Programada** o **Completa** cuya hora de inicio ha llegado pasan a **En progreso**.
- Las mesas **En progreso** cuyo final (inicio más duración) ha llegado pasan a **Completada** y se emite `GameTableCompleted`. Una mesa programada que ya terminó pasa por los dos estados en la misma ejecución.
- Los borradores y las mesas canceladas o completadas no cambian. Cada mesa se vuelve a leer antes de cambiarla: si alguien la ha cambiado a mano entretanto, se salta; si una falla, se anota en el log y se sigue con las demás. El comando imprime cuántas ha iniciado, completado, saltado y cuántas han fallado.
- No se envían correos. Las horas se comparan en la zona horaria de la aplicación (`APP_TIMEZONE`), la misma en la que se guardan. Las fechas se guardan como hora de pared sin desplazamiento, así que una hora de inicio dentro de la hora que se repite en octubre (de 02:00 a 02:59) se lee como su segunda pasada; desaparecerá cuando el core guarde los instantes en UTC (DavidMRGaona/GuildForge#107).

Las inscripciones no cambian: ya se cierran a la hora de inicio, y las mesas que admiten inscripciones durante la partida las siguen admitiendo en **En progreso** hasta el final. Basta con que el planificador de Laravel esté en marcha (`php artisan schedule:work`, o `php artisan schedule:run` cada minuto); `php artisan schedule:list` muestra la tarea. Con el ajuste desactivado, el estado solo cambia con las acciones del panel.

### Lista de espera

Se activa en **Configuración** → **Inscripciones** → **Lista de espera** (`registration.waiting_list_enabled`, desactivada por defecto). Desactivada, una mesa con las plazas de jugador completas rechaza a los jugadores; la lista solo recoge a quien añade un administrador desde el panel.

Activada:

- **Quién**: solo jugadores, usuarios e invitados, cuando las plazas de jugador están completas y se cumplen las demás reglas de inscripción (fechas, acceso anticipado, tipo de inscripción, no estar ya inscrito). La página muestra **Apuntarse a la lista de espera** con una línea que explica la promoción, y cada persona recibe su puesto (1, 2…). Los espectadores no tienen lista de espera.
- **Puesto**: el número que ven la página de la mesa, el perfil, el mensaje y el correo de inscripción y la columna **Puesto en lista de espera** del panel es el puesto entre quienes esperan con el mismo rol, por posición guardada y después por fecha de inscripción. Las posiciones guardadas no se reescriben cuando alguien sale de la lista, así que pueden tener huecos; el puesto no. El orden de promoción es ese mismo.
- **Promoción automática**: cuando un jugador confirmado cancela (o la organización cancela su inscripción desde el panel), el primer jugador de la lista ocupa su plaza; una promoción por plaza liberada, nunca por encima del máximo de jugadores. Los espectadores en espera nunca pasan a una plaza de jugador.
- **Mesas con confirmación manual**: los jugadores pendientes ocupan plaza (confirmados + pendientes cuentan para el aforo), así que quien llega con la mesa así llena va a la lista detrás de los que ya esperan, y cancelar o **Rechazar** una inscripción pendiente también libera plaza y pasa al primero de la lista (a pendiente). El promovido queda pendiente y el director lo confirma o lo rechaza; los correos al participante y a los directores lo dicen. En las demás mesas el promovido queda confirmado. Los espectadores pendientes también ocupan plaza de espectador: con esas plazas así llenas, la web rechaza a los espectadores nuevos («No quedan plazas de espectador disponibles») y el panel los añade a la lista de espera.
- **Límite en el panel**: con la lista activada, **Confirmar** y **Promover** no dejan superar las plazas de jugador ni las de espectador, contadas como en la inscripción (en mesas con confirmación manual también ocupan plaza los pendientes; un pendiente que ya ocupa la suya solo necesita hueco entre los confirmados). Avisan de que la mesa está completa o de que no quedan plazas de espectador y no cambian nada.
- **Activar la lista en una mesa manual que ya supera su aforo** (confirmados + pendientes por encima de `max_players`): no se toca ninguna fila; quien llega va a la lista, confirmar a un pendiente se rechaza mientras los confirmados estén en el máximo y la lista avanza a medida que se liberen plazas.
- **Cierre**: la lista se cierra con las inscripciones (fecha de cierre pasada, mesa no publicada o en un estado que no admite inscripciones) y siempre a la hora de inicio, también en las mesas que admiten inscripciones durante la partida. Desde entonces no se admiten altas en la lista ni hay promoción automática; las filas se conservan y el director puede promover a mano con la acción **Promover** del panel, que confirma al participante bajo el mismo bloqueo de la mesa y no hace nada si ya no está en espera.
- **Concurrencia**: las inscripciones (también las del panel), cancelaciones, confirmaciones y promociones de una mesa se hacen en una transacción con bloqueo de la fila de la mesa (en PostgreSQL), así que dos cancelaciones simultáneas no promueven dos veces a la misma persona ni sobrepasan el aforo, y dos peticiones simultáneas no crean dos filas para la misma persona.

Con la lista desactivada todo sigue como en la versión 2.5.0 salvo esto: los espectadores no dependen de las plazas de jugador, y las reglas de promoción (una por plaza, sin pasar el aforo, pendiente en mesas con confirmación manual, sin promoción tras el cierre) se aplican a las filas en espera que añade un administrador o que quedan de cuando la lista estaba activada; quien llega a una mesa completa recibe «La mesa está completa». Que los pendientes (jugadores y espectadores) ocupen plaza, que rechazar a un pendiente promueva y el límite de **Confirmar** y **Promover** solo se aplican con la lista activada.

Los emails de los invitados se guardan en minúsculas y se comparan sin distinguir mayúsculas. Quien solo puede entrar como espectador (plazas de jugador completas y sin lista) ve **Inscribirse como espectador**, tenga sesión o no; el invitado ve además un aviso de que las plazas de jugador están completas.

### Inscripciones de invitados

Un invitado se inscribe con nombre, email y consentimiento, y su inscripción queda **sin confirmar** hasta que pulsa el enlace del correo que recibe. Qué pasa mientras tanto lo decide **Plaza mientras no confirma**:

- **`hold`** (por defecto): la inscripción reserva su plaza hasta un plazo de `guest_confirmation.hold_hours` horas (2 por defecto) desde el envío, contadas como tiempo real también la noche del cambio de hora, y el plazo nunca pasa del cierre de inscripciones de la mesa. Si la mesa tiene lista de espera y estaba llena, el invitado conserva su puesto en ella con el mismo plazo.
- **`wait`**: no ocupa plaza hasta que confirma; el correo avisa de que la plaza no está garantizada. Sin plaza, la inscripción espera hasta que se cierran las inscripciones de la mesa.
- **Tope**: como mucho `guest_confirmation.max_unconfirmed_held` (2 por defecto) invitados sin confirmar retienen plaza a la vez en una mesa; los siguientes esperan sin plaza, como en `wait`.
- **Reenvío**: quien vuelve a enviar el formulario con un email pendiente ve «Ya tienes una inscripción pendiente: revisa tu correo». El correo solo se reenvía si el último envío tiene cinco minutos o más; el plazo no se mueve.

**Cuentas.** Solo cuentan las cuentas con el email verificado: una cuenta sin verificar no impide inscribirse como invitado con ese email. Con **Email con cuenta registrada** en `require_login`, ese email se rechaza con «Este email pertenece a una cuenta. Inicia sesión para inscribirte.» y un enlace para iniciar sesión; con `allow_guest` se admite como invitado salvo que esa cuenta ya esté inscrita («Ya hay una inscripción con tu email.»). Si un usuario con sesión se inscribe en una mesa donde ya tenía una inscripción de invitado con su email verificado, la inscripción pasa a su cuenta (se borran los datos personales de invitado: nombre, apellidos, email y teléfono, y el enlace de cancelación) y, si estaba sin confirmar, su email queda confirmado por la cuenta; no se crea otra.

**Enlace de confirmación.** `GET /mesas/confirmar/{token}` solo muestra los datos de la inscripción; la confirmación se hace con el botón (`POST` a la misma ruta), para que un escáner de correo no confirme por el invitado. Al confirmar, la inscripción sin plaza se coloca bajo el bloqueo de la mesa: si ya no hay plaza o las inscripciones se han cerrado, la inscripción pasa a **Caducada**. La misma página muestra el resultado: plaza confirmada, pendiente de aprobación por la organización, en la lista de espera con su puesto, ya confirmada, llena mientras confirmaba, inscripciones cerradas o enlace no válido (caducado, cancelado, desconocido o de una mesa no publicada); este último también al abrir el enlace. Antes de confirmar, la página muestra el estado «Pendiente de confirmar». Está limitado a 20 peticiones por minuto y 100 por hora y conexión; pasado el límite, la página lo explica (estado 429).

**Límite de inscripciones.** El formulario de invitado (solo invitados) admite `guest_rate_limit.per_minute` (5) y `guest_rate_limit.per_hour` (20) envíos por conexión; pasado el límite se muestra un error en el formulario.

**Caducidad.** El comando `game-tables:expire-guest-registrations`, programado cada cinco minutos sin ejecuciones solapadas, pasa a **Caducada** las inscripciones sin confirmar cuyo plazo ha vencido y las que esperaban sin plaza cuando se cierran las inscripciones de su mesa. Solo se envía correo («Inscripción caducada») si la fila retenía plaza; la que esperaba sin plaza caduca en silencio. Al liberarse una plaza se promueve a la lista de espera como en una cancelación. Es independiente de **Actualizar el estado de las mesas automáticamente**: funciona aunque ese ajuste esté desactivado.

**Posible duplicado.** Cada inscripción hecha desde la web guarda una huella de la conexión y el navegador. En el panel, la columna **Avisos** marca **Posible duplicado** en dos inscripciones activas de la misma mesa con la misma huella (al menos una de invitado) hechas con `duplicate_signal.window_hours` horas de diferencia o menos, ventana inclusive. Es solo un aviso; no bloquea nada. La huella se borra cuando termina la mesa. Las columnas de avisos muestran también **Email sin confirmar** y la fecha en que **Caduca**.

**Acciones de la organización.** Las inscripciones que añade la organización desde el panel no pasan por la confirmación del invitado: **Marcar email como confirmado** (como si hubiera pulsado el enlace; si no tenía plaza, la coloca antes bajo el mismo bloqueo y, sin plaza libre, avisa de que la mesa está llena y no cambia nada), **Confirmar** y **Promover** confirman también el email. Confirmar el email de un invitado sin confirmar de cualquiera de estas tres formas no envía a la organización el aviso de inscripción de invitado (es su propia acción) y el invitado recibe un solo correo. Añadir a un invitado desde el panel sigue avisando como siempre.

**Cookie.** El formulario guarda un indicio en el navegador, la cookie `gt_guest_registrations` (categoría preferencias, 90 días), con las mesas en las que se ha inscrito como invitado para avisar si lo intenta otra vez. No se lee ni escribe sin consentimiento. El módulo la declara en el registro de cookies del core, y si cookie-consent está instalado aparece en su banner; sin cookie-consent no hay forma de dar ese consentimiento, así que la cookie nunca se escribe.

### Inscripción

| Estado | Valor | Descripción |
|--------|-------|-------------|
| Pendiente | `pending` | Pendiente de confirmación |
| Confirmado | `confirmed` | Plaza confirmada |
| Lista de espera | `waiting_list` | Sin plaza, en espera |
| Cancelado | `cancelled` | Cancelada por el participante, por la organización desde el panel o al cancelarse la mesa |
| Rechazado | `rejected` | Rechazada por un administrador |
| No presentado | `no_show` | No asistió; lo marca la organización desde el panel, una vez empezada la mesa |
| Caducada | `expired` | Inscripción de invitado que no confirmó su email a tiempo (o antes del cierre de inscripciones, si esperaba sin plaza); no ocupa ni espera plaza y se puede borrar |

### Moderación (creación desde la web)

| Estado | Valor | Descripción |
|--------|-------|-------------|
| Borrador | `draft` | El autor la prepara y la puede editar; si viene de un rechazo, guarda el motivo |
| Pendiente de revisión | `pending_review` | Enviada a revisión |
| Aprobada | `approved` | Aprobada y publicada |
| Rechazada | `rejected` | Solo en filas rechazadas antes de la versión 2.10.0; se trata como un borrador hasta que se convierte |

Las mesas y campañas creadas desde el panel no tienen estado de moderación.

## Creación desde la web

Con **Creación desde web** activada, los usuarios con sesión iniciada que cumplan el nivel de acceso pueden:

1. Crear una mesa en `/mesas/crear` (o desde el botón de la página de un evento, que la asocia al evento) o una campaña en `/campanas/crear`. Si no cumplen los requisitos, ven una página que explica el motivo.
2. Editar sus borradores desde **Mis mesas** (`/mesas/mis-mesas`) o **Mis campañas** (`/campanas/mis-campanas`).
3. Enviarlos a revisión, solo si están en borrador.

El autor solo edita, envía a revisión o borra una mesa o campaña que no esté publicada y esté en borrador (o rechazada antes de la versión 2.10.0), lo mismo que ofrecen **Mis mesas** y **Mis campañas**; el servidor responde 403 a cualquier otra petición. Una mesa o campaña publicada desde el panel cuando aún estaba en borrador queda fuera de su alcance, igual que las creadas desde el panel. Los permisos de edición y borrado del panel (`gametables.update`/`.delete`, `campaigns.update`/`.delete`) siguen dando acceso completo.

En el formulario web de mesas el autor es siempre el único director principal; el resto de filas son co-directores sin cuenta. Su título personalizado, su teléfono y **Mostrar nombre públicamente** se guardan en su ficha de director, que comparten todas las mesas que dirige: el formulario parte de ella y lo indica bajo su tarjeta. Guardar una mesa que añade un co-director pide confirmar el consentimiento de los co-directores; los que ya estaban en la mesa no lo vuelven a pedir.

Según el modo de publicación:

- `auto`: la mesa o campaña queda aprobada y publicada al crearla o al enviarla.
- `approval`: queda pendiente de revisión.
- `role_based`: se publica directamente para los roles o permisos de autopublicación (`frontend_creation.publication.auto_publish_roles` y `auto_publish_permissions`, sin campo en el panel y vacíos por defecto); el resto queda pendiente.

Las mesas y campañas enviadas a revisión se avisan por correo a los usuarios con rol `admin` o `editor` y aparecen en el widget **Pendientes de moderación** del dashboard.

Aprobar o rechazar requiere el permiso de edición (`gametables.update` para mesas, `campaigns.update` para campañas); verlas o ser su autor no basta.

### Flujo de revisión

1. **Borrador**: el autor prepara la mesa o campaña.
2. El autor la **envía a revisión** → **Pendiente de revisión**.
3. Un administrador la **aprueba** → **Aprobada** y publicada.
4. O la **rechaza** con un motivo obligatorio → vuelve a **Borrador**, sin publicar y con el motivo, que el autor ve en **Mis mesas** / **Mis campañas** (y al editar la campaña) hasta que la envía de nuevo; enviarla borra el motivo y el ciclo vuelve a empezar.

Aprobar y rechazar (una a una o, en mesas, en bloque) solo actúan sobre lo que está pendiente de revisión: el servidor rechaza aprobar o rechazar un borrador o una mesa ya aprobada, y las acciones en bloque se saltan lo que no está pendiente.

El panel nunca publica una mesa o campaña creada desde la web que el autor no haya enviado, ni una pendiente de revisión: el interruptor **Publicado** está bloqueado, con el aviso «El autor aún no la ha enviado a revisión.» en borrador o el de usar **Aprobar** o **Rechazar** si está pendiente, y en las mesas no aparece la acción **Publicar**. El servidor lo vuelve a comprobar al guardar, así que un formulario manipulado tampoco la publica. Aprobar es la única forma de publicarlas. Las creadas desde el panel se publican con el interruptor como siempre, y las aprobadas se pueden despublicar y volver a publicar sin que cambie su estado de moderación.

**Mesas**: en **Mesas de rol** → **Mesas** (en la lista o en la página de edición) se aprueban o se rechazan; el autor recibe un correo en ambos casos.

**Campañas**: en **Mesas de rol** → **Campañas** se aprueban o se rechazan, una a una desde la lista, sin correo al autor.

Una mesa creada o editada desde la web solo se puede vincular a una campaña que dirija quien la crea (las que ofrece el formulario); el servidor rechaza cualquier otra con «Solo puedes vincular la mesa a una campaña que dirijas.». Quién dirige una campaña se explica en [Plantillas de campaña](#quién-dirige-una-campaña). Desde el panel se puede vincular cualquier campaña. Al editar una mesa desde la web se puede conservar la campaña que ya tenga, aunque la haya vinculado un administrador desde el panel.

Dirigir una campaña aprobada basta para crear sus sesiones desde la web, aunque las reglas generales de creación (nivel de acceso, rol o permiso) no lo permitieran; solo hace falta que **Creación desde web** esté activada. Quien solo puede crear sesiones (no cumple las reglas generales) ve únicamente el tipo «Sesión de campaña» y solo puede editar o enviar a revisión las sesiones de campañas que dirige. Editar un borrador o enviarlo a revisión aplica siempre las reglas de creación vigentes en ese momento. Esta excepción no alcanza a las sesiones que pertenecen a un evento: esas siguen las reglas de creación del evento como cualquier mesa. Las sesiones siguen pasando por la revisión de mesas según el modo de publicación.

Del mismo modo, una mesa creada o editada desde la web solo se puede vincular a un evento que ofrezca el formulario: publicado, sin terminar y con las mesas activadas. El servidor rechaza cualquier otro con «Solo puedes vincular la mesa a un evento abierto a nuevas mesas». El panel ofrece cualquier evento publicado que no haya empezado, tenga o no las mesas activadas. Al editar una mesa desde la web se puede conservar el evento que ya tenga, aunque haya terminado o lo haya vinculado un administrador desde el panel.

## Plantillas de campaña

Cada campaña tiene una **plantilla**: las personas que juegan la campaña entera. Las campañas ya existentes arrancan con la plantilla vacía.

Las campañas que ya estén cerradas (**Acepta nuevos jugadores** desactivado, o en pausa, completadas o canceladas) rechazan desde que se instala esta versión las inscripciones web de quien no esté en la plantilla. Las inscripciones que ya existían se mantienen. La dirección debe invitar a sus jugadores a la plantilla: cuando un jugador entra, sus inscripciones en las próximas sesiones pasan a ser de la plantilla, sin enviar correos de sesión.

### Quién dirige una campaña

La **dirección** la forman:

- quien creó la campaña, solo si la creó desde la web; quien la crea desde el panel (normalmente para otra persona) no forma parte de la dirección;
- todo director de la campaña que tenga cuenta. Los directores externos, sin cuenta, no dirigen ni reciben correos.

Los correos a la dirección respetan el interruptor **Notificar por email** de cada director; quien creó la campaña en la web los recibe siempre. Las cuentas anonimizadas no cuentan. Los codirectores no ven el motivo de rechazo de moderación dirigido a quien creó la campaña. La dirección gestiona la plantilla y crea sesiones de la campaña desde la web. Quien dirige una campaña no puede entrar en su plantilla como jugador.

### Acceso a la plantilla

Cada campaña define **Quién puede solicitar** (`roster_access`): **Cualquier cuenta** (por defecto) o **Solo socios**. La condición de socio se comprueba con el rol de socio configurado en el core, nunca por nombre de rol. Además:

- la campaña tiene que estar publicada y **abierta**: **Acepta nuevos jugadores** activado y estado Reclutando o Activa. En pausa, completada o cancelada cuenta como cerrada para solicitudes y para inscripciones sueltas;
- el máximo de jugadores de la campaña (vacío = sin límite) limita la plantilla: con la plantilla llena no se puede aceptar ni invitar a nadie, y no puede bajarse por debajo de la plantilla actual;
- una campaña completada o cancelada ya no cambia su plantilla, aunque la dirección puede quitar jugadores.

Se entra de tres formas:

1. **Solicitud.** Desde la ficha de la campaña, con sesión iniciada y un mensaje opcional (hasta 500 caracteres); se puede retirar mientras esté pendiente. Hay un límite de 5 solicitudes por minuto y 20 por hora y usuario. La dirección la acepta o la rechaza (con un motivo opcional) desde la página de plantilla o desde el panel.
2. **Invitación.** La dirección invita por email a cualquier persona, tenga cuenta o no, y la persona entra al aceptar la invitación con una cuenta de ese email. Las cuentas que ya existen también tienen que aceptar: nadie entra en la plantilla sin pulsar **Aceptar**. Ver [Invitaciones](#invitaciones).
3. **Aceptar una solicitud.**

La dirección puede invitar o aceptar mientras la campaña está cerrada a nuevos jugadores o en pausa (no una vez completada o cancelada); solo las solicitudes necesitan una campaña abierta. Las cuentas anonimizadas no cuentan para el límite, no se inscriben, no reciben correos y sus solicitudes pendientes no se muestran.

### Invitaciones

- **Invitar.** Desde la página de plantilla o desde la pestaña **Invitaciones** del panel, la dirección escribe el email y marca la casilla «Confirmo que esta persona me ha dado permiso para usar su email para invitarla a la campaña.», obligatoria. La casilla es una declaración de quien invita, no el consentimiento de la persona invitada. La respuesta es siempre «Invitación enviada a {email}.», tenga cuenta o no, así que el formulario no sirve para averiguar qué emails tienen cuenta. Solo se responde otra cosa si la campaña no está disponible, ha terminado o tiene la plantilla completa, si ese email es de alguien que ya está en la plantilla o dirige la campaña, y cuando se superan los límites de envío. El email se guarda en minúsculas y sin espacios. Hay un límite de 20 invitaciones y reenvíos por minuto y usuario.
- **El correo.** Dice quién invita y a qué campaña (con enlace a su ficha), lleva el botón **Ver invitación**, explica cómo rechazar, indica la caducidad e incluye el aviso de protección de datos (ver [Aviso legal del correo](#aviso-legal-del-correo)). Se envía siempre, sin depender de los ajustes de avisos.
- **La página del enlace** (`/campanas/invitaciones/{token}`) no pide sesión y no se indexa. Muestra un estado según quién la abre:
  - **sin sesión:** la campaña, quién invita, el email que hay que usar y los botones **Iniciar sesión** y **Crear cuenta**, que vuelven a la invitación al terminar;
  - **con la cuenta de ese email** (sin distinguir mayúsculas): **Aceptar** y **Rechazar**;
  - **con una cuenta que debe verificar su email:** solo **Rechazar** y el aviso «Verifica tu email para aceptar.», con un enlace a la verificación que vuelve a la invitación al verificar;
  - **con otra cuenta:** «Esta invitación es para otro email», sin decir cuál, y **Cerrar sesión**;
  - **recién rechazada:** «Invitación rechazada»;
  - **caducada, usada, cancelada o inexistente:** un único mensaje genérico, «Invitación no válida».

  Hay un límite de 30 visitas por minuto y conexión.
- **Aceptar** es siempre un clic explícito de una cuenta con el email invitado; registrarse no cuenta como aceptar. Si el sitio exige verificación de email, la cuenta verifica antes; si no la exige, basta el enlace, que prueba que la persona controla ese buzón. Aceptar sigue las mismas reglas y correos que la entrada por la dirección (sesiones, correo de entrada, solicitud pendiente dada por aceptada), salta **Solo socios** y avisa a la dirección («{nombre} ha aceptado tu invitación», interruptor `invitation_accepted`).
- **Rechazar** solo necesita el enlace, sin cuenta y con confirmación (nunca con solo abrir el enlace): la invitación y el email se borran en el acto y la dirección no recibe aviso.
- **Pestaña del perfil.** La subpestaña **Invitaciones** de **Mesas**, con un contador, lista las invitaciones pendientes del email de la cuenta (también las enviadas antes de registrarse) con **Aceptar** y **Rechazar**. Solo aparece si el email de la cuenta está verificado y hay al menos una invitación pendiente; el servidor comprueba las dos cosas en cada acción, aunque se fuerce la petición.
- **Lista de la dirección.** La página de plantilla y la pestaña **Invitaciones** del panel muestran las invitaciones pendientes con el email, quién invitó, los envíos y la caducidad, con **Reenviar** y **Cancelar** (cancelar la borra). Reenviar manda un enlace nuevo (el anterior deja de valer) y no alarga la caducidad. Si hay más invitaciones pendientes que plazas libres, avisa: «Hay más invitaciones pendientes que plazas libres; entrará quien acepte primero.».
- **Límites** por campaña y email, entre todas las invitaciones (cancelar y volver a invitar no reinicia la cuenta): como máximo 3 envíos en 30 días, al menos 24 horas entre dos envíos y 30 días sin poder invitar tras un rechazo. Cancelar no bloquea, pero sus envíos cuentan.
- **Plazas.** Una invitación no reserva plaza: el máximo de jugadores se comprueba al invitar y al aceptar. Si la plantilla está completa al aceptar, la invitación sigue pendiente hasta caducar, por si se libera una plaza.
- **Conservación.** Una invitación caduca a los 14 días de crearse y se borra al aceptarla, rechazarla, cancelarla o caducar, y también cuando la campaña ha terminado o no está ni aprobada ni publicada. Una invitación pertenece a la campaña: sigue valiendo aunque quien la envió deje la dirección o borre su cuenta. Para los límites queda un registro de envíos sin el email en claro (un HMAC de la campaña y el email con la clave de la aplicación), que se borra a los 30 días. La limpieza la hace cada día `game-tables:prune-campaign-invitations`.

#### Aviso legal del correo

El bloque de protección de datos del correo de invitación (finalidad, legitimación, origen, conservación y derechos) se edita en el core, en **Páginas legales → Avisos en correos**, con una vista previa del correo. Los huecos `:inviter`, `:campaign`, `:site` y `:days` se rellenan solos; un texto vacío vuelve al texto por defecto del módulo. El responsable y sus contactos salen siempre de la pestaña **Datos de la asociación** de **Páginas legales** (con el nombre del sitio si el nombre legal está vacío), y el resto del correo, con el enlace a la política de privacidad, no se edita. La política de privacidad del sitio debe recoger esta finalidad.

La pestaña del perfil que se oculta y el aviso editable usan el core `^3.14`, la versión mínima que exige el módulo.

### Páginas de la web

- **Invitación** (`/campanas/invitaciones/{token}`): la página del enlace del correo (ver [Invitaciones](#invitaciones)).
- **Ficha de la campaña**: la caja de unirse muestra el estado de cada persona (invitada, con solicitud pendiente, de la plantilla, campaña completa o cerrada, solo socios o dirección). La plantilla puede **Salir de la campaña** con confirmación.
- **Mis campañas** (`/campanas/mis-campanas`): además de los borradores, lista las campañas aprobadas o publicadas que la persona dirige, también las completadas o canceladas (sin **Añadir sesión**), con el número de solicitudes pendientes y los accesos **Plantilla** y **Añadir sesión**.
- **Plantilla** (`/campanas/mis-campanas/{id}/plantilla`): ajustes (acepta jugadores nuevos, quién puede solicitar y máximo de jugadores; no devuelven la campaña a revisión), solicitudes pendientes, el formulario para invitar, las invitaciones pendientes y la lista de jugadores con una ficha por sesión próxima (va, no va, en espera). El resto de la ficha de una campaña publicada solo se edita en el panel.

### Inscripción al publicar una sesión

Una **sesión de campaña** es cualquier mesa con campaña, sea cual sea su tipo. Cuando queda publicada y admite inscripciones, la plantilla se inscribe sola: por aprobación en la revisión, por publicación directa o desde el panel (el interruptor, el estado o la acción **Publicar**, que pasa por el mismo servicio). No se inscribe al crear el borrador.

- Las inscripciones de la plantilla son de jugador y quedan **confirmadas**, sin pasar por las reglas de la mesa (tipo de inscripción, solo socios o confirmación manual). Cada persona recibe un solo correo de sesión programada.
- **Entrada tardía.** Quien entra en la plantilla cuando ya hay sesiones publicadas se inscribe en las que siguen admitiendo inscripciones; si una sesión ya está llena de gente de fuera, pasa a su lista de espera con prioridad de plantilla. El correo de entrada lista solo las sesiones en las que quedó inscrito.
- **Publicar otra vez** (despublicar y volver a publicar, o un cambio entre Completa y Programada) no crea una segunda fila ni manda un segundo correo. La sincronización solo añade a quien no tiene inscripción y reactiva las canceladas por salir de la plantilla; nunca toca las canceladas por una baja de esa sesión, ni las rechazadas, de no presentados o caducadas. El mismo criterio vale cuando alguien vuelve a apuntarse a una sesión: el camino de la plantilla no deshace una decisión de la organización.
- **Aforo.** La plantilla siempre cabe: una sesión nueva toma por defecto el máximo de la campaña (o el tamaño de la plantilla, si es mayor) y el servidor rechaza un máximo de jugadores menor que la plantilla al crear, editar o publicar, también desde el panel. Las inscripciones que añade el personal desde el panel pueden superar lo que necesita la plantilla.

### Bajas de una sesión y salidas

- **Baja de una sola sesión** («No puedo ir a esta sesión», con el enlace de cancelación del correo, o marcada por la dirección en la ficha de la plantilla). La persona sigue en la plantilla y en las demás sesiones, y la sincronización no la vuelve a inscribir en esa sesión, ni aunque salga de la campaña y vuelva a entrar o la sesión se despublique y se publique de nuevo. Puede **Volver a apuntarse** mientras haya sitio y las inscripciones sigan abiertas, sin pasar por las reglas de la mesa. La dirección puede marcar una ausencia hasta que empiece la sesión y quitarla mientras admita inscripciones. La baja usa los correos de cancelación que ya existían, con sus ajustes.
- **Salir de la campaña.** La persona pierde sus plazas en todas las sesiones próximas que no han empezado, publicadas o no, y no recibe correo. Las sesiones pasadas no se tocan. Si la dirección la quita, sí recibe un correo (no uno por sesión). Las plazas que se liberan se anuncian como cualquier promoción de la lista de espera.
- **Plaza liberada.** En una campaña abierta queda para inscripciones sueltas o la lista de espera, como cualquier baja; en una cerrada queda vacía.

### Campañas cerradas y abiertas

- **Cerrada** (sin nuevos jugadores, en pausa, completada o cancelada): solo se inscribe la plantilla. El resto ve «Solo para la plantilla de la campaña» con un enlace a la campaña, no se le ofrece el formulario de invitado y el servidor rechaza las dos vías. El personal puede saltarse esta regla al inscribir desde el panel.
- **Abierta**: se puede ir a una sesión con las reglas normales de la mesa (invitados, aprobación, lista de espera), lo que no hace entrar en la plantilla. La ficha de la sesión enlaza a «Solicita unirte».

### Panel

La ficha de la campaña tiene tres pestañas de relación, **Plantilla** (quitar jugadores), **Invitaciones** (invitar, reenviar y cancelar; ver [Invitaciones](#invitaciones)) y **Solicitudes** (aceptar y rechazar), con las mismas reglas, correos y servicio que la web. Se ven y se usan con el permiso de edición de campañas (`campaigns.update`); no hace falta ningún permiso de usuarios del core. **Quién puede solicitar** está en la pestaña **Progreso**, junto al máximo de jugadores, y **Acepta nuevos jugadores** en **Publicación**.

### Correos de las plantillas

Nueve correos. El de invitación se envía siempre, porque es la invitación misma; los otros ocho tienen su interruptor en **Notificaciones de campañas** (todos activados por defecto):

| Interruptor | Correo | Destinatarios |
|-------------|--------|---------------|
| `session_scheduled` | Sesión programada | Cada persona de la plantilla al publicarse una sesión (con variante si queda en la lista de espera) |
| `join_request_received` | Solicitud recibida | Dirección |
| `join_request_accepted` | Solicitud aceptada | Quien la envió, con las sesiones en las que queda inscrito |
| `join_request_rejected` | Solicitud rechazada | Quien la envió, con el motivo si lo hay |
| — | Invitación a la campaña | El email invitado, al invitar y en cada reenvío |
| `roster_added` | Añadido a la plantilla | La persona añadida (también al aceptar una invitación), con sus sesiones |
| `invitation_accepted` | Invitación aceptada | Dirección |
| `roster_removed` | Quitado de la plantilla | La persona quitada |
| `player_left` | Salida de la plantilla | Dirección |

Los textos que escriben las personas (mensaje de la solicitud, motivo del rechazo, nombres) se muestran tal cual en el correo, nunca como formato.

## Verificación de email

Con **Requerir verificación de email** activado en el core, el servidor cierra a las cuentas que deben verificar su email (sin verificar y creadas desde la fecha de corte del core) todas las rutas con sesión del módulo: inscribirse y cancelar la inscripción, y crear, editar, enviar a revisión o borrar mesas y campañas desde la web. Las visitas van a la página de aviso de verificación y las peticiones JSON reciben un 403. El módulo no cambia esas reglas; la interfaz las anticipa con `useVerificationGate()` del core (`auth.user.mustVerifyEmail`):

- **Botón de inscripción** de la página de la mesa: si la cuenta podría inscribirse o cancelar su inscripción, el botón se muestra desactivado con el motivo («Para inscribirte, verifica tu email.» o «Para cancelar tu inscripción, verifica tu email.») y el enlace «Ver cómo →» al aviso; el diálogo de rol no llega a abrirse. Si el botón ya estaba desactivado por otro motivo (inscripciones cerradas, sin plazas), se muestra como antes.
- **Crear mesa** en la página del evento y **crear mesa o campaña** en las pestañas del perfil: desactivados con su motivo y el enlace al aviso (en el perfil, el motivo se muestra una vez por pestaña).
- **Editar** un borrador desde el perfil: desactivado; la lista de borradores muestra una vez «Para editar, verifica tu email.» con el enlace al aviso.
- Los formularios de creación y edición, **Mis mesas** y **Mis campañas** no llegan a mostrarse a esas cuentas: el servidor las lleva al aviso. Si una inscripción se envía igualmente (por ejemplo, porque el estado cambió en otra pestaña), la página termina en el aviso sin mostrar error ni mensaje de éxito.

Los invitados (inscripción sin cuenta y cancelación con el enlace del correo), las cuentas verificadas, las creadas antes de la fecha de corte y todas cuando el ajuste está desactivado ven y usan el módulo como antes.

## Panel de administración

| Grupo | Elemento | Contenido |
|-------|----------|-----------|
| Mesas de rol | Mesas | Formulario por pestañas (información básica, dirección, horario y ubicación, capacidad y requisitos, contenido y seguridad, inscripciones, publicación), participantes y moderación |
| Mesas de rol | Campañas | Campañas, sus directores y publicación, y las pestañas **Plantilla** y **Solicitudes** (ver [Plantillas de campaña](#plantillas-de-campaña)) |
| Mesas de rol | Avisos de contenido | Catálogo de avisos con severidad |
| Mesas de rol | Configuración | Configuración del módulo (permiso `gametables.settings`) |
| Catálogo de juegos | Editoriales | Editoriales de los sistemas de juego (también se crean desde el formulario de sistemas) |
| Catálogo de juegos | Sistemas de juego | Sistemas con su editorial |

Widget del dashboard: `PendingModerationWidget` (mesas y campañas pendientes de revisión; solo se muestra si hay alguna).

## Web pública

### Rutas

| Método | Ruta | Nombre | Acceso |
|--------|------|--------|--------|
| `GET` | `/mesas` | `gametables.index` | Público |
| `GET` | `/mesas/calendario` | `gametables.calendar` | Público |
| `GET` | `/mesas/{slug}` | `gametables.show` | Público |
| `POST` | `/mesas/{gameTable}/inscripcion` | `gametables.register` | Usuarios autenticados |
| `DELETE` | `/mesas/{gameTable}/inscripcion` | `gametables.cancel` | Usuarios autenticados |
| `POST` | `/mesas/{gameTable}/inscripcion-invitado` | `gametables.register-guest` | Público |
| `GET` | `/mesas/confirmar/{token}` | `gametables.confirm-guest` | Invitados con enlace |
| `POST` | `/mesas/confirmar/{token}` | `gametables.confirm-guest.store` | Invitados con enlace |
| `GET` | `/mesas/cancelar/{token}` | `gametables.cancel-confirmation` | Invitados con enlace |
| `DELETE` | `/mesas/cancelar/{token}` | `gametables.cancel-by-token` | Invitados con enlace |
| `GET` / `POST` | `/mesas/crear` | `gametables.create` / `gametables.store` | Usuarios autenticados |
| `GET` | `/mesas/mis-mesas` | `gametables.my-tables` | Usuarios autenticados |
| `GET` | `/mesas/mis-mesas/{gameTable}/editar` | `gametables.edit` | Autor |
| `PUT` / `DELETE` | `/mesas/mis-mesas/{gameTable}` | `gametables.update` / `gametables.destroy` | Autor |
| `POST` | `/mesas/mis-mesas/{gameTable}/enviar-revision` | `gametables.submit-review` | Autor |
| `GET` | `/mesas/mis-mesas/{gameTable}/inscritos` | `gametables.registrations` | Directores de la mesa con la gestión delegada |
| `POST` | `/mesas/mis-mesas/{gameTable}/inscritos/{participantId}/aprobar` y `…/rechazar` | `gametables.registrations.approve` / `gametables.registrations.reject` | Igual que la anterior |
| `GET` | `/campanas` | `campaigns.index` | Público |
| `GET` | `/campanas/{slug}` | `campaigns.show` | Público |
| `GET` / `POST` | `/campanas/crear` | `campaigns.frontend-create` / `campaigns.frontend-store` | Usuarios autenticados |
| `GET` | `/campanas/mis-campanas` | `campaigns.my-campaigns` | Usuarios autenticados |
| `GET` | `/campanas/mis-campanas/{id}/editar` | `campaigns.frontend-edit` | Autor |
| `PUT` / `DELETE` | `/campanas/mis-campanas/{id}` | `campaigns.frontend-update` / `campaigns.frontend-destroy` | Autor |
| `POST` | `/campanas/mis-campanas/{id}/enviar-revision` | `campaigns.submit-review` | Autor |
| `GET` | `/campanas/mis-campanas/{id}/plantilla` | `campaigns.roster` | Dirección |
| `PUT` | `/campanas/mis-campanas/{id}/plantilla/ajustes` | `campaigns.roster.settings` | Dirección |
| `DELETE` | `/campanas/mis-campanas/{id}/plantilla/jugadores/{userId}` | `campaigns.roster.remove` | Dirección |
| `POST` | `/campanas/mis-campanas/{id}/plantilla/invitaciones` | `campaigns.roster.invite` | Dirección |
| `POST` / `DELETE` | `/campanas/mis-campanas/{id}/plantilla/invitaciones/{invitationId}[/reenviar]` | `campaigns.roster.invitation.resend` / `campaigns.roster.invitation.cancel` | Dirección |
| `POST` | `/campanas/mis-campanas/{id}/plantilla/solicitudes/{requestId}/aceptar` / `rechazar` | `campaigns.roster.accept` / `campaigns.roster.reject` | Dirección |
| `POST` | `/campanas/mis-campanas/{id}/plantilla/ausencias` | `campaigns.roster.absence` | Dirección |
| `POST` / `DELETE` | `/campanas/{slug}/solicitud` | `campaigns.join.request` / `campaigns.join.withdraw` | Usuarios autenticados |
| `DELETE` | `/campanas/{slug}/plantilla` | `campaigns.leave` | Plantilla |
| `GET` | `/campanas/invitaciones/{token}` | `campaigns.invitation.show` | Con el enlace |
| `GET` | `/campanas/invitaciones/{token}/iniciar-sesion` / `crear-cuenta` / `verificar` | `campaigns.invitation.login` / `campaigns.invitation.register` / `campaigns.invitation.verify` | Con el enlace |
| `POST` | `/campanas/invitaciones/{token}/aceptar` | `campaigns.invitation.accept` | La cuenta del email invitado |
| `POST` | `/campanas/invitaciones/{token}/rechazar` | `campaigns.invitation.reject` | Con el enlace |
| `POST` | `/campanas/invitaciones/perfil/{invitationId}/aceptar` / `rechazar` | `campaigns.invitation.profile-accept` / `campaigns.invitation.profile-reject` | La cuenta del email invitado, verificada |

`{gameTable}` es el slug de la mesa; en las rutas de **Mis mesas** (`/mesas/mis-mesas/…`) también vale su id, la forma que llevan los correos de rechazo enviados antes de la versión 2.8.0 (los nuevos enlazan por slug). En las rutas de **Mis campañas**, `{id}` es el id de la campaña. El módulo no tiene API: el número de mesas de un evento llega a su página como prop de Inertia (`gameTableCount`). `gametables.index`, `gametables.calendar` y `campaigns.index` aparecen entre las páginas que se pueden enlazar desde el editor de menús.

### Páginas

| Página | Ruta |
|--------|------|
| `GameTables/Index` | `/mesas` |
| `GameTables/Calendar` | `/mesas/calendario` |
| `GameTables/Show` | `/mesas/{slug}` |
| `GameTables/CancelRegistration` | `/mesas/cancelar/{token}` |
| `GameTables/Create`, `GameTables/CreateNotEligible` | `/mesas/crear` |
| `GameTables/Edit` | `/mesas/mis-mesas/{gameTable}/editar` |
| `GameTables/MyTables` | `/mesas/mis-mesas` |
| `GameTables/Registrations` | `/mesas/mis-mesas/{gameTable}/inscritos` |
| `Campaigns/Index` | `/campanas` |
| `Campaigns/Show` | `/campanas/{slug}` |
| `Campaigns/Create`, `Campaigns/CreateNotEligible` | `/campanas/crear` |
| `Campaigns/Edit` | `/campanas/mis-campanas/{id}/editar` |
| `Campaigns/MyCampaigns` | `/campanas/mis-campanas` |
| `Campaigns/Roster` | `/campanas/mis-campanas/{id}/plantilla` |
| `Campaigns/Invitation` | `/campanas/invitaciones/{token}` |

### Componentes

| Componente | Descripción |
|------------|-------------|
| `GameTableCard`, `GameTableCardSkeleton` | Tarjeta de mesa y su esqueleto de carga |
| `GameTableFilters` | Filtros del listado |
| `RegistrationButton` | Inscripción con selección de rol (slot `game-table-registration`) |
| `RegistrationStatus` | Estado de la inscripción del usuario |
| `GuestRegistrationModal` | Inscripción de invitados |
| `StatusBadge`, `FormatBadge`, `ContentWarningBadge`, `SafetyToolBadge` | Insignias |
| `GameMasterSection` | Directores de juego |
| `FormTabs` | Pestañas de los formularios de creación |
| `EventTablesLink` | Enlace a las mesas del evento (slot `event-detail-actions`) |
| `EventCreateTableButton` | Botón «crear mesa» en la página del evento (slot `event-detail-actions`) |
| `profile/*` | Pestañas del perfil: Mesas, Mis mesas, Campañas e Invitaciones (slot `profile-sections`) |
| `VerificationBlockedButton` | Acción desactivada con su motivo mientras la cuenta debe verificar su email (ver [Verificación de email](#verificación-de-email)) |

## Correos

| Momento | Destinatarios |
|---------|---------------|
| Inscripción (usuario o invitado) | Participante; directores y email de la mesa si **Notificaciones** lo permite |
| Inscripción de invitado sin confirmar | Invitado, con el enlace para confirmar el email (y el plazo, si reserva plaza); los avisos de inscripción a la organización salen al confirmar el invitado, no al inscribirse |
| Inscripción de invitado caducada | Invitado, solo si la inscripción retenía plaza |
| Confirmación o rechazo de la inscripción | Participante (el rechazo incluye el motivo, si se escribió) |
| Cancelación de la inscripción por el participante | Participante; directores y email de la mesa si **Notificaciones** lo permite |
| Cancelación de la inscripción por la organización (panel) | Participante, con su propio correo; los directores y el email de la mesa no reciben el aviso de cancelación (sí el de promoción si alguien sale de la lista de espera) |
| Promoción desde la lista de espera | Participante siempre; directores y email de la mesa si **Notificaciones** lo permite; en las mesas con confirmación manual indican que la inscripción queda pendiente |
| Cancelación de la mesa | Participantes |
| Recordatorio antes de la mesa | Participantes confirmados (con cuenta o con el email confirmado), una vez, las horas configuradas antes del inicio; desactivado por defecto (`game-tables:send-reminders`, cada hora) |
| Sesión de campaña programada | Plantilla (ver [Correos de las plantillas](#correos-de-las-plantillas)) |
| Solicitud de entrada recibida, aceptada o rechazada | Dirección (recibida) o quien la envió (aceptada y rechazada) |
| Invitación a una campaña | El email invitado, siempre (ver [Invitaciones](#invitaciones)) |
| Invitación aceptada | Dirección |
| Añadido o quitado de la plantilla | La persona afectada |
| Salida de la plantilla | Dirección |
| Mesa o campaña enviada a revisión | Usuarios con rol `admin` o `editor` |
| Mesa o campaña aprobada o rechazada | Autor (el rechazo incluye el motivo y el enlace para editarla) |

## Eventos de dominio

| Evento | Cuándo |
|--------|--------|
| `GameTableCreated` | Se crea una mesa |
| `GameTablePublished` | Se publica una mesa |
| `GameTableCancelled` | Se cancela una mesa |
| `GameTableCompleted` | Se completa una mesa |
| `GameTableSubmittedForReview` | Una mesa creada desde la web se envía a revisión |
| `GameTableModerationApproved` | Se aprueba una mesa en moderación |
| `GameTableModerationRejected` | Se rechaza una mesa en moderación |
| `CampaignSubmittedForReview` | Una campaña creada desde la web se envía a revisión |
| `CampaignModerationApproved` | Se aprueba una campaña en moderación |
| `CampaignModerationRejected` | Se rechaza una campaña en moderación |
| `ParticipantRegistered` | Un usuario se inscribe |
| `GuestRegistered` | Un invitado se inscribe |
| `ParticipantConfirmed` | Se confirma una inscripción |
| `ParticipantRejected` | Se rechaza una inscripción |
| `ParticipantCancelled` | Se cancela una inscripción (`byOrganization` cuando la cancela la organización desde el panel) |
| `ParticipantPromotedFromWaitingList` | Un participante sale de la lista de espera |
| `CampaignJoinRequested`, `CampaignJoinRequestAccepted`, `CampaignJoinRequestRejected` | Se envía, se acepta o se rechaza una solicitud de entrada |
| `CampaignRosterMemberAdded`, `CampaignRosterMemberRemoved`, `CampaignRosterMemberLeft` | Alguien entra en la plantilla, la dirección le quita o sale por su cuenta |
| `CampaignInvitationSent`, `CampaignInvitationAccepted` | Se envía (o reenvía) una invitación, o alguien la acepta |
| `RosterMemberEnrolledInSession` | Una persona de la plantilla queda inscrita en una sesión |

`CampaignCreated` y `CampaignStatusChanged` están definidos pero hoy no se emiten.

## Permisos

El módulo registra sus permisos con el prefijo `gametables:` (por ejemplo `gametables:gametables.view_any`).

| Permiso | Rol propuesto | Uso |
|---------|-----------------|-----|
| `gametables.view_any`, `gametables.view` | editor | Ver mesas en el panel |
| `gametables.create`, `gametables.update` | editor | Crear y editar mesas |
| `gametables.delete` | — | Eliminar mesas |
| `campaigns.view_any`, `campaigns.view` | editor | Ver campañas en el panel |
| `campaigns.create`, `campaigns.update` | editor | Crear y editar campañas |
| `campaigns.delete` | — | Eliminar campañas |
| `gamesystems.view_any`, `gamesystems.manage` | — | Ver y gestionar sistemas de juego y editoriales |
| `contentwarnings.view_any`, `contentwarnings.manage` | — | Ver y gestionar avisos de contenido |
| `gametables.settings` | — | Abrir y guardar la página de configuración del módulo |

La columna de rol indica el rol al que el módulo propone el permiso; habilitar el módulo no lo asigna, se concede en **Roles**. Los administradores tienen acceso completo. La dirección de una campaña gestiona su plantilla desde la web sin ningún permiso del panel. Los autores gestionan sus propias mesas y campañas desde «Mis mesas» y «Mis campañas»; solo pueden editarlas, enviarlas a revisión o borrarlas mientras no estén publicadas y estén en borrador o rechazadas.

## Comandos

```bash
php artisan gametables:generate-slugs            # genera slugs de mesas y campañas que no lo tienen
php artisan gametables:generate-slugs --dry-run  # muestra qué haría sin cambiar nada
php artisan gametables:generate-slugs --force    # regenera también los existentes
php artisan game-tables:update-statuses          # inicia y completa las mesas según su hora (programado cada cinco minutos)
php artisan game-tables:expire-guest-registrations  # caduca invitados sin confirmar y borra huellas de mesas terminadas (programado cada cinco minutos)
php artisan game-tables:prune-campaign-invitations  # borra las invitaciones caducadas o de campañas que ya no están abiertas y los registros de envíos de más de 30 días (programado a diario)
php artisan game-tables:prune-participant-status-log  # borra el historial de inscripciones 12 meses después del inicio de la mesa (programado a diario)
```

## Limitaciones actuales

- Las rutas de creación desde la web exigen sesión iniciada, también con el nivel de acceso «Cualquier persona».
- Las mesas «Solo por invitación» no tienen sistema de invitaciones (las invitaciones por email son de las campañas): nadie se inscribe desde la web (ni invitados ni usuarios con sesión, que ven «Esta mesa es solo por invitación: la organización inscribe a los jugadores») y la organización añade a los participantes desde el panel.

## Arquitectura

```
src/modules/game-tables/
├── config/
│   ├── module.php
│   └── settings.php           # Valores iniciales de la página de configuración
├── database/
│   ├── migrations/
│   └── seeders/               # Editoriales, sistemas de juego, avisos de contenido; DevelopmentSeeder (solo local y testing)
├── lang/
│   ├── en/                    # emails.php, messages.php
│   └── es/                    # emails.php, messages.php
├── resources/
│   ├── css/
│   ├── js/
│   │   ├── components/        # Componentes Vue (y profile/)
│   │   ├── locales/           # Textos del frontend
│   │   ├── pages/             # GameTables/, Campaigns/
│   │   └── types/
│   └── views/filament/        # Vista de la pestaña del formulario de eventos
├── routes/
│   └── web.php
├── src/
│   ├── Application/           # DTOs e interfaces de servicios
│   ├── Console/Commands/      # gametables:generate-slugs, game-tables:update-statuses
│   ├── Domain/                # Entidades, enums, eventos, excepciones, repositorios, VOs
│   ├── Filament/              # Resources, RelationManagers, Pages, Widgets, Forms
│   ├── Http/                  # Controladores, requests y resources
│   ├── Infrastructure/        # Calendario, listeners, observers, persistencia y servicios
│   ├── Listeners/             # Correos de inscripción y moderación
│   ├── Notifications/
│   ├── Policies/
│   └── GameTablesServiceProvider.php
├── tests/
│   ├── Unit/
│   ├── Integration/
│   └── Feature/
├── module.json
├── phpunit.xml
└── vite.config.ts
```

## Tests

Desde `src/` (o dentro del contenedor `guildforge_app`):

```bash
vendor/bin/phpunit -c modules/game-tables/phpunit.xml
vendor/bin/phpunit -c modules/game-tables/phpunit.xml --testsuite=Unit
```

`php artisan test` no ejecuta los tests de los módulos.

Las duraciones y plazos en horas o minutos se suman como tiempo transcurrido (`add(new DateInterval('PT…'))`), nunca con `modify('+N hours')` ni `new DateTimeImmutable('-N minutes')`, que suman hora de pared y en la hora repetida de octubre dan una hora de más. La hora actual se lee con `CarbonImmutable::now()`, que sigue a `travelTo()`. Los tests que guardan horas a minutos u horas de ahora fijan el reloj lejos de un cambio de hora, y las dos noches de cambio de hora tienen sus propios casos (`TimeSlotTest`, `GuestConfirmationPolicyTest`, `ParticipantTest`, `CampaignInvitationServiceTest`).

## Publicar una versión

1. Sube `version` en `module.json`.
2. Crea y empuja la etiqueta `vX.Y.Z` (debe coincidir con `version`).
3. El workflow `release.yml` ejecuta los tests contra el core (`main`, o la variable `GUILDFORGE_HOST_REF`), compila los assets y publica `game-tables-X.Y.Z.zip` con su `.sha256` en la release.

## Licencia

Este módulo es parte de GuildForge y está bajo la misma licencia del proyecto principal.
