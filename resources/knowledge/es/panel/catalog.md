---
id: game-tables.panel.catalog
title: Sistemas de juego, editoriales, avisos de contenido y géneros
summary: Mantén las listas que usan los formularios de mesas y campañas, desactiva lo que ya no quieres ofrecer y evita borrar lo que está en uso.
category: activities
audience: panel
order: 100
keywords: [sistema de juego, sistemas, juego, reglamento, D&D, Dungeons and Dragons, Pathfinder, La llamada de Cthulhu, editorial, editoriales, avisos de contenido, aviso, advertencias, trigger warnings, contenido sensible, severidad, géneros, género, fantasía, terror, ciencia ficción, color, catálogo, desactivar, activo, borrar sistema, borrar género]
related: [game-tables.panel.create-table, game-tables.panel.create-campaign, game-tables.panel.overview]
conditions:
  permissions:
    any: ['gametables:gamesystems.view_any', 'gametables:contentwarnings.view_any', 'gametables:genres.view_any']
---
Los formularios de mesas y campañas, en el panel y en la web, sacan sus opciones de cuatro listas: los sistemas de juego, las editoriales, los avisos de contenido y los géneros. Esta guía explica cómo mantenerlas.

Para verlas hace falta **Ver sistemas de juego** (también para las editoriales), **Ver avisos de contenido** o **Ver géneros**. Para crearlas, cambiarlas o borrarlas, **Gestionar sistemas de juego**, **Gestionar avisos de contenido** o **Gestionar géneros**.

## Sistemas de juego

Están en **Catálogos** → **Sistemas de juego**. Pulsa **Crear sistema de juego** y rellena:

- **Nombre** y **Slug**: obligatorios. El slug es el nombre en minúsculas y con guiones, por ejemplo `la-llamada-de-cthulhu`, y no se puede repetir.
- **Editorial**: elígela de la lista o créala ahí mismo con el botón **+** del campo.
- **Edición**, **Año**, **Descripción**, **Logo** (una dirección web de la imagen) y **Sitio web**.
- **Título del director de juego**: cómo se llama a quien dirige en ese juego, como «Dungeon Master» o «Guardián». La web lo muestra para el director principal de sus mesas y campañas cuando no tiene un **Título personalizado**; un co-director sin título propio sale como **Co-director**.
- **Activo**: si se ofrece al crear mesas y campañas.

## Editoriales

Están en **Catálogos** → **Editoriales**. Se crean con **Crear editorial** y tienen **Nombre**, **Slug**, **Descripción**, **País**, **Sitio web**, **Logo** y **Activo**. La lista dice cuántos **Sistemas** tiene cada una. Sirven para ordenar el catálogo de sistemas de juego. Al elegir la editorial de un sistema solo salen las activas, además de la que ese sistema ya tenga.

## Avisos de contenido

Están en **Catálogos** → **Avisos de contenido** y se crean con **Crear aviso de contenido**. Son los temas delicados que una partida puede tocar (violencia, terror, arañas…), para que cada persona sepa a qué se apunta.

- **Nombre** y **Slug**: obligatorios. El nombre es lo que se elige en los formularios.
- **Etiqueta**: obligatoria.
- **Descripción**: una explicación más larga.
- **Severidad**: **Leve**, **Moderada** o **Severa**.
- **Icono** y **Activo**.

Si a quien dirige le falta un aviso en la lista, puede escribirlo en **Avisos personalizados** de su mesa o campaña sin pasar por aquí.

## Géneros

Están en **Catálogos** → **Géneros**: Fantasía, Terror, Ciencia ficción… Cada mesa y campaña marca los que encajan. Se crean con **Crear género**:

- **Nombre**: obligatorio y distinto del de los demás géneros, aunque cambien las mayúsculas.
- **Clave**: se propone sola a partir del nombre, en minúsculas, sin acentos y con guiones bajos (`ciencia_ficcion`). Las mesas y campañas guardan la clave, así que **no se puede cambiar después**. Si te equivocas, cambia el nombre: la clave no se ve en la web.
- **Color**: el de la etiqueta del género. Se elige entre los colores del tema, para que se lea bien con fondo claro y oscuro.
- **Activo**: si se ofrece en los formularios.

La lista dice cuántas mesas y campañas tienen cada género. Para cambiar el orden en que salen en los formularios, pulsa el botón de reordenar de la lista y arrastra las filas. Al cambiar el nombre de un género, las mesas que lo tienen lo muestran con el nombre nuevo, también en el buscador de la web.

## Desactivar en lugar de borrar

Lo que desactivas con **Activo** deja de salir en los formularios nuevos, pero las mesas y campañas que ya lo usan lo conservan. Es la forma segura de retirar algo.

> [!WARNING]
> Un sistema de juego que usan mesas o campañas no se puede borrar: el panel lo explica y te ofrece desactivarlo. Con los géneros pasa lo mismo, y borrar uno que no tiene nadie pide marcar una casilla de confirmación, porque no se puede deshacer. Borrar un aviso de contenido lo quita de las mesas y campañas que lo tenían. Borrar una editorial deja sus sistemas sin editorial. Si algo ya no se usa, desactívalo.

## Si algo no sale bien

- El formulario dice que el slug ya está en uso: cada sistema, editorial o aviso necesita un slug distinto. Añade la edición o el año, como `vampiro-v5`.
- Un sistema o un género no sale al crear una mesa: comprueba que está **Activo**. Una mesa que ya tenía un género desactivado lo conserva al editarla.
- El formulario dice que la clave no es válida: usa solo minúsculas sin acentos, números y guiones bajos, y empieza por una letra.

[[action:filament.admin.resources.game-systems.index|Ver los sistemas de juego|if=gametables:gamesystems.view_any]]
[[action:filament.admin.resources.content-warnings.index|Ver los avisos de contenido|if=gametables:contentwarnings.view_any]]
[[action:filament.admin.resources.generos.index|Ver los géneros|if=gametables:genres.view_any]]
