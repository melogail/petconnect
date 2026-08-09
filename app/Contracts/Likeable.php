<?php

namespace App\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

interface Likeable
{
    public function likes(): MorphMany;

    /**
     * Users who should be notified when this model is liked.
     *
     * @return Collection<int, \App\Models\User>
     */
    public function likeNotificationRecipients(): Collection;
}
