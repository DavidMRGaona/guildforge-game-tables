# Mesas de rol

Módulo de GuildForge para gestionar mesas de rol y campañas: inscripciones de usuarios e invitados, lista de espera, avisos de contenido, herramientas de seguridad, correos, creación de mesas y campañas desde la web con moderación y gestión desde el panel.

## Características

- **Mesas**: sesiones de juego de tipo one-shot, aventura, sesión de campaña, demo o tutorial; presenciales, online o híbridas.
- **Campañas**: agrupan sesiones, con estado (reclutando, activa, en pausa, completada, cancelada) y frecuencia.
- **Directores de juego**: director principal y co-directores, propios de la mesa o heredados de la campaña.
- **Inscripciones**: como jugador o espectador, con confirmación automática o manual por mesa.
- **Invitados**: quien no tiene cuenta se inscribe con nombre y email en las mesas abiertas a todos, y cancela con un enlace con token.
- **Lista de espera**: recoge a quien llega sin plaza (la última plaza pedida a la vez por dos personas, o un participante que un administrador añade a una mesa completa); al cancelar un jugador confirmado se promociona al primero de la lista.
- **Tipos de inscripción**: abierta a todos, solo socios (con acceso anticipado opcional) o solo por invitación.
- **Avisos de contenido** con severidad (leve, moderada, severa) y **herramientas de seguridad** (Carta X, Líneas y velos, Puerta abierta, Estrellas y deseos, Flor de apoyo, Script change, Rosas y espinas, otras).
- **Catálogo**: sistemas de juego y editoriales.
- **Creación desde la web**: los usuarios crean mesas y campañas y las envían a revisión; administradores y editores reciben un correo y las aprueban o rechazan desde el panel.
- **Integración con eventos**: configuración de mesas por evento, enlace a las mesas del evento y botón para crear una mesa desde su página.
- **Calendario**: vista `/mesas/calendario` y mesas en el calendario del core.
- **Perfil**: pestañas con las mesas en las que participa el usuario, las que ha creado y sus campañas.
- **Correos** de inscripción, confirmación, rechazo, lista de espera, cancelación y moderación.

## Requisitos

- GuildForge core `^3.2`
- PHP >= 8.5, Laravel >= 13, Filament 5
- Opcional: el módulo de socios (`memberships`) para las mesas «Solo socios»

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

Se configura en el panel: **Mesas de rol** → **Configuración** (permiso `gametables.settings`, que los administradores tienen siempre; el mismo formulario está en **Módulos** → **Configuración**). Los valores iniciales son los que trae el módulo en `config/settings.php`; lo que se guarda en el panel prevalece sobre ellos clave a clave.

| Sección | Qué controla |
|---------|--------------|
| **Integración con socios** | Si los socios pueden inscribirse antes de la apertura según los días de acceso anticipado de cada mesa (desactivada, se inscriben con la apertura general) y los días de acceso anticipado con los que empieza cada mesa nueva del panel |
| **Valores por defecto** | Duración y número de jugadores y espectadores con los que empiezan los formularios de mesa del panel y de la web; confirmación automática y tipo de inscripción de las mesas nuevas del panel y de las creadas desde la web |
| **Notificaciones** | Correos de inscripción y de cancelación a los directores y al email de la mesa, y correos de promoción desde la lista de espera |
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

La publicación es un indicador aparte (`is_published`). **Publicar** deja la mesa en `scheduled` y **Despublicar** la devuelve a `draft`. Las inscripciones se admiten mientras la mesa está publicada y en `scheduled` o `full`, dentro de sus fechas de inscripción; los socios pueden inscribirse antes si la mesa tiene días de acceso anticipado y **Integración con socios** está activada. Además:

- Las mesas «Solo socios» solo admiten socios; los invitados solo pueden inscribirse en las mesas abiertas a todos.
- Sin plazas de jugador libres no se admiten inscripciones nuevas, tampoco de espectador; como espectador hace falta además una plaza de espectador libre.
- Nadie puede tener dos inscripciones activas en la misma mesa (los invitados se distinguen por su email).

El servidor aplica estas reglas igual que la página de la mesa: una inscripción que no las cumple no se crea y se responde con el motivo (error de formulario en la web, 422 a los clientes JSON). Los administradores que añaden participantes desde el panel no están sujetos a ellas. En el panel, la cabecera de la mesa tiene las acciones **Publicar**, **Despublicar**, **Iniciar**, **Completar** y **Cancelar mesa**.

