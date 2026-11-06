---
id: game-tables.panel.create-campaign
title: Crear una campaña en el panel
summary: Rellena la ficha de una campaña pestaña a pestaña, elige su estado y decide quién puede pedir entrar y cuántos jugadores caben.
category: activities
audience: panel
order: 60
keywords: [crear campaña, nueva campaña, campana, campaña de rol, ficha de campaña, estado de campaña, reclutando, activa, en pausa, completada, cancelada, acepta nuevos jugadores, quién puede solicitar, máximo de jugadores, plazas, número de sesiones, sesión actual, frecuencia, formato, ubicación, horario, directores, máster, master, DM, GM]
related: [game-tables.panel.campaign-roster, game-tables.panel.campaign-sessions, game-tables.panel.moderation, game-tables.panel.overview]
conditions:
  permissions:
    all: ['gametables:campaigns.create']
---
Una campaña reúne su ficha, su dirección y su plantilla de jugadores fijos. Sus partidas son sesiones: mesas que se crean aparte y se vinculan a la campaña. Esta guía recorre el formulario de la campaña.

## Crear la campaña

1. En el menú lateral, abre **Mesas de rol** y elige **Campañas**.
2. Pulsa **Crear campaña**.
3. Recorre las pestañas. Cada una se explica abajo.
4. Pulsa **Crear**.

## Información básica

- **Título** y **Sistema de juego**: obligatorios. Solo salen los sistemas activos.
- **Estado de la campaña**: empieza en **Reclutando**. Mira la tabla de estados más abajo.
- **Idioma**, **Nivel de experiencia** y **Creación de personajes**: ayudan a decidir si la campaña es para cada persona.
- **Descripción**: de qué va la campaña, hasta 2000 caracteres.
- **Imagen**: opcional, hasta 2 MB.

## Dirección

Empieza con una fila para el primer director. Rellénala o quítala, y pulsa **Añadir director de juego** para añadir más. Los campos son los mismos que en una mesa: **Tipo** (**Usuario de la plataforma** o **Persona externa**), **Rol** (**Director principal** o **Co-director**), **Título personalizado**, **Notificar por email**, **Nombre visible públicamente** y **Notas**.

Los directores de la campaña pasan solos a sus sesiones. Quienes tienen cuenta pueden gestionar la plantilla desde la web y, si la creación desde la web está activada, crear sesiones. Quien dirige no puede estar en la plantilla como jugador.

## Progreso

- **Frecuencia**: **Semanal**, **Quincenal**, **Mensual** o **Irregular**.
- **Mínimo de jugadores** y **Máximo de jugadores**: el máximo son las plazas de la plantilla. Vacío, la plantilla no tiene límite. Ninguno puede pasar del **Límite máximo de jugadores** de la configuración, el mismo que tienen las mesas.
- **Quién puede solicitar**: **Cualquier cuenta** o **Solo el rol {{association_role}}** (solo quienes tienen el rol {{association_role}}). Decide quién puede pedir entrar en la plantilla desde la web y a quién se puede invitar.
- **Número de sesiones**: las previstas. Vacío si no está definido.
- **Sesión actual**: por cuál vais. Usa 0 si aún no ha empezado. No avanza sola: cámbiala tú.

## Horario y ubicación

- **Formato**: **Presencial**, **Online** o **Híbrido**.
- **Notas de horario**: por ejemplo «Viernes alternos, de 18:00 a 22:00».
- **Ubicación** y **URL online**: salen según el formato.

Al crear una sesión de la campaña, el formulario de la mesa propone este formato, esta ubicación y este enlace.

## Contenido y seguridad

**Géneros**, **Tono**, **Herramientas de seguridad**, **Avisos de contenido** y **Avisos personalizados**, como en una mesa. Son informativos: salen en la ficha de la campaña.

## Publicación

- **Acepta nuevos jugadores**: indica que la campaña busca gente. Activado, se puede pedir entrar desde la web y las plazas libres de sus sesiones quedan abiertas a cualquiera. Desactivado, nadie puede pedir entrar ni aceptar una invitación, no se pueden enviar invitaciones y sus sesiones son solo para la plantilla.
- **Publicado**: decide si la campaña se ve en la web.
- **Encuesta al terminar**: si tu web tiene encuestas, elige aquí qué encuesta sale cuando la campaña termine. El campo tiene su propia ayuda.

> [!IMPORTANT]
> La plantilla, las solicitudes y las invitaciones solo funcionan con la campaña publicada (o aprobada, si llegó de la web). Mientras no lo está, cualquier cambio en la plantilla responde «Esta campaña no está publicada.».

## Estados de la campaña

El estado se elige a mano en **Estado de la campaña** y puedes pasar de cualquiera a cualquiera.

| Estado | Para qué sirve | Qué cambia |
|---|---|---|
| **Reclutando** | Busca jugadores | Admite solicitudes si **Acepta nuevos jugadores** está activado |
| **Activa** | Se está jugando | Igual que **Reclutando** |
| **En pausa** | Parada por un tiempo | No admite solicitudes y sus sesiones son solo para la plantilla |
| **Completada** | Terminó | La plantilla ya no cambia y no se crean sesiones nuevas desde la web |
| **Cancelada** | No seguirá | Igual que **Completada** |

Si tu web tiene encuestas, pasar a **Completada** puede enviar la encuesta al terminar de la campaña.

## Cambiarla después

Abre la campaña, cambia lo que necesites y pulsa **Guardar cambios**. Abajo de la ficha están las pestañas **Plantilla**, **Solicitudes** e **Invitaciones** (mira la guía de la plantilla).

> [!WARNING]
> Al borrar una campaña con **Borrar**, sus sesiones no se borran: se quedan como mesas sueltas, sin campaña. Si solo quieres cerrarla, ponla en **Completada** o **Cancelada**.

## Si algo no sale bien

- **«El máximo de jugadores no puede ser menor que la plantilla actual (…)»**: ya hay más jugadores fijos que ese máximo. Sube el máximo o quita a alguien de la plantilla antes.
- **«El mínimo de jugadores no puede ser mayor que el máximo»**: baja el mínimo o sube el máximo.
- No encuentras el sistema de juego: puede estar desactivado en **Sistemas de juego**.

[[action:filament.admin.resources.campaigns.create|Crear una campaña]]
