# PetConnect — "Aubergine & Lavender" Style Guide

Agent handoff for restyling the PetConnect codebase (Laravel 12 · Inertia · Vue 3 · Tailwind v4 · shadcn-vue) and the companion mobile app.

**Scope: visual only.** Keep every layout, component tree, route, copy string, interaction and accessibility contract exactly as it is. Change colours, type, radii, shadows, borders and button treatments. Nothing moves.

**Reference file:** `PetConnect Web Styles.dc.html` → option **2b** (light + dark). It is an HTML design reference, not production code — recreate it inside the existing codebase with its existing patterns (CSS tokens in `resources/css/app.css`, shadcn `Button` variants, Tailwind utilities). Fidelity: **hi-fi** — values below are final.

---

## 1. Design intent

Refined evolution of the current violet brand. Lavender-tinted surfaces instead of pure white/grey, cards that float on a diffuse shadow instead of a hard border, one deeper violet as the only brand colour, and a serif for headings. Dark mode re-tones (lighter violet on deep aubergine), it does not invert.

Three rules that decide everything else:

1. **One accent.** Violet is the only chromatic colour. The violet→fuchsia gradient, teal/emerald gradients and the emerald "own listing" frame are retired.
2. **One solid button per surface.** The primary action is solid violet; every secondary action is a tinted lavender wash. Never two solid violet buttons side by side.
3. **Shadows over borders.** Cards and popovers lift with a soft shadow; borders stay very low-contrast and exist only as hairlines.

---

## 2. Colour tokens

Replace the `:root` and `.dark` blocks in `resources/css/app.css`. Keep the `@theme inline` registrations and every token *name* — only values change. All text pairs meet WCAG AA (≥ 4.5:1) on their stated surface.

### 2.1 Brand ramp (absolute, same in both schemes)

| Token | Hex | Notes |
|---|---|---|
| `--primary-50` | `#F6F4FB` | light page background |
| `--primary-100` | `#EEEAFB` | light accent wash |
| `--primary-200` | `#DED6F7` | |
| `--primary-300` | `#CDBDFF` | dark accent foreground |
| `--primary-400` | `#A78BFF` | **dark primary** |
| `--primary-500` | `#7C5CF2` | hover of dark primary |
| `--primary-600` | `#5A3FE0` | **light primary** |
| `--primary-700` | `#4A32C4` | light accent foreground, hover of light primary |
| `--primary-800` | `#3A2799` | |
| `--primary-900` | `#261D45` | dark accent wash |

### 2.2 Light scheme (`:root`)

| Token | Value | Hex | Use |
|---|---|---|---|
| `--background` | `hsl(252 47% 97%)` | `#F6F4FB` | page |
| `--foreground` | `hsl(265 29% 12%)` | `#1C1526` | body text (15.6:1) |
| `--card` / `--popover` | `hsl(0 0% 100%)` | `#FFFFFF` | cards, sheets, menus |
| `--card-foreground` / `--popover-foreground` | | `#1C1526` | |
| `--primary` | `var(--primary-600)` | `#5A3FE0` | solid buttons, active states (white on it 6.2:1) |
| `--primary-foreground` | | `#FFFFFF` | |
| `--secondary` | `hsl(252 60% 95%)` | `#EEEAFB` | secondary/"View details" buttons |
| `--secondary-foreground` | `var(--primary-700)` | `#4A32C4` | 7.4:1 on `--secondary` |
| `--muted` | `hsl(255 40% 96%)` | `#F1EEF8` | skeletons, subtle fills |
| `--muted-foreground` | `hsl(262 12% 48%)` | `#756B88` | metadata (4.9:1 on white) |
| `--accent` | `hsl(252 60% 95%)` | `#EEEAFB` | hover / open menu item |
| `--accent-foreground` | `var(--primary-700)` | `#4A32C4` | |
| `--destructive` | `hsl(0 72% 51%)` | `#DC2828` | unchanged |
| `--border` | `hsl(258 35% 93%)` | `#ECE8F4` | hairlines |
| `--input` | `hsl(0 0% 100%)` | `#FFFFFF` | inputs sit white on the tinted page |
| `--ring` | `var(--primary-600)` | `#5A3FE0` | focus ring (now brand; used at full opacity, see §6) |
| `--radius` | `0.875rem` | 14px | |
| `--sidebar-*` | mirror the matching tokens above | | |

