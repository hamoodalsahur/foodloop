<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The inbox behind the bell, for customers, sellers and admins alike.
 * What gets sent, and the wording, is in App\Services\Notifier.
 */
class NotificationController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        $messages = $user->notifications()->paginate(20);

        // New messages are highlighted on this visit (the page still has
        // them as unread), and the bell goes back to zero.
        $user->unreadNotifications()->update(['read_at' => now()]);

        return view('notifications.index', [
            'user' => $user,
            'messages' => $messages,
            'followedShops' => $user->isCustomer() ? $user->followedShops()->orderBy('business_name')->get() : collect(),
        ]);
    }
}
