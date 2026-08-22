<?php

namespace App\Http\Controllers;

class NotificationsController extends Controller
{
    /**
     * The notification feed page shell — the actual data comes from
     * NotificationFeedController's API, which this page calls client-side.
     */
    public function index()
    {
        return view('notifications.index');
    }
}
