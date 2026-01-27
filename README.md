# Mesas de rol

Sistema de gestión de mesas de juegos de rol y campañas con inscripciones, lista de espera, avisos de contenido, herramientas de seguridad, notificaciones por email y gestión desde el panel de administración.

## Características

- **Mesas de rol**: Creación y gestión de sesiones de juego (one-shot, aventura, sesión de campaña, demo, tutorial)
- **Campañas**: Agrupación de sesiones en campañas con estado y frecuencia
- **Inscripciones**: Los usuarios pueden inscribirse como jugador o espectador
- **Inscripción de invitados**: Los no registrados pueden inscribirse con nombre y email
- **Lista de espera**: Gestión automática con promoción cuando se liberan plazas
- **Formatos de mesa**: Presencial, online o híbrido
- **Avisos de contenido**: Sistema de advertencias con severidad (leve, moderado, grave)
- **Herramientas de seguridad**: X-Card, Lines & Veils, Open Door, entre otras
- **Catálogo de sistemas de juego**: Gestión de sistemas de rol con editorial asociada
- **Calendario**: Vista de calendario de las mesas programadas
- **Notificaciones por email**: Confirmación, lista de espera, promoción, cancelación
- **Panel de administración**: Gestión completa desde Filament

## Requisitos

- PHP >= 8.2
- GuildForge core

## Instalación

1. Copiar el módulo a `src/modules/game-tables/`

2. Descubrir y habilitar el módulo:

```bash
php artisan module:discover
php artisan module:enable game-tables
```

3. Ejecutar las migraciones:

```bash
php artisan migrate
```

## Configuración

### Configuración global

Valores por defecto en `config/game-tables.php`:

```php
return [
    'creators' => env('GAMETABLES_CREATORS', 'admin'),
    // Valores: 'admin', 'members', 'permission'

    'membership_integration' => [
        'enabled' => env('GAMETABLES_MEMBERSHIP_INTEGRATION', true),
        'fallback_role' => 'member',
    ],

    'defaults' => [
        'duration_minutes' => 240,
        'min_players' => 3,
        'max_players' => 5,
        'max_spectators' => 0,
        'language' => 'es',
        'registration_type' => 'everyone',
        'members_early_access_days' => 0,
        'auto_confirm' => true,
    ],

    'limits' => [
        'max_players_limit' => 20,
        'max_spectators_limit' => 50,
        'max_duration_minutes' => 720,
        'max_early_access_days' => 30,
    ],

    'notifications' => [
        'notify_on_registration' => true,
        'notify_on_cancellation' => true,
        'notify_waiting_list_promotion' => true,
        'reminder_hours_before' => [24, 2],
    ],

    'feedback' => [
        'enabled' => true,
        'allow_anonymous' => false,
        'days_to_submit' => 7,
    ],
];
```

## Estados de mesa

| Estado | Valor | Descripción |
|--------|-------|-------------|
| Borrador | `draft` | Mesa creada pero no visible |
| Publicada | `published` | Visible pero sin inscripciones abiertas |
| Abierta | `open` | Inscripciones abiertas |
| Completa | `full` | Todas las plazas ocupadas |
| En curso | `in_progress` | Sesión en desarrollo |
| Completada | `completed` | Sesión finalizada |
| Cancelada | `cancelled` | Mesa cancelada |

## Estados de inscripción

| Estado | Valor | Descripción |
|--------|-------|-------------|
| Pendiente | `pending` | Pendiente de confirmación |
| Confirmada | `confirmed` | Inscripción confirmada |
| Lista de espera | `waiting_list` | En lista de espera |
| Cancelada | `cancelled` | Cancelada por el usuario |
| Rechazada | `rejected` | Rechazada por administrador |
| No presentado | `no_show` | El participante no asistió |

## Arquitectura

```
src/modules/game-tables/
├── config/
│   ├── game-tables.php        # Configuración del módulo
│   └── module.php             # Activación del módulo
├── database/
│   ├── migrations/            # Migraciones de base de datos
│   └── seeders/               # Seeders (sistemas, editoriales, avisos)
├── lang/
│   ├── en/                    # Traducciones en inglés
│   │   ├── emails.php
│   │   └── messages.php
│   └── es/                    # Traducciones en español
│       ├── emails.php
│       └── messages.php
├── resources/js/
│   ├── components/            # Componentes Vue
│   ├── locales/               # Traducciones i18n (frontend)
│   ├── pages/
│   │   ├── GameTables/        # Páginas de mesas
│   │   └── Campaigns/         # Páginas de campañas
│   └── types/                 # Tipos TypeScript
├── routes/
│   ├── api.php                # Rutas API
│   └── web.php                # Rutas web
├── src/
│   ├── Application/
│   │   ├── DTOs/              # Data Transfer Objects
│   │   └── Services/          # Interfaces de servicios
│   ├── Console/
│   │   └── Commands/          # Comandos Artisan
│   ├── Domain/
│   │   ├── Entities/          # Entidades de dominio
│   │   ├── Enums/             # Enumeraciones
│   │   ├── Events/            # Eventos de dominio
│   │   ├── Exceptions/        # Excepciones de dominio
│   │   ├── Repositories/      # Interfaces de repositorios
│   │   └── ValueObjects/      # Objetos de valor
│   ├── Filament/
│   │   ├── Pages/             # Páginas de configuración
│   │   ├── Resources/         # Recursos CRUD
│   │   └── Widgets/           # Widgets del dashboard
│   ├── Http/
│   │   ├── Controllers/       # Controladores
│   │   ├── Requests/          # Form Requests
│   │   └── Resources/         # HTTP Resources (JSON)
│   ├── Infrastructure/
│   │   ├── Listeners/         # Listeners de eventos
│   │   ├── Persistence/       # Repositorios Eloquent
│   │   └── Services/          # Implementaciones de servicios
│   ├── Listeners/             # Listeners adicionales
│   ├── Notifications/         # Notificaciones por email
│   └── Policies/              # Políticas de autorización
├── tests/
│   ├── Integration/           # Tests de integración
│   └── Unit/                  # Tests unitarios
├── module.json                # Manifiesto del módulo
└── phpunit.xml                # Configuración de tests
```

