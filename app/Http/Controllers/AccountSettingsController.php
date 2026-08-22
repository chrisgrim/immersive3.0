<?php

namespace App\Http\Controllers;

class AccountSettingsController extends Controller
{
    /**
     * Account Settings shell — Personal info / Login & security / Privacy.
     * Same Blade view for every tab; the active tab is a path segment the Vue
     * shell reads client-side, same pattern as the Hub (see HubController).
     */
    public function index()
    {
        return view('account-settings.index');
    }
}
