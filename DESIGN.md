# Warden admin design system

Shared by every Warden plugin (OrderWarden, CODWarden, BlockWarden, MailWarden, PulseWarden). Each plugin keeps its own CSS prefix (`orderwarden-`, `codwarden-` ...) but uses these exact tokens so the family looks like one product.

## Who it is for
WooCommerce store owners, mostly non-technical, often worried because something is going wrong with their store. The screen's first job is to answer one question at a glance: **is my store protected right now?** Everything else is secondary.

## Color

| Token | Hex | Use |
|---|---|---|
| ink | `#1d2733` | Headings, numbers |
| text | `#3f4b59` | Body copy |
| muted | `#6b7684` | Help text, labels |
| line | `#dde3ea` | Borders, dividers |
| surface | `#ffffff` | Panels (page canvas stays WordPress grey) |
| pine | `#136f4d` | Brand. Protected state, primary buttons, focus |
| pine-tint | `#e6f2ec` | Protected banner background |
| amber | `#9a5b0b` / tint `#fbf0dc` | Learning, needs attention |
| red | `#b42318` / tint `#fde8e6` | Off, blocked, destructive |

Pine was chosen over the usual SaaS blue or acid green: it reads as "guard, safe, calm", not "alarm".

## Type
System font stack (same as WordPress admin, no external font requests).

| Role | Size / weight |
|---|---|
| Page title | 23px / 600 |
| Status headline | 18px / 600 |
| Section heading | 16px / 600 |
| Body | 14px / 1.6 |
| Help text | 13px / 1.5, muted |
| Figures | 28px / 600, tabular numbers |

Rules: sentence case everywhere, no all-caps labels, no long dashes, no arrows appended to links, no labels above headings.

## Layout
- One **status banner** at the top of each plugin's Overview screen. Its color is the state (pine = protected, amber = learning, red = off). This is the one bold element; everything else stays quiet.
- Numbers sit in **one panel split by dividers**, not a grid of identical cards.
- Settings are **rows inside one panel**: label and help on the left, control on the right. Yes/no options are switches.
- Radius: panels 8px, buttons 6px, pills fully round. One soft border, no drop shadows, no gradients.
- Left aligned. Max text width about 70 characters.

## Voice
Plain, calm, specific. Say what happened and what to do next. Buttons name the action: "Save settings", "Start cleanup", "Stop cleanup".