Extra text step used by cards (add as a utility or use `text-foreground/80`): **soft text** `#4A4059` for descriptions (8.3:1 on white).

### 2.3 Dark scheme (`.dark`)

| Token | Value | Hex | Use |
|---|---|---|---|
| `--background` | `hsl(255 33% 7%)` | `#0F0C18` | page |
| `--foreground` | `hsl(260 38% 96%)` | `#F2EEFA` | 17.3:1 |
| `--card` / `--popover` | `hsl(254 37% 12%)` | `#181329` | |
| `--primary` | `var(--primary-400)` | `#A78BFF` | 9.4:1 vs background |
| `--primary-foreground` | `hsl(255 45% 12%)` | `#15102A` | 9.6:1 on primary |
| `--secondary` | `var(--primary-900)` | `#261D45` | tinted button |
| `--secondary-foreground` | `var(--primary-300)` | `#CDBDFF` | 9.2:1 on secondary |
| `--muted` | `hsl(255 30% 16%)` | `#221B38` | |
| `--muted-foreground` | `hsl(258 18% 64%)` | `#9C92B4` | 6.9:1 on background |
| `--accent` | `var(--primary-900)` | `#261D45` | |
| `--accent-foreground` | `var(--primary-300)` | `#CDBDFF` | |
| `--destructive` | `hsl(0 72% 51%)` | `#DC2828` | unchanged |
| `--border` | `hsl(258 32% 18%)` | `#271F3C` | |
| `--input` | `hsl(254 37% 12%)` | `#181329` | |
| `--ring` | `var(--primary-400)` | `#A78BFF` | |

Soft text (dark): `#CBC3DC`.

### 2.4 `app.blade.php`

Update the inline pre-paint background so there is no flash:

```css
html { background-color: #F6F4FB; }
html.dark { background-color: #0F0C18; }
```

---

## 3. Typography

### 3.1 Families

| Role | Family | Weights | Fallback |
|---|---|---|---|
| Headings (h1–h3, brand wordmark, pet names) | **Newsreader** (variable, `opsz` 6–72) | 500, 600 | `Georgia, serif` |
| UI + body | **Manrope** | 400, 500, 600, 700 | `ui-sans-serif, system-ui` |
| Arabic (all roles) | **Readex Pro** | 400, 500, 600, 700 | `system-ui` |

Load via Laravel's `@fonts` directive / Google Fonts:
`https://fonts.googleapis.com/css2?family=Newsreader:opsz,wght@6..72,500;6..72,600&family=Manrope:wght@400;500;600;700&family=Readex+Pro:wght@400;500;600;700&display=swap`

In `app.css` `@theme inline`:
```css
--font-sans: 'Manrope', 'Readex Pro', ui-sans-serif, system-ui, sans-serif;
--font-serif: 'Newsreader', Georgia, serif;
```
Set `html[dir="rtl"] { --font-sans: 'Readex Pro', ...; --font-serif: 'Readex Pro', ...; }` — Arabic has no serif pairing; headings in RTL use Readex Pro 600.

### 3.2 Scale (web)

| Element | Font | Size / line | Weight | Tracking |
|---|---|---|---|---|
| Page title (`Home` h2 "Discover Pets", `pets/Show` name) | Newsreader | 40 / 1.05 | 500 | −0.015em |
| Section heading (`PetSectionHeading`, settings panel titles) | Newsreader | 26 / 1.15 | 500 | −0.01em |
| Card title (pet name on `PetCardHeader`) | Newsreader | 21 / 1.2 | 500 | −0.015em |
| Brand wordmark (`BrandMark`) | Newsreader | 21 | 500 | −0.015em |
| Body | Manrope | 15 / 1.55 | 400 | 0 |
| UI label / button | Manrope | 14 | 600 | 0 |
| Metadata (breed · age, timestamps) | Manrope | 13 | 400 | 0 |
| Pills / badges | Manrope | 11 | 600 | 0 |
| Subtitle under page title ("128 listings near Cairo") | Manrope | 14 | 400, `--muted-foreground` | |

