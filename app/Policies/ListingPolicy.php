<?php

namespace App\Policies;

use App\Models\Listing;
use App\Models\User;

class ListingPolicy
{
    public function update(User $user, Listing $listing): bool
    {
        return $user->sellerProfile?->id === $listing->seller_profile_id;
    }

    public function publish(User $user, Listing $listing): bool
    {
        return $this->update($user, $listing);
    }

    public function delete(User $user, Listing $listing): bool
    {
        return $this->update($user, $listing);
    }
}
