---
id: game-tables.panel.catalog
title: Sistemas de juego, editoriales y avisos de contenido
summary: Mantén las listas que usan los formularios de mesas y campañas, desactiva lo que ya no quieres ofrecer y evita borrar lo que está en uso.
category: activities
audience: panel
order: 100
keywords: [sistema de juego, sistemas, juego, reglamento, D&D, Dungeons and Dragons, Pathfinder, La llamada de Cthulhu, editorial, editoriales, avisos de contenido, aviso, advertencias, trigger warnings, contenido sensible, severidad, catálogo, desactivar, activo, borrar sistema]
related: [game-tables.panel.create-table, game-tables.panel.create-campaign, game-tables.panel.overview]
conditions:
  permissions:
    any: ['gametables:gamesystems.view_any', 'gametables:contentwarnings.view_any']
---
Los formularios de mesas y campañas, en el panel y en la web, sacan sus opciones de tres listas: los sistemas de juego, las editoriales y los avisos de contenido. Esta guía explica cómo mantenerlas.

Para verlas hace falta **Ver sistemas de juego** (también para las editoriales) o **Ver avisos de contenido**. Para crearlas, cambiarlas o borrarlas, **Gestionar sistemas de juego** o **Gestionar avisos de contenido**.

## Sistemas de juego

Están en **Catálogo de juegos** → **Sistemas de juego**. Pulsa **Crear sistema de juego** y rellena:

- **Nombre** y **Slug**: obligatorios. El slug es el nombre en minúsculas y con guiones, por ejemplo `la-llamada-de-cthulhu`, y no se puede repetir.
- **Editorial**: elígela de la lista o créala ahí mismo con el botón **+** del campo.
- **Edición**, **Año**, **Descripción**, **Logo** (una dirección web de la imagen) y **Sitio web**.
- **Título del director de juego**: cómo se llama a quien dirige en ese juego, como «Dungeon Master» o «Guardián». La web lo muestra para el director principal de sus mesas y campañas cuando no tiene un **Título personalizado**; un co-director sin título propio sale como **Co-director**.
- **Activo**: si se ofrece al crear mesas y campañas.

## Editoriales

Están en **Catálogo de juegos** → **Editoriales**. Se crean con **Crear editorial** y tienen **Nombre**, **Slug**, **Descripción**, **País**, **Sitio web**, **Logo** y **Activo**. La lista dice cuántos **Sistemas** tiene cada una. Sirven para ordenar el catálogo de sistemas de juego. Al elegir la editorial de un sistema solo salen las activas, además de la que ese sistema ya tenga.

## Avisos de contenido

Están en **Mesas de rol** → **Avisos de contenido** y se crean con **Crear aviso de contenido**. Son los temas delicados que una partida puede tocar (violencia, terror, arañas…), para que cada persona sepa a qué se apunta.

- **Nombre** y **Slug**: obligatorios. El nombre es lo que se elige en los formularios.
- **Etiqueta**: obligatoria.
- **Descripción**: una explicación más larga.
- **Severidad**: **Leve**, **Moderada** o **Severa**.
- **Icono** y **Activo**.

Si a quien dirige le falta un aviso en la lista, puede escribirlo en **Avisos personalizados** de su mesa o campaña sin pasar por aquí.

## Desactivar en lugar de borrar

Lo que desactivas con **Activo** deja de salir en los formularios nuevos, pero las mesas y campañas que ya lo usan lo conservan. Es la forma segura de retirar algo.

> [!WARNING]
> Un sistema de juego que usan mesas o campañas no se puede borrar: el panel lo explica y te ofrece desactivarlo. Borrar un aviso de contenido lo quita de las mesas y campañas que lo tenían. Borrar una editorial deja sus sistemas sin editorial. Si algo ya no se usa, desactívalo.

## Si algo no sale bien

- El formulario dice que el slug ya está en uso: cada sistema, editorial o aviso necesita un slug distinto. Añade la edición o el año, como `vampiro-v5`.
- Un sistema no sale al crear una mesa: comprueba que está **Activo**.

[[action:filament.admin.resources.game-systems.index|Ver los sistemas de juego|if=gametables:gamesystems.view_any]]
[[action:filament.admin.resources.content-warnings.index|Ver los avisos de contenido|if=gametables:contentwarnings.view_any]]