Headings keep `text-wrap: balance`; paragraphs `text-wrap: pretty`.

---

## 4. Shape, elevation, borders

| Token | Value |
|---|---|
| `--radius` (buttons, inputs, chips, menus) | **14px** (`rounded-[14px]`; shadcn `rounded-md` → 12px, `rounded-sm` → 10px) |
| Card radius | **20px** (`rounded-[20px]`) |
| Avatar | 12px square-rounded (`rounded-xl`) — not circular |
| Brand mark | 10px |
| Pills (status, type, gender, counts) | `rounded-full` |
| Card shadow (light) | `0 1px 2px rgba(28,21,38,.04), 0 14px 36px -14px rgba(28,21,38,.14)` |
| Card shadow (dark) | `0 14px 36px -14px rgba(0,0,0,.6)` |
| Card hover | same shadow, `translate-y-[-2px]`, border → `--primary-200` (light) / `--primary-800` (dark). Remove the current violet glow and the emerald own-listing frame |
| CTA glow (light) | `0 12px 28px -12px rgba(90,63,224,.65)` |
| CTA glow (dark) | `0 12px 28px -12px rgba(167,139,255,.5)` |
| Popover / sheet shadow | `0 24px 48px -16px rgba(28,21,38,.25)` |
| Borders | 1px `--border` everywhere; never darker than that |
| Header / footer | `bg-background/90 backdrop-blur-md`, `border-b` / `border-t` `--border`; drop `shadow-sm` |

Register in `@theme inline`: `--shadow-card`, `--shadow-card-dark`, `--shadow-cta`, `--shadow-popover` so they are usable as `shadow-card` etc.

---

## 5. Components

### 5.1 Button (`components/ui/button/index.ts`)

| Variant | Light | Dark | Hover |
|---|---|---|---|
| `default` | `bg-primary text-primary-foreground shadow-cta` | same tokens | `bg-primary-700` / `bg-primary-500` |
| `secondary` | `bg-secondary text-secondary-foreground` | same | `bg-primary-200` / `bg-primary-800` |
| `outline` | `border border-border bg-card text-foreground` | same | `bg-accent text-accent-foreground` |
| `ghost` | transparent | | `bg-accent text-accent-foreground` |
| `destructive` | unchanged | | |
| `link` | `text-primary underline-offset-4` | | underline |

Sizes: `default` h-11, `lg` h-12, `sm` h-9, `icon` size-10. All `rounded-[14px]` except `icon` in the header which stays `rounded-[14px]` too (not circular).

**Retire every gradient.** Replace `bg-linear-to-r from-violet-500 to-fuchsia-500 … hover:from-violet-600 hover:to-fuchsia-600` with `variant="default"` in:
- `pets/CreatePetButton.vue` — also drop the pinned `text-white` (the variant token now carries the correct colour in both schemes).
- `pets/card/PetCardActions.vue` "View Details" → **`variant="secondary"`**, `h-11 rounded-[14px]` (not `rounded-full`). The round `StartConversationButton` beside it → `variant="outline" size="icon"`, `size-11 rounded-[14px]`.
- `messaging/StartConversationButton.vue` icon appearance → same outline treatment.
- `auth/VerifyEmailResendForm.vue` → `variant="default"`.
- `pets/card/PetCardBadges.vue` — status pill: `bg-card/90 text-foreground backdrop-blur`; listing-type pill: `bg-primary text-primary-foreground`. No gradients, no coloured shadows.
- `pets/card/PetCardMedia.vue` distance chip → `bg-card/90 text-foreground backdrop-blur rounded-full text-[11px] font-medium`.

