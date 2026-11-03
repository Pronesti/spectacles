---
name: Spectacles
description: A warm, restrained personal concert journal.
colors:
  primary: "#d5f76a"
  paper: "#f7f7f2"
  ink: "#1d211e"
  muted: "#747966"
  line: "#dfe2d9"
  lavender: "#e9e2f7"
typography:
  display:
    fontFamily: "Helvetica Neue, Arial, sans-serif"
    fontSize: "clamp(32px,3.45vw,51px)"
    fontWeight: 750
    lineHeight: 1.1
    letterSpacing: "-2.1px"
  body:
    fontFamily: "Helvetica Neue, Arial, sans-serif"
    fontSize: "14px"
    lineHeight: 1.6
rounded:
  control: "7px"
  panel: "12px"
components:
  button-primary:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.ink}"
    rounded: "{rounded.control}"
    padding: "13px 19px"
    height: "44px"
---

# Design System: Spectacles

## Overview

**Creative North Star: "Personal Concert Journal"**

Warm, restrained, and easy to scan. Preserve the existing identity: a paper-like canvas, dark ink, lime highlights, and softly rounded controls. Concise content and low information density support everyday collection tasks.

This record describes the incumbent implementation in `frontend/src/index.css`, `App.css`, `account.css`, and `catalog.css`. It establishes no redesign. Typography and component values describe their observed roles, not a mandate to shrink other text to match.

## Colors

Lime is the primary action and active-navigation accent. Warm paper forms the base canvas; dark ink carries content. Muted olive-gray supports secondary copy, subtle lines separate regions, and pale lavender offers a secondary surface accent.

**The Accent Rule.** Preserve the palette and use lime selectively for actions and selected states.

## Typography

The interface uses Helvetica Neue with Arial and sans-serif fallbacks. Display headings are bold, tightly tracked, and fluid; supporting copy is calmer with generous line height. Small uppercase eyebrow labels establish hierarchy above page titles. Preserve readable labels and visible keyboard focus.

## Layout

Desktop uses a fixed sidebar (224px) alongside the main content area. Dashboard summaries use a four-column grid; content regions mix lists and paired panels. Existing responsive rules adapt at 1150px, 850px, and 700px, with wide-screen adjustments at 1500px. Preserve those responsive behaviors and avoid crowding content.

## Elevation & Depth

**The Mostly Flat Rule.** Borders and surface colors separate ordinary content. Reserve pronounced shadows for overlays; existing small ambient shadows may remain on incumbent components. Do not turn every panel into a raised card.

## Shapes

Controls are softly rounded, commonly using the control radius. Large cards and panels use the panel radius. Circular avatars and icon controls retain their established silhouettes. Thin borders provide structure without heavy framing.

## Components

### Buttons

Restrained and softly rounded. Primary buttons use lime with dark ink. Standard buttons have a minimum height of 44px, horizontal padding of 19px, and vertical padding of 13px. Hover lifts standard buttons by 1px; keyboard focus remains explicit.

### Cards and containers

Use the existing paper and neutral surfaces, restrained borders, and rounded panel corners. Preserve content-specific layouts instead of forcing every region into a uniform card.

### Inputs and fields

Keep inherited sans-serif typography, dark text, clear labels, and the established border treatment. Focus uses an olive outline; preserve distinct disabled and error states.

### Navigation

Desktop navigation is a vertical sidebar with icon-and-label rows. Active rows use lime and dark ink; unselected rows use muted text and a subtle hover surface. Preserve the existing mobile navigation behavior.

## Do's and Don'ts

- Do preserve the warm, restrained journal identity and confirmed palette.
- Do keep content concise, hierarchy clear, and primary actions easy to find.
- Do retain visible keyboard focus and reduced-motion handling.
- Don't replace the palette, introduce a new font system, or redesign component shapes during a scoped refinement.
- Don't add heavy shadows to ordinary content panels.
