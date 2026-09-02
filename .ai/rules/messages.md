---
paths:
  - 'app/Pipelines/Messages/**'
---

# Messages

## StartDirectConversation\EnsureRecipientAccepts is unreachable today — defence in depth, not dead code
`User::acceptsMessagesFrom()` is `return $this->isActive();` and nothing else, and `Actions\Messaging\StartConversation::resolveRecipient()` resolves through `User::resolveRouteBinding()`, which refuses a deactivated account with a ModelNotFoundException (404) before the pipeline is constructed. So `StartDirectConversation\EnsureRecipientAccepts` — and its abort `Exceptions\Messaging\ConversationNotPermitted` — cannot currently fire through the Action.

Do not delete either as unused. That step is the seam a recipient block list or a per-recipient message setting lands on: it fires the moment `acceptsMessagesFrom()` gains a second clause, and it is the only consent check on a thread opened with no `initial_message` (that path never reaches the send flow). Both docblocks now scope themselves to consent reasons only and name resolution as the deactivation answer — keep them that way; the earlier "same message whatever the reason" wording would, once a block list lands, read as a promise that "blocked" and "deactivated" are indistinguishable, which they are not (422 vs 404) and which a well-meaning fix would close by reinstating `Rule::exists` on `recipient_id` and with it the enumeration oracle.

Different step, do not confuse them: `Messages\Send\EnsureRecipientAccepts` / `RecipientNotAcceptingMessages` sits on the message path into an existing thread, which is not re-resolved by id, and remains fully reachable.