### 5.2 Listing card (`PetListingCard` + `card/*`)

- Frame: `bg-card rounded-[20px] border border-border shadow-card overflow-hidden`. Remove the `is_owner` emerald branch — ownership is already shown by the "Your listing" status pill.
- Photo: 220px tall (was 256), full-bleed top; no inset.
- Content padding `px-[18px] pt-4 pb-[18px]`, `gap-3`.
- Name row: Newsreader 21/500; gender pill `bg-secondary text-secondary-foreground`; price `text-[13px] font-semibold text-primary`.
- Breed · age: 13px `text-muted-foreground`.
- Description: 13px/1.55 soft text, 3-line clamp (unchanged behaviour).
- Divider: plain 1px `--border` line — remove the people-glyph ornament.
- Engagement row: 12px/500 `text-muted-foreground`, icons 16px stroke 1.6.
- Action row: outline icon button 44×44 + secondary "View details" h-11 flex-1.

### 5.3 Header (`PublicHeader`, `BrandMark`, `ShellSearchField`)

- Brand mark: 30px, `rounded-[10px] bg-primary`, 9px dot in `--primary-foreground`. Wordmark Newsreader 21/500.
- Search: `bg-card border border-border rounded-[14px] h-11`, placeholder `text-muted-foreground`.
- Icon buttons 38px, `rounded-[14px]`, ghost hover. Unread dot: 7px `bg-primary` with `ring-2 ring-background`.
- Avatar: 34px `rounded-xl bg-primary text-primary-foreground` initials, 12/600.

### 5.4 Filter chips (`PetFilterSheet` trigger, `NearbySearchButton`)

`h-10 rounded-[14px] border border-border bg-card px-3.5 text-[13px] font-medium`. Active count badge: `bg-primary text-primary-foreground rounded-full min-w-[18px] h-[18px] text-[11px] font-semibold`.

### 5.5 Inputs, selects, textareas

`h-11 rounded-[14px] bg-input border border-border px-3.5 text-[15px]`; focus `border-primary ring-2 ring-ring/30`. Labels 13/600 `text-foreground`. Error text 13px `--destructive`.

### 5.6 Dialogs, sheets, popovers, dropdown menus

`bg-popover rounded-[20px] border border-border shadow-popover`. Menu items `rounded-[10px]`, hover `bg-accent text-accent-foreground`. Sheet from the side keeps square outer corners on the viewport edge, 20px on the inner edge.

### 5.7 Messaging

- Own bubble: `bg-primary text-primary-foreground rounded-[18px] rounded-br-[6px]`.
- Other bubble: `bg-card border border-border rounded-[18px] rounded-bl-[6px]`.
- Unread row (`MessagePreviewRow`): `bg-accent`; unread avatar ring `ring-2 ring-primary`; dot `bg-primary`.
- Composer: card-style input, send button `variant="default" size="icon"`.

### 5.8 Notifications

Row icon disc `bg-secondary text-secondary-foreground rounded-xl size-9`; unread row `bg-accent`.

### 5.9 Auth pages

`AuthSplitLayout` / `AuthCardLayout`: card `rounded-[20px] shadow-popover`; drop the violet/fuchsia blurred orbs and gradient hero in `VerifyEmail*.vue` — replace with a flat `bg-secondary` hero panel carrying the brand mark and Newsreader heading. Hint icon discs → `bg-secondary text-secondary-foreground`. Success notice (`VerificationLinkSentNotice`) keeps emerald as the single semantic-success exception.

### 5.10 Footer

`bg-background/90 border-t border-border`. Social marks 30px `rounded-[14px] border border-border`. Real links 12/600 `text-foreground`; placeholder labels 12/400 `text-muted-foreground` (existing weight-not-colour rule stays).

---

## 6. Focus & states

