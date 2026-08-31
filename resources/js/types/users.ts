/**
 * The single byline payload — `App\Http\Resources\User\UserSummaryResource`.
 *
 * It is what a conversation participant, a message sender and a review author
 * all serialise to, so it is typed once here. `Pet\PetOwnerResource` extends the
 * same class; `PetOwner` in `./pets` is that same shape under the name the pet
 * payloads use.
 */
export type UserSummary = {
    id: number;
    name: string;
    username: string | null;
    /** The coarse "City, State, Country" accessor — never a street address. */
    location: string | null;
    avatar: string | null;
};
