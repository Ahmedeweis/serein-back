<?php

namespace App\Observers;

use App\Models\Review;

class ReviewObserver
{
    /**
     * Handle the Review "saved" event (created or updated).
     */
    public function saved(Review $review): void
    {
        $review->product?->recalculateRatingMetrics();
    }

    /**
     * Handle the Review "deleted" event.
     */
    public function deleted(Review $review): void
    {
        $review->product?->recalculateRatingMetrics();
    }
}
