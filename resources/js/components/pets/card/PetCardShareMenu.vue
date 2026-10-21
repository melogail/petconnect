<script setup lang="ts">
import { Link, Share2 } from '@lucide/vue';
import { type Component, computed } from 'vue';
import { toast } from 'vue-sonner';
import {
    FacebookIcon,
    WhatsAppIcon,
    XIcon,
} from '@/components/pets/card/shareIcons';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { show as showPet } from '@/routes/pets';

/**
 * Share one listing.
 *
 * ## The URL is this pet's, not this page's
 *
 * The legacy card built both its social links and its clipboard payload from
 * `encodeURIComponent(window.location.href)`. On a listing page that happened
 * to be right; on the feed — where every card is rendered — it meant all
 * twelve share buttons published the feed URL, so nobody who followed a shared
 * link ever landed on the pet that was shared. The path here comes from
 * `pets.show`'s own route helper keyed by `petSlug`, so the identity of the thing
 * being shared cannot drift from the card rendering the control.
 *
 * `document.baseURI` supplies the scheme and host and nothing else: the helper
 * returns a root-absolute path (`/pets/golden-retriever-k3m9x2qa`), so `new URL()` takes only the
 * origin from the base and the current page's path is discarded — which is the
 * whole bug, gone by construction rather than by remembering not to. There is
 * no `<base>` element in `resources/views/app.blade.php`, so `baseURI` is the
 * document URL. Under SSR there is no `document`; the computed is lazy and the
 * menu content is portalled and only rendered while open, so it is not
 * evaluated server-side at all, and the path fallback is belt-and-braces.
 *
 * ## No account required
 *
 * Every destination is either an external page or the clipboard, so this
 * control is the same for a guest as for a signed-in viewer — it fires no
 * request at this application and has no `login` branch.
 *
 * ## Use the primitives
 *
 * `ui/dropdown-menu` (Reka) owns the open state, the outside-click dismissal,
 * focus trapping and roving focus. Do not reintroduce the legacy hand-rolled
 * panel with its `document.addEventListener('click', …)` and its
 * `onMounted`/`onUnmounted` pair. A global `ConfigProvider` supplies `dir`, so
 * the portalled content is RTL-correct without a prop here.
 *
 * ## One row of logos, named by tooltip and by `aria-label`
 *
 * The destinations are a single horizontal row of icon-only items rather than
 * a stacked text list: the brand marks (`card/shareIcons`) plus Lucide's
 * `Link` for the clipboard. Each item is a `TooltipTrigger`, so hovering or
 * focusing it shows the platform's name, and carries the same name as its
 * `aria-label` — an icon-only control still needs an accessible name, and the
 * tooltip alone does not supply one to every assistive technology. The
 * `DropdownMenuItem` stays the outer wrapper so Reka's roving focus and
 * `Escape`/outside-click dismissal are unchanged; only the layout is a row.
 */
const { petSlug, name } = defineProps<{
    petSlug: string;
    name: string;
}>();

/** This listing's absolute URL. See the note above before changing the base. */
const petUrl = computed(() => {
    const path = showPet(petSlug).url;

    return typeof document === 'undefined'
        ? path
        : new URL(path, document.baseURI).toString();
});

type Destination = {
    key: string;
    label: string;
    icon: Component;
    href: string;
};

const destinations = computed<Destination[]>(() => {
    const url = encodeURIComponent(petUrl.value);
    const text = encodeURIComponent(`Meet ${name} on SniffPal`);

    return [
        {
            key: 'facebook',
            label: 'Facebook',
            icon: FacebookIcon,
            href: `https://www.facebook.com/sharer/sharer.php?u=${url}`,
        },
        {
            key: 'x',
            label: 'X',
            icon: XIcon,
            href: `https://twitter.com/intent/tweet?url=${url}&text=${text}`,
        },
        {
            key: 'whatsapp',
            label: 'WhatsApp',
            icon: WhatsAppIcon,
            href: `https://wa.me/?text=${text}%20${url}`,
        },
    ];
});

/**
 * One 36px square per destination. `justify-center` and `p-0` override the
 * item's text-row padding; the icon colour comes from the item itself, muted
 * at rest and `--accent-foreground` while focused or hovered.
 */
const itemClass = 'size-9 justify-center p-0';

/**
 * `navigator.clipboard` is undefined outside a secure context, which throws
 * synchronously inside the `try` and lands in the same branch as a denied
 * permission prompt. Both want the same message.
 */
async function copyLink(): Promise<void> {
    try {
        await navigator.clipboard.writeText(petUrl.value);
        toast.success(`Link to ${name} copied`);
    } catch {
        toast.error('Could not copy the link');
    }
}
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <!--
              A quiet ghost pill, legacy's action-row shape (restyled 2026-09-06);
              re-toned to the guide's engagement row — muted, 16px icon,
              `--primary` on hover, the button's 14px radius.
            -->
            <Button
                variant="ghost"
                size="icon"
                :aria-label="`Share ${name}`"
                class="text-muted-foreground hover:bg-accent hover:text-primary"
            >
                <Share2 class="size-4" aria-hidden="true" />
            </Button>
        </DropdownMenuTrigger>

        <DropdownMenuContent align="start" class="w-auto">
            <TooltipProvider :delay-duration="150">
                <div class="flex items-center gap-1">
                    <Tooltip
                        v-for="destination in destinations"
                        :key="destination.key"
                    >
                        <TooltipTrigger as-child>
                            <DropdownMenuItem as-child :class="itemClass">
                                <a
                                    :href="destination.href"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    :aria-label="destination.label"
                                >
                                    <component
                                        :is="destination.icon"
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                </a>
                            </DropdownMenuItem>
                        </TooltipTrigger>
                        <TooltipContent>{{ destination.label }}</TooltipContent>
                    </Tooltip>

                    <Tooltip>
                        <TooltipTrigger as-child>
                            <DropdownMenuItem
                                :class="itemClass"
                                aria-label="Copy link"
                                @select="copyLink"
                            >
                                <Link class="size-4" aria-hidden="true" />
                            </DropdownMenuItem>
                        </TooltipTrigger>
                        <TooltipContent>Copy link</TooltipContent>
                    </Tooltip>
                </div>
            </TooltipProvider>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
