# Frontend patterns

## Boot and layouts

`resources/js/app.ts` picks the layout stack from the page name, so pages never import layouts themselves:

```ts
function resolvePageShell(name: string) {
    switch (true) {
        case name === 'auth/VerifyEmail':
            return [];
        case name.startsWith('auth/'):
            return [AuthLayout];
        case name.startsWith('settings/'):
            return [PublicLayout, SettingsLayout];
        default:
            return [PublicLayout];
    }
}

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => [RootLayout, ...resolvePageShell(name)],
    // ...
});
```

`RootLayout` mounts the toaster once and sets `lang`/`dir`; `PublicLayout` is the header and shell for every public and member page.

## Shared props

`HandleInertiaRequests` shares three things on every response, plus the translation catalogue once per locale per session:

```php
public function share(Request $request): array
{
    return [
        ...parent::share($request),
        'name'   => config('app.name'),
        'auth'   => ['user' => $user === null ? null : AuthenticatedUserResource::make($user)],
        'locale' => $this->localeProps(),
    ];
}

public function shareOnce(Request $request): array
{
    return [
        'translations' => Inertia::once(
            fn (): array => $this->buildTranslationCatalogue->handle($locale),
        )->as("translations.{$locale}"),
    ];
}
```

The user is a resource, never the raw model, so nothing sensitive leaks into the page payload by accident.

## Translations

```ts
const { t } = useTranslations();

t('messaging.message_name_about', { name: owner.name, pet: petName });
```

Keys are the same strings `__()` takes on the server. Both `:name` and `{name}` placeholders are filled, longest key first, so `:count` never eats `:count_other`.

## Routes through Wayfinder

Never type a URL. Import the generated function for the route or controller action:

```ts
import { show as showPet } from '@/routes/pets';
import { store as storeComment } from '@/routes/comments';

<Link :href="showPet.url(pet.slug)">…</Link>
```

Generated files live in `resources/js/routes`, `resources/js/actions` and `resources/js/wayfinder`; they are git-ignored and rebuilt by the Vite plugin.

## Forms

Writes use Inertia v3's `<Form>` bound to a Wayfinder form definition. The server's validation errors arrive in the slot; no manual error wiring.

```vue
<Form
    v-bind="storeComment.form({ commentable_type: commentableType, commentable_id: commentableId })"
    reset-on-success
    :options="options"
    v-slot="{ errors, processing }"
    @success="content = ''; emit('posted'); surface.onMutated();"
>
    <Textarea v-model="content" name="content" rows="3" required :maxlength="maxLength ?? undefined" />
    <InputError :message="errors.content" />
    <Button type="submit" :disabled="processing">…</Button>
</Form>
```

`useMutationSurface()` supplies the visit options (preserve scroll, partial reloads) and a hook to refresh the surrounding list after a successful write.

## Typed props

Every page declares its props from `resources/js/types`, which mirror the PHP resources:

```ts
const { pet, canInteract } = defineProps<{
    pet: PetCard;
    canInteract: boolean;
    reportCategories?: SelectOption<ReportCategory>[];
}>();
```

If a resource gains a field, add it to the matching type in the same change.

## Components

- `components/ui/*`: shadcn-style primitives on reka-ui. Use these before writing a native element.
- `components/<feature>/*`: feature components, for example `components/pets/card/PetCardActions.vue` composes the like, comment, share and message controls for a card.
- Keep a component to one root element.
- Icons come from `@lucide/vue`.

## Real time

```ts
configureEcho({ broadcaster: 'reverb' });

useEchoNotification(`App.Models.User.${user.id}`, (notification) => { /* … */ });
```

Composables from `@laravel/echo-vue` subscribe and unsubscribe with the component.

## Styling

Tailwind v4 utilities on top of the CSS tokens in `resources/css/app.css`. Use semantic tokens (`bg-primary`, `text-muted-foreground`) rather than palette values, logical properties (`ms-`, `pe-`) rather than `ml-`/`pr-`, and the `LISTING_TYPE_PILL_CLASSES` map for listing-type colours.