- Focus ring: `focus-visible:ring-2 ring-ring ring-offset-2 ring-offset-background` — brand colour at **full opacity** (the previous 50 % alpha rule is dropped because the ring is now solid; 6.2:1 light, 9.4:1 dark against the page).
- Hover transitions: `transition-[background-color,border-color,box-shadow,transform] duration-200 ease-out`.
- Disabled: `opacity-50`, no colour change.
- Skeletons: `bg-muted rounded-[12px]`.

---

## 7. Semantic colours (unchanged)

Destructive `#DC2828`; success `emerald-600`/`emerald-400` only for the "link sent" notice and health "vaccinated" ticks; warning `amber-600`. No other chroma.

---

## 8. Mobile app (PetConnect Mobile)

Same tokens, same families. Platform adjustments:

| Property | Value |
|---|---|
| Page bg | `#F6F4FB` light / `#0F0C18` dark |
| Tab bar | `bg-card/92` with blur, top hairline `--border`; active icon + label `--primary`, inactive `--muted-foreground`; centre "Post" tab is a 52px `rounded-[18px] bg-primary` disc with `shadow-cta` |
| Cards | `rounded-[20px]`, `shadow-card`, photo 200px tall |
| Buttons | h-52 (iOS) / h-48 (Android), `rounded-[14px]`; primary solid, secondary wash — never two solids on one screen section |
| Bottom sheets | `bg-popover rounded-t-[24px]`, 36×4 grabber `bg-border` |
| Inputs | h-52, `rounded-[14px]`, `bg-input` |
| Pills | `rounded-full`, 12/600 |
| Headings | Newsreader 500: screen titles 30px, section 22px, card names 19px. RTL: Readex Pro 600 |
| Body / UI | Manrope 15 / 14 ; Arabic Readex Pro |
| Status bar | light content on dark scheme, dark content on light; header `bg-background/90` blur |
| Minimum hit target | 44 × 44 |
| Splash / app icon | `#5A3FE0` field, white dot mark (10px radius square on icon grid) |

Dark mode on mobile uses the same `.dark` tokens; do not introduce an OLED pure-black variant.

---

## 9. Migration checklist for the agent

1. `resources/css/app.css` — replace ramp, `:root`, `.dark`, `--radius`, fonts; add shadow tokens. Keep every token name.
2. `resources/views/app.blade.php` — pre-paint backgrounds; font link.
3. `components/ui/button/index.ts` — variants and radii per §5.1.
4. Grep and remove: `from-violet`, `to-fuchsia`, `from-teal`, `to-emerald`, `from-emerald`, `shadow-violet`, `shadow-emerald`, `shadow-teal`, `border-emerald`, `bg-violet-`, `bg-fuchsia-`, `text-violet-`, `text-fuchsia-`, `drop-shadow-violet`. Replace with token utilities (`bg-primary`, `bg-secondary`, `text-primary` …). Files known to hit: `CreatePetButton`, `PetCardActions`, `PetCardBadges`, `PetCardMedia`, `PetListingCard`, `StartConversationButton`, `VerifyEmail*.vue`, `VerificationLinkSentNotice` (keep emerald), `AuthBrandLockup`.
5. `rounded-2xl` on cards → `rounded-[20px]`; `rounded-full` on rectangular buttons → `rounded-[14px]`; avatars `rounded-full` → `rounded-xl`.
6. Headings: add `font-serif` to h1/h2/h3, `BrandMark`, `PetCardHeader` name, `PetDetailHeader` name, `Heading.vue`.
7. Remove ornamental divider in `PetListingCard`.
8. Re-run the contrast measurements noted in docblocks and update those comments to the new values; do not delete the docblocks.
9. Visual check both schemes × both locales on: Home, pets/Show, pets/Create, messaging/Index + Show, profile/Show, settings/*, auth/Login, auth/VerifyEmail.
10. Apply §8 to the mobile app.

---

## 10. Files in this bundle

- `PETCONNECT-STYLE-GUIDE.md` — this document.
- `PetConnect Web Styles.dc.html` — design reference; option **2b** is the target (light and dark artboards). Ignore 1a–1c, 2a, 2c.