### Inscripción

| Estado | Valor | Descripción |
|--------|-------|-------------|
| Pendiente | `pending` | Pendiente de confirmación |
| Confirmado | `confirmed` | Plaza confirmada |
| Lista de espera | `waiting_list` | Sin plaza, en espera |
| Cancelado | `cancelled` | Cancelada por el participante o al cancelarse la mesa |
| Rechazado | `rejected` | Rechazada por un administrador |
| No presentado | `no_show` | No asistió |

### Moderación (creación desde la web)

| Estado | Valor | Descripción |
|--------|-------|-------------|
| Borrador | `draft` | El autor puede editarla |
| Pendiente de revisión | `pending_review` | Enviada a revisión |
| Aprobada | `approved` | Aprobada y publicada |
| Rechazada | `rejected` | Rechazada con motivo; el autor puede editarla y volver a enviarla |

## Creación desde la web

Con **Creación desde web** activada, los usuarios con sesión iniciada que cumplan el nivel de acceso pueden:

1. Crear una mesa en `/mesas/crear` (o desde el botón de la página de un evento, que la asocia al evento) o una campaña en `/campanas/crear`. Si no cumplen los requisitos, ven una página que explica el motivo.
2. Editar sus borradores desde **Mis mesas** (`/mesas/mis-mesas`) o **Mis campañas** (`/campanas/mis-campanas`).
3. Enviarlos a revisión.

Según el modo de publicación:

- `auto`: la mesa se publica al crearla o al enviarla.
- `approval`: queda pendiente de revisión.
- `role_based`: se publica directamente para los roles o permisos de autopublicación (`frontend_creation.publication.auto_publish_roles` y `auto_publish_permissions`, sin campo en el panel y vacíos por defecto); el resto queda pendiente.

Las mesas y campañas enviadas a revisión se avisan por correo a los usuarios con rol `admin` o `editor` y aparecen en el widget **Pendientes de moderación** del dashboard.

**Mesas**: en **Mesas de rol** → **Mesas** se aprueban (la mesa se publica) o se rechazan con motivo, una a una o en bloque; el autor recibe un correo en ambos casos.

**Campañas**: en **Mesas de rol** → **Campañas** se aprueban (la campaña se publica) o se rechazan con motivo, una a una; el autor ve el motivo del rechazo en **Mis campañas**, sin correo.

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

`{gameTable}` es el identificador de la mesa. El módulo no tiene API: el número de mesas de un evento llega a su página como prop de Inertia (`gameTableCount`). `gametables.index`, `gametables.calendar` y `campaigns.index` aparecen entre las páginas que se pueden enlazar desde el editor de menús.

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

## Correos

| Momento | Destinatarios |
|---------|---------------|
| Inscripción (usuario o invitado) | Participante; directores y email de la mesa si **Notificaciones** lo permite |
| Confirmación o rechazo de la inscripción | Participante |
| Cancelación de la inscripción | Participante; directores y email de la mesa si **Notificaciones** lo permite |
| Promoción desde la lista de espera | Participante y directores, si **Notificaciones** lo permite |
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
| `ParticipantCancelled` | Se cancela una inscripción |
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

La columna de rol indica el rol al que el módulo propone el permiso; habilitar el módulo no lo asigna, se concede en **Roles**. Los administradores tienen acceso completo. Los autores gestionan sus propias mesas y campañas desde «Mis mesas» y «Mis campañas»; una mesa solo se puede editar en borrador o rechazada.

## Comandos

```bash
php artisan gametables:generate-slugs            # genera slugs de mesas y campañas que no lo tienen
php artisan gametables:generate-slugs --dry-run  # muestra qué haría sin cambiar nada
php artisan gametables:generate-slugs --force    # regenera también los existentes
```

## Limitaciones actuales

- Para «Solo socios», un usuario es socio si está vinculado a un socio con estado activo en el módulo `memberships`; sin ese módulo nadie cuenta como socio.
- Las rutas de creación desde la web exigen sesión iniciada, también con el nivel de acceso «Cualquier persona».
- «Solo por invitación» no tiene sistema de invitaciones: rechaza a los invitados sin cuenta, pero los usuarios con sesión iniciada pueden inscribirse como en una mesa abierta.

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
│   ├── Console/Commands/      # gametables:generate-slugs
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
