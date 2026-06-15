<?php

namespace App\Support;

use App\Models\User;

class DashboardRedirector
{
    public function blockingRouteFor(User $user): ?string
    {
        if (! $user->hasVerifiedEmail()) {
            return route('verification.notice');
        }

        if ($user->requiresSellerVerification() && ! $user->hasSubmittedSellerVerification()) {
            return route('seller.verification.create');
        }

        if ($user->isSeller() && $user->approval_status === 'rejected') {
            return route('seller.verification.create');
        }

        if (! $user->isAdmin() && ! $user->isApproved()) {
            return route('approval.pending');
        }

        return null;
    }

    public function intendedOrHome(User $user, ?string $next = null): string
    {
        if (filled($next) && str_starts_with($next, '/')) {
            return $next;
        }

        if ($user->isAdmin()) {
            return route('admin.dashboard');
        }

        return $user->isSeller()
            ? route('seller.dashboard')
            : route('buyer.requests');
    }
}
