<script setup lang="ts">
import { WhatsAppIcon } from "@/components/pets/card/shareIcons";
import { Button } from "@/components/ui/button";
import { useTranslations } from "@/composables/useTranslations";

/**
 * Opens a WhatsApp chat with a listing's owner. Rendered only when the owner
 * opted in (settings → Privacy) and `PetOwnerResource` shipped a `wa.me` link,
 * which it only does for a signed-in viewer. Same 44px outline square as
 * `StartConversationButton`'s icon appearance so the two sit as a pair.
 */
const { appearance = "icon" } = defineProps<{
    url: string;
    ownerName: string;
    /** `icon` is the 44px square beside a card's Message button; `default` carries the visible "WhatsApp" text. */
    appearance?: "default" | "icon";
}>();

const { t } = useTranslations();
</script>

<template>
    <Button
        as-child
        variant="outline"
        :size="appearance === 'icon' ? 'icon-lg' : 'default'"
        :class="appearance === 'icon' ? 'size-11 shrink-0 rounded-[14px]' : ''"
    >
        <a
            :href="url"
            target="_blank"
            rel="noopener"
            :aria-label="t('pets.whatsapp_name', { name: ownerName })"
            :title="t('pets.whatsapp_name', { name: ownerName })"
        >
            <WhatsAppIcon
                :class="appearance === 'icon' ? 'size-5' : 'size-4'"
                aria-hidden="true"
            />
            <template v-if="appearance !== 'icon'">
                {{ t("pets.whatsapp") }}
            </template>
        </a>
    </Button>
</template>
