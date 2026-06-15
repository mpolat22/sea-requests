<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalPendingController extends Controller
{
    public function __invoke(Request $request): Response
    {
        return Inertia::render('Auth/ApprovalPending', [
            'isSeller' => $request->user()?->isSeller() ?? false,
            'hasSubmittedSellerVerification' => $request->user()?->hasSubmittedSellerVerification() ?? false,
        ]);
    }
}
