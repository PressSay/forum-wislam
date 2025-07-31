<?php

namespace App\Http\Controllers;

use Laravel\Jetstream\JetStream;
use Laravel\Fortify\Features;
use Illuminate\Http\Request;
use Laravel\Jetstream\Http\Controllers\Inertia\UserProfileController as UserProfileControllerJetstream;

class UserProfileController extends UserProfileControllerJetstream
{
    /**
     * Show the profile detail screen.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Inertia\Response
     */
    public function detail(Request $request)
    {
        $this->validateTwoFactorAuthenticationState($request);

        return Jetstream::inertia()->render($request, 'Profile/detail', [
            'confirmsTwoFactorAuthentication' => Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm'),
            'sessions' => $this->sessions($request)->all(),
        ]);
    }
}