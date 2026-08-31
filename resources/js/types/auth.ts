export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

/**
 * The `auth` shared prop, on every page.
 *
 * ## `user` is typed non-nullable and is null for a guest
 *
 * `HandleInertiaRequests` shares `$request->user()`, so `auth.user` is **null
 * on every public page**. The type says otherwise on purpose, and it is a debt,
 * not a description: widening it to `User | null` today cascades into the
 * fifteen-odd scaffold pages that read `page.props.auth.user.name` straight
 * out, none of which a guest can reach.
 *
 * There are now five pages a guest *can* reach — `Home`, `pets/Show`,
 * `profile/Show`, `Help` and `Support`, the five `app.ts` maps to
 * `PublicLayout` — plus everything they render, `PublicHeader` included. Every
 * one of them guards at runtime (`page.props.auth.user ?? null`,
 * `Boolean(page.props.auth.user)`) because `vue-tsc` will not.
 *
 * **So: guard `auth.user` in anything a guest can reach, whatever this type
 * says.** The narrowing is a scaffold-page cleanup waiting for a phase with
 * room for it; the rule is `.ai/rules/types.md`, whose `paths` glob is
 * `resources/js/types/**` — the index maps the glob to the file.
 */
export type Auth = {
    /** Null for a guest, whatever the type says. See the note above. */
    user: User;
};

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
