/**
 * What a `v-model` on an `<input>` actually puts into form state.
 *
 * Not `string`. Vue's `vModelText` casts the DOM value **unconditionally** for
 * a numeric input — `castToNumber = number || vnode.props.type === 'number'` —
 * so `v-model="form.age"` on an `<input type="number">` writes a `number` the
 * first time anyone types into it, whatever the state was initialised to. The
 * empty box is the one exception: `looseToNumber('')` cannot parse and hands
 * `''` straight back, so the pair is `'' | number`, i.e. `string | number`.
 *
 * The same is true of `v-model.number`, and of any control whose value is
 * seeded from an uncast `decimal` column (see `Coordinate` in `types/profile`),
 * which is a string on MySQL and a float on SQLite.
 *
 * Declaring form state as `InputValue` is what makes `vue-tsc` reject
 * `state.age.trim()` at the call site instead of leaving it to blow up in the
 * browser at submit time — which is exactly how three fields of the pet form
 * shipped unpublishable.
 */
export type InputValue = string | number;

/** An input's value as the string every string operation assumes it is. */
export function inputText(value: InputValue): string {
    return typeof value === 'string' ? value : String(value);
}

/** An input's value, trimmed. A blank box comes back as `''`. */
export function trimmedInput(value: InputValue): string {
    return inputText(value).trim();
}
