<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSellerWorkspaceReady
{
    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user?->isSeller()) {
            abort(403);
        }

        if (! $user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        if (! $user->hasSubmittedSellerVerification()) {
            return redirect()->route('seller.verification.create');
        }

        if ($user->approval_status === 'rejected') {
            return redirect()->route('seller.verification.create');
        }

        if (! $user->isApproved()) {
            return redirect()->route('approval.pending');
        }

        return $next($request);
    }
}
