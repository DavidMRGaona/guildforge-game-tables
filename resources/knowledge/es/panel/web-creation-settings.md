---
id: game-tables.panel.web-creation-settings
title: Ajustes de creación desde la web
summary: Decide si la gente puede crear mesas y campañas desde la web, quién puede hacerlo y si lo que crea se publica al momento o espera revisión.
category: activities
audience: panel
order: 120
keywords: [creación desde web, crear desde la web, crear mesas en la web, crear campañas en la web, quién puede crear, nivel de acceso, roles permitidos, permiso requerido, modo de publicación, publicación automática, requiere aprobación, según el rol, publicar sin revisión, moderación, revisión, autopublicar, contenido permitido]
related: [game-tables.panel.moderation, game-tables.panel.event-tables, game-tables.panel.settings, core.panel.roles-and-permissions]
conditions:
  permissions:
    all: ['gametables:gametables.settings']
---
La web puede dejar que la gente cree sus propias mesas y campañas, sin entrar en el panel. Esta parte de la configuración decide si se puede, quién puede y qué pasa con lo que crea.

## Abrir los ajustes

1. En el menú lateral, abre **Sistema**, luego **Configuración** y elige **Mesas**.
2. Baja hasta la sección **Creación desde web** y despliégala si está plegada.
3. Al terminar, pulsa **Guardar**.

## Quién puede crear

1. Activa **Habilitar creación desde web**. Desactivado, nadie crea nada desde la web, salvo en los eventos que tienen su propia regla.
2. En **Contenido permitido**, elige **Solo mesas**, **Solo campañas** o **Mesas y campañas**.
3. En **Nivel de acceso**, elige quién:
   - **Usuarios registrados**: cualquiera con cuenta. Crear siempre exige iniciar sesión.
   - **Usuarios con rol específico**: elige en **Roles permitidos** uno o varios roles de la lista.
   - **Usuarios con permiso específico**: elige en **Permiso requerido** el permiso de la lista, con el mismo nombre que ves en **Roles**.

> [!TIP]
> Para que solo quienes tienen el rol {{association_role}} creen mesas, elige **Usuarios con rol específico** y, en **Roles permitidos**, ese rol.

Hay una excepción: quien dirige una campaña aprobada o publicada, que no haya terminado, puede crear sesiones de esa campaña aunque no cumpla el nivel de acceso. Solo hace falta que **Habilitar creación desde web** esté activado.

## Qué pasa con lo que se crea

**Modo de publicación** decide si lo que se crea en la web necesita revisión:

| Modo | Qué pasa |
|---|---|
| **Publicación automática** | Lo que se crea se publica al momento, sin revisión. |
| **Requiere aprobación** | Se guarda como borrador. Su autor lo envía a revisión cuando está listo, y alguien con permiso lo aprueba o lo rechaza en el panel. |
| **Según el rol del usuario** | Quien tiene alguno de los **Roles que publican sin revisión** o de los **Permisos que publican sin revisión** publica al momento. El resto pasa por revisión. |

Con **Según el rol del usuario** hay que elegir al menos un rol o un permiso. Esas listas solo cuentan en ese modo: si cambias a otro, se guardan pero no hacen nada.

Una sesión de campaña que se publicaría al momento se rechaza si tiene menos plazas que la plantilla de su campaña: su autor tiene que subir el máximo.

> [!IMPORTANT]
> Cambiar el modo no cambia lo que ya está en revisión: eso hay que aprobarlo o rechazarlo igualmente.

## Lo que la web no elige

Quien crea una mesa en la web no decide cómo se apunta la gente. Su mesa toma estos valores de la configuración:

- El **Tipo de inscripción por defecto**.
- **Confirmar inscripciones automáticamente**.
- Los **Días de acceso anticipado**.
- Sin apertura de inscripciones, y con cierre al inicio de la mesa, o al inicio del evento si la mesa es de un evento.
- **El director gestiona las inscripciones**, desactivado.

Quien tiene el permiso **Editar mesa** puede cambiar cualquiera de estos campos en el panel, mesa a mesa.

## Reglas por evento

Cada evento puede tener sus propias reglas para las mesas que se crean en él: si admite mesas, sus turnos, su ubicación, desde cuándo se puede crear, quién puede crear y quién se adelanta. Se configuran en la pestaña **Configuración de mesas** del evento (mira la guía de mesas en un evento). El modo de publicación es siempre el de aquí.

## Si algo no sale bien

- **«Elige al menos un rol o un permiso que publique sin revisión.»**: con **Según el rol del usuario**, rellena **Roles que publican sin revisión** o **Permisos que publican sin revisión**.
- Alguien con el rol correcto no ve **Crear mesa** en la web: comprueba que el nombre del rol en **Roles permitidos** está bien escrito y que **Contenido permitido** incluye lo que quiere crear.

[[action:filament.admin.configuracion.pages.game-tables-settings|Abrir la configuración de mesas]]
