import type { VariantProps } from "class-variance-authority"
import { cva } from "class-variance-authority"

export { default as Button } from "./Button.vue"

/*
  "Aubergine & Lavender" button treatment.

  One solid button per surface: `default` is the only solid violet, every
  secondary action is the tinted lavender wash. The focus ring is the brand
  colour at full opacity with a page-coloured offset — see the `--ring`
  comment in resources/css/app.css for why the earlier 50%-alpha ring went.
  Hover re-tones one ramp step rather than fading the surface with alpha, so
  the hover colour is measured, not derived.

  `default` wears the tonal brand gradient (`--gradient-brand` in app.css:
  primary-600→700 light, primary-300→400 dark) over a `bg-primary` fallback,
  and hovers one ramp step along the same hue. Every stop carries
  --primary-foreground past AA: white on #5A3FE0 6.2:1 and on #4A32C4 8.25:1;
  #15102A on #CDBDFF 11.4:1 and on #A78BFF 9.6:1. The guide's dark hover step
  `primary-500` (#7C5CF2) was rejected at 4.10:1 — a contrast failure is a
  defect, not a design decision.
*/
export const buttonVariants = cva(
  "inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-[14px] text-sm font-semibold transition-[background-color,border-color,box-shadow,transform,color] duration-200 ease-out disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive",
  {
    variants: {
      variant: {
        default:
          "bg-primary bg-gradient-brand text-primary-foreground shadow-cta hover:bg-gradient-brand-hover",
        destructive:
          "bg-destructive text-white hover:bg-destructive/90 focus-visible:ring-destructive dark:bg-destructive/60",
        outline:
          "border border-border bg-card text-foreground hover:bg-accent hover:text-accent-foreground",
        secondary:
          "bg-secondary text-secondary-foreground hover:bg-primary-200 dark:hover:bg-primary-800",
        ghost:
          "hover:bg-accent hover:text-accent-foreground",
        link: "text-primary underline-offset-4 hover:underline",
      },
      size: {
        "default": "h-11 px-4 py-2 has-[>svg]:px-3.5",
        "sm": "h-9 gap-1.5 px-3 has-[>svg]:px-2.5",
        "lg": "h-12 px-6 has-[>svg]:px-4",
        "icon": "size-10",
        "icon-sm": "size-9",
        "icon-lg": "size-11",
      },
    },
    defaultVariants: {
      variant: "default",
      size: "default",
    },
  },
)
export type ButtonVariants = VariantProps<typeof buttonVariants>