## Rutas web

| Método | Ruta | Descripción |
|--------|------|-------------|
| `GET` | `/mesas` | Listado de mesas |
| `GET` | `/mesas/calendario` | Vista de calendario |
| `GET` | `/mesas/{id}` | Detalle de una mesa |
| `POST` | `/mesas/{id}/inscripcion` | Inscribirse a una mesa (auth) |
| `DELETE` | `/mesas/{id}/inscripcion` | Cancelar inscripción (auth) |
| `POST` | `/mesas/{id}/inscripcion-invitado` | Inscripción de invitado |
| `GET` | `/mesas/cancelar/{token}` | Confirmación de cancelación por token |
| `DELETE` | `/mesas/cancelar/{token}` | Cancelar inscripción por token |
| `GET` | `/campanas` | Listado de campañas |
| `GET` | `/campanas/{id}` | Detalle de una campaña |

## API

| Método | Ruta | Descripción |
|--------|------|-------------|
| `GET` | `/api/mesas/count` | Obtener número de mesas |

## Componentes Vue

### Componentes

| Componente | Descripción |
|------------|-------------|
| `GameTableCard` | Tarjeta resumen de una mesa con fecha, capacidad y director de juego |
| `GameTableCardSkeleton` | Esqueleto de carga para `GameTableCard` |
| `GameTableFilters` | Controles de filtrado para el listado de mesas |
| `RegistrationButton` | Botón de inscripción con gestión de estado y selección de rol |
| `RegistrationStatus` | Muestra el estado actual de la inscripción del usuario |
| `GuestRegistrationModal` | Modal de inscripción para invitados |
| `StatusBadge` | Insignia de estado de la mesa con colores e iconos |
| `FormatBadge` | Insignia del formato de la mesa (presencial, online, híbrido) |
| `ContentWarningBadge` | Insignia de aviso de contenido |
| `SafetyToolBadge` | Insignia de herramienta de seguridad |
| `EventTablesLink` | Enlace a las mesas de un evento |

### Páginas

| Página | Ruta | Descripción |
|--------|------|-------------|
| `GameTables/Index` | `/mesas` | Listado de mesas con filtros y búsqueda |
| `GameTables/Show` | `/mesas/{id}` | Detalle de una mesa |
| `GameTables/Calendar` | `/mesas/calendario` | Calendario de mesas |
| `GameTables/CancelRegistration` | `/mesas/cancelar/{token}` | Confirmación de cancelación |
| `Campaigns/Index` | `/campanas` | Listado de campañas |
| `Campaigns/Show` | `/campanas/{id}` | Detalle de una campaña |

## Eventos de dominio

| Evento | Descripción |
|--------|-------------|
| `GameTableCreated` | Se creó una nueva mesa |
| `GameTablePublished` | Una mesa fue publicada |
| `GameTableCancelled` | Una mesa fue cancelada |
| `GameTableCompleted` | Una mesa fue completada |
| `RegistrationOpened` | Se abrieron las inscripciones de una mesa |
| `ParticipantRegistered` | Un usuario se inscribió a una mesa |
| `ParticipantConfirmed` | Una inscripción fue confirmada |
| `ParticipantCancelled` | Un participante canceló su inscripción |
| `ParticipantPromotedFromWaitingList` | Un participante fue promocionado de la lista de espera |
| `GuestRegistered` | Un invitado se inscribió a una mesa |
| `CampaignCreated` | Se creó una nueva campaña |
| `CampaignStatusChanged` | El estado de una campaña cambió |

## Permisos

| Permiso | Descripción |
|---------|-------------|
| `gametables.view_any` | Ver listado de mesas |
| `gametables.view` | Ver detalle de mesa |
| `gametables.create` | Crear mesas |
| `gametables.update` | Editar mesas |
| `gametables.delete` | Eliminar mesas |
| `campaigns.view_any` | Ver listado de campañas |
| `campaigns.view` | Ver detalle de campaña |
| `campaigns.create` | Crear campañas |
| `campaigns.update` | Editar campañas |
| `campaigns.delete` | Eliminar campañas |
| `gamesystems.view_any` | Ver sistemas de juego |
| `gamesystems.manage` | Gestionar sistemas de juego |
| `contentwarnings.view_any` | Ver avisos de contenido |
| `contentwarnings.manage` | Gestionar avisos de contenido |
| `gametables.settings` | Gestionar configuración del módulo |

## Tests

Ejecutar los tests del módulo:

```bash
# Desde el directorio del módulo
cd src/modules/game-tables
../../../vendor/bin/phpunit

# O desde el directorio raíz
php artisan test --filter=GameTable
```

## Licencia

Este módulo es parte de GuildForge y está bajo la misma licencia del proyecto principal.
