# Mesas de rol

Módulo de GuildForge para gestionar mesas de rol y campañas: inscripciones de usuarios e invitados, lista de espera, avisos de contenido, herramientas de seguridad, correos, creación de mesas y campañas desde la web con moderación y gestión desde el panel.

## Características

- **Mesas**: sesiones de juego de tipo one-shot, aventura, sesión de campaña, demo o tutorial; presenciales, online o híbridas.
- **Campañas**: agrupan sesiones, con estado (reclutando, activa, en pausa, completada, cancelada) y frecuencia.
- **Directores de juego**: director principal y co-directores, propios de la mesa o heredados de la campaña.
- **Inscripciones**: como jugador o espectador, con confirmación automática o manual por mesa.
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

- GuildForge core `^3.10` (indica a las páginas si la cuenta debe verificar su email; ver [Verificación de email](#verificación-de-email))
- PHP >= 8.5, Laravel >= 13, Filament 5

El core rechaza el módulo si no se cumplen (`module.json` → `requires`).

## Instalación

1. Descarga `game-tables-X.Y.Z.zip` de la última release de `DavidMRGaona/guildforge-game-tables` en GitHub.
2. En el panel, activa el modo mantenimiento (**Configuración del sitio** → pestaña **Mantenimiento**).
3. En **Módulos**, pulsa **Instalar**, sube el ZIP y confirma con **Instalar / actualizar**.
4. Pulsa **Habilitar** en el módulo: la primera vez ejecuta sus migraciones y seeders (editoriales, sistemas de juego y avisos de contenido).
5. Desactiva el modo mantenimiento.

Las versiones nuevas se aplican desde **Actualizaciones de módulos** (también con el modo mantenimiento activo) o con `php artisan module:update game-tables`.

### En desarrollo

Con el repositorio clonado en `src/modules/game-tables`:

```bash
php artisan module:discover
php artisan module:enable game-tables
php artisan module:build game-tables   # compila los componentes Vue (--force para recompilar)
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

La publicación es un indicador aparte (`is_published`). **Publicar** deja la mesa en `scheduled` y **Despublicar** la devuelve a `draft`. Las inscripciones se admiten mientras la mesa está publicada y en `scheduled` o `full` (o en `in_progress` si tiene activado **Permitir inscripciones durante la partida**), dentro de sus fechas de inscripción y nunca desde la hora de inicio: la fecha de cierre solo puede adelantar ese límite, y el panel no deja guardarla posterior al inicio. Con **Permitir inscripciones durante la partida** el límite es la hora de final (inicio + duración) y la ventana la decide la hora, no el estado: entre el inicio y el final se admiten inscripciones aunque nadie haya pulsado **Iniciar**, solo con plaza libre (sin lista de espera). Aparte, los socios pueden inscribirse antes de la fecha de apertura si la mesa tiene días de acceso anticipado y **Acceso anticipado para socios** está activado. Socio es quien tiene el rol de socio del core: el que se elige en **Configuración del sitio** (`socio` por defecto); no depende del módulo de socios. Si ese ajuste no apunta a un rol válido, nadie cuenta como socio: no hay acceso anticipado y las mesas «Solo socios» no admiten a nadie. Además:

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

### Cambio de estado automático

Con **Automatización** → **Actualizar el estado de las mesas automáticamente** activado (por defecto), el comando `game-tables:update-statuses`, que el módulo programa cada cinco minutos sin ejecuciones solapadas, hace lo mismo que **Iniciar** y **Completar**:

- Las mesas **Programada** o **Completa** cuya hora de inicio ha llegado pasan a **En progreso**.
- Las mesas **En progreso** cuyo final (inicio más duración) ha llegado pasan a **Completada** y se emite `GameTableCompleted`. Una mesa programada que ya terminó pasa por los dos estados en la misma ejecución.
- Los borradores y las mesas canceladas o completadas no cambian. Cada mesa se vuelve a leer antes de cambiarla: si alguien la ha cambiado a mano entretanto, se salta; si una falla, se anota en el log y se sigue con las demás. El comando imprime cuántas ha iniciado, completado, saltado y cuántas han fallado.
- No se envían correos. Las horas se comparan en la zona horaria de la aplicación (`APP_TIMEZONE`), la misma en la que se guardan.

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

- **`hold`** (por defecto): la inscripción reserva su plaza hasta un plazo de `guest_confirmation.hold_hours` horas (2 por defecto) desde el envío, y el plazo nunca pasa del cierre de inscripciones de la mesa. Si la mesa tiene lista de espera y estaba llena, el invitado conserva su puesto en ella con el mismo plazo.
- **`wait`**: no ocupa plaza hasta que confirma; el correo avisa de que la plaza no está garantizada. Sin plaza, la inscripción espera hasta que se cierran las inscripciones de la mesa.
- **Tope**: como mucho `guest_confirmation.max_unconfirmed_held` (2 por defecto) invitados sin confirmar retienen plaza a la vez en una mesa; los siguientes esperan sin plaza, como en `wait`.
- **Reenvío**: quien vuelve a enviar el formulario con un email pendiente ve «Ya tienes una inscripción pendiente: revisa tu correo». El correo solo se reenvía si el último envío tiene cinco minutos o más; el plazo no se mueve.

**Cuentas.** Solo cuentan las cuentas con el email verificado: una cuenta sin verificar no impide inscribirse como invitado con ese email. Con **Email con cuenta registrada** en `require_login`, ese email se rechaza con «Este email pertenece a una cuenta. Inicia sesión para inscribirte.» y un enlace para iniciar sesión; con `allow_guest` se admite como invitado salvo que esa cuenta ya esté inscrita («Ya hay una inscripción con tu email.»). Si un usuario con sesión se inscribe en una mesa donde ya tenía una inscripción de invitado con su email verificado, la inscripción pasa a su cuenta (se borran los datos personales de invitado: nombre, apellidos, email y teléfono, y el enlace de cancelación) y, si estaba sin confirmar, su email queda confirmado por la cuenta; no se crea otra.

**Enlace de confirmación.** `GET /mesas/confirmar/{token}` solo muestra los datos de la inscripción; la confirmación se hace con el botón (`POST` a la misma ruta), para que un escáner de correo no confirme por el invitado. Al confirmar, la inscripción sin plaza se coloca bajo el bloqueo de la mesa: si ya no hay plaza o las inscripciones se han cerrado, se avisa y no se cambia nada. Un enlace usado antes dice «Tu inscripción ya estaba confirmada»; uno caducado, cancelado o desconocido, que ya no es válido. Está limitado a 20 peticiones por minuto y 100 por hora y conexión.

**Límite de inscripciones.** El formulario de invitado (solo invitados) admite `guest_rate_limit.per_minute` (5) y `guest_rate_limit.per_hour` (20) envíos por conexión; pasado el límite se muestra un error en el formulario.

**Caducidad.** El comando `game-tables:expire-guest-registrations`, programado cada cinco minutos sin ejecuciones solapadas, pasa a **Caducada** las inscripciones sin confirmar cuyo plazo ha vencido y las que esperaban sin plaza cuando se cierran las inscripciones de su mesa. Solo se envía correo («Inscripción caducada») si la fila retenía plaza; la que esperaba sin plaza caduca en silencio. Al liberarse una plaza se promueve a la lista de espera como en una cancelación. Es independiente de **Actualizar el estado de las mesas automáticamente**: funciona aunque ese ajuste esté desactivado.

**Posible duplicado.** Cada inscripción hecha desde la web guarda una huella de la conexión y el navegador. En el panel, la columna **Avisos** marca **Posible duplicado** en dos inscripciones activas de la misma mesa con la misma huella (al menos una de invitado) hechas con `duplicate_signal.window_hours` horas de diferencia o menos, ventana inclusive. Es solo un aviso; no bloquea nada. La huella se borra cuando termina la mesa. Las columnas de avisos muestran también **Email sin confirmar** y la fecha en que **Caduca**.

**Acciones de la organización.** Las inscripciones que añade la organización desde el panel no pasan por la confirmación del invitado: **Marcar email como confirmado** (como si hubiera pulsado el enlace; si no tenía plaza, la coloca antes bajo el mismo bloqueo y, sin plaza libre, avisa de que la mesa está llena y no cambia nada), **Confirmar** y **Promover** confirman también el email. Confirmar el email de un invitado sin confirmar de cualquiera de estas tres formas no envía a la organización el aviso de inscripción de invitado (es su propia acción) y el invitado recibe un solo correo. Añadir a un invitado desde el panel sigue avisando como siempre.

**Cookie.** El formulario guarda un indicio en el navegador, la cookie `gt_guest_registrations` (categoría preferencias, 90 días), con las mesas en las que se ha inscrito como invitado para avisar si lo intenta otra vez. No se lee ni escribe sin consentimiento. Si el módulo cookie-consent está instalado, el módulo la declara ahí; si no, no hace falta.

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

Una mesa creada o editada desde la web solo se puede vincular a una campaña creada por quien la crea (las que ofrece el formulario); el servidor rechaza cualquier otra con «Solo puedes vincular la mesa a una campaña creada por ti». Desde el panel se puede vincular cualquier campaña. Al editar una mesa desde la web se puede conservar la campaña que ya tenga, aunque la haya vinculado un administrador desde el panel.

Del mismo modo, una mesa creada o editada desde la web solo se puede vincular a un evento que ofrezca el formulario: publicado, sin terminar y con las mesas activadas. El servidor rechaza cualquier otro con «Solo puedes vincular la mesa a un evento abierto a nuevas mesas». El panel ofrece cualquier evento publicado que no haya empezado, tenga o no las mesas activadas. Al editar una mesa desde la web se puede conservar el evento que ya tenga, aunque haya terminado o lo haya vinculado un administrador desde el panel.

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
| Mesas de rol | Campañas | Campañas, sus directores y publicación |
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
| `GET` | `/campanas` | `campaigns.index` | Público |
| `GET` | `/campanas/{slug}` | `campaigns.show` | Público |
| `GET` / `POST` | `/campanas/crear` | `campaigns.frontend-create` / `campaigns.frontend-store` | Usuarios autenticados |
| `GET` | `/campanas/mis-campanas` | `campaigns.my-campaigns` | Usuarios autenticados |
| `GET` | `/campanas/mis-campanas/{id}/editar` | `campaigns.frontend-edit` | Autor |
| `PUT` / `DELETE` | `/campanas/mis-campanas/{id}` | `campaigns.frontend-update` / `campaigns.frontend-destroy` | Autor |
| `POST` | `/campanas/mis-campanas/{id}/enviar-revision` | `campaigns.submit-review` | Autor |

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
| `Campaigns/Index` | `/campanas` |
| `Campaigns/Show` | `/campanas/{slug}` |
| `Campaigns/Create`, `Campaigns/CreateNotEligible` | `/campanas/crear` |
| `Campaigns/Edit` | `/campanas/mis-campanas/{id}/editar` |
| `Campaigns/MyCampaigns` | `/campanas/mis-campanas` |

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
| `profile/*` | Pestañas del perfil: Mesas, Mis mesas y Campañas (slot `profile-sections`) |
| `VerificationBlockedButton` | Acción desactivada con su motivo mientras la cuenta debe verificar su email (ver [Verificación de email](#verificación-de-email)) |

## Correos

| Momento | Destinatarios |
|---------|---------------|
| Inscripción (usuario o invitado) | Participante; directores y email de la mesa si **Notificaciones** lo permite |
| Inscripción de invitado sin confirmar | Invitado, con el enlace para confirmar el email (y el plazo, si reserva plaza); los avisos de inscripción a la organización salen al confirmar el invitado, no al inscribirse |
| Inscripción de invitado caducada | Invitado, solo si la inscripción retenía plaza |
| Confirmación o rechazo de la inscripción | Participante |
| Cancelación de la inscripción por el participante | Participante; directores y email de la mesa si **Notificaciones** lo permite |
| Cancelación de la inscripción por la organización (panel) | Participante, con su propio correo; los directores y el email de la mesa no reciben el aviso de cancelación (sí el de promoción si alguien sale de la lista de espera) |
| Promoción desde la lista de espera | Participante y directores, si **Notificaciones** lo permite; en las mesas con confirmación manual indican que la inscripción queda pendiente |
| Cancelación de la mesa | Participantes |
| Mesa o campaña enviada a revisión | Usuarios con rol `admin` o `editor` |
| Mesa aprobada o rechazada | Autor |

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
| `ParticipantRegistered` | Un usuario se inscribe |
| `GuestRegistered` | Un invitado se inscribe |
| `ParticipantConfirmed` | Se confirma una inscripción |
| `ParticipantRejected` | Se rechaza una inscripción |
| `ParticipantCancelled` | Se cancela una inscripción (`byOrganization` cuando la cancela la organización desde el panel) |
| `ParticipantPromotedFromWaitingList` | Un participante sale de la lista de espera |

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

La columna de rol indica el rol al que el módulo propone el permiso; habilitar el módulo no lo asigna, se concede en **Roles**. Los administradores tienen acceso completo. Los autores gestionan sus propias mesas y campañas desde «Mis mesas» y «Mis campañas»; solo pueden editarlas, enviarlas a revisión o borrarlas mientras no estén publicadas y estén en borrador o rechazadas.

## Comandos

```bash
php artisan gametables:generate-slugs            # genera slugs de mesas y campañas que no lo tienen
php artisan gametables:generate-slugs --dry-run  # muestra qué haría sin cambiar nada
php artisan gametables:generate-slugs --force    # regenera también los existentes
php artisan game-tables:update-statuses          # inicia y completa las mesas según su hora (programado cada cinco minutos)
php artisan game-tables:expire-guest-registrations  # caduca invitados sin confirmar y borra huellas de mesas terminadas (programado cada cinco minutos)
```

## Limitaciones actuales

- Las rutas de creación desde la web exigen sesión iniciada, también con el nivel de acceso «Cualquier persona».
- «Solo por invitación» no tiene sistema de invitaciones: nadie se inscribe desde la web (ni invitados ni usuarios con sesión, que ven «Esta mesa es solo por invitación: la organización inscribe a los jugadores») y la organización añade a los participantes desde el panel.

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
├── package.json
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

## Publicar una versión

1. Sube `version` en `module.json`.
2. Crea y empuja la etiqueta `vX.Y.Z` (debe coincidir con `version`).
3. El workflow `release.yml` ejecuta los tests contra el core (`main`, o la variable `GUILDFORGE_HOST_REF`), compila los assets y publica `game-tables-X.Y.Z.zip` con su `.sha256` en la release.

## Licencia

Este módulo es parte de GuildForge y está bajo la misma licencia del proyecto principal.
