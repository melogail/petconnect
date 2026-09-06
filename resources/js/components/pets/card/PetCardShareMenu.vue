<script setup lang="ts">
import { Share2 } from '@lucide/vue';
import { computed } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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
 * `pets.show`'s own route helper keyed by `petId`, so the identity of the thing
 * being shared cannot drift from the card rendering the control.
 *
 * `document.baseURI` supplies the scheme and host and nothing else: the helper
 * returns a root-absolute path (`/pets/3`), so `new URL()` takes only the
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
 */
const { petId, name } = defineProps<{
    petId: number;
    name: string;
}>();

/** This listing's absolute URL. See the note above before changing the base. */
const petUrl = computed(() => {
    const path = showPet(petId).url;

    return typeof document === 'undefined'
        ? path
        : new URL(path, document.baseURI).toString();
});

/** Brand icons were dropped from `@lucide/vue` v1, so these are text-only. */
const destinations = computed(() => {
    const url = encodeURIComponent(petUrl.value);
    const text = encodeURIComponent(`Meet ${name} on PetConnect`);

    return [
        {
            key: 'facebook',
            label: 'Facebook',
            href: `https://www.facebook.com/sharer/sharer.php?u=${url}`,
        },
        {
            key: 'x',
            label: 'X',
            href: `https://twitter.com/intent/tweet?url=${url}&text=${text}`,
        },
        {
            key: 'whatsapp',
            label: 'WhatsApp',
            href: `https://wa.me/?text=${text}%20${url}`,
        },
    ];
});

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
            <!-- A quiet round pill, legacy's action-row shape (restyled 2026-09-06). -->
            <Button
                variant="ghost"
                size="icon"
                :aria-label="`Share ${name}`"
                class="text-muted-foreground hover:bg-muted hover:text-primary-600 dark:hover:text-primary-400 rounded-full"
            >
                <Share2 class="size-5" aria-hidden="true" />
            </Button>
        </DropdownMenuTrigger>

        <DropdownMenuContent align="start" class="w-44">
            <DropdownMenuItem
                v-for="destination in destinations"
                :key="destination.key"
                as-child
            >
                <a
                    :href="destination.href"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    {{ destination.label }}
                </a>
            </DropdownMenuItem>

            <DropdownMenuSeparator />

            <DropdownMenuItem @select="copyLink">Copy link</DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
