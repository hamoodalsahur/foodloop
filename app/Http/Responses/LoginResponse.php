<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Symfony\Component\HttpFoundation\Response;

/**
 * Where people go right after logging in or registering: back to the page
 * they were trying to open, otherwise to their own area (customers to the
 * deals, sellers to /seller, admins to /admin).
 */
class LoginResponse implements LoginResponseContract, RegisterResponseContract
{
    public function toResponse($request): Response
    {
        if ($request->wantsJson()) {
            return new JsonResponse(['two_factor' => false], 200);
        }

        return redirect()->intended($request->user()->homeUrl());
    }
}
