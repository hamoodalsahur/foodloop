<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Exceptions\AdminActionRefused;
use Closure;
use Illuminate\Http\RedirectResponse;

/**
 * The same ending for every admin button: run the action, go back to the
 * page it was pressed on, and show either what happened or why it was refused.
 */
trait AttemptsAdminActions
{
    /**
     * @param  Closure(): mixed  $action
     * @param  string|Closure(mixed): string  $success  the message, or a function that writes it from the action's result
     */
    protected function attempt(Closure $action, string|Closure $success): RedirectResponse
    {
        try {
            $result = $action();
        } catch (AdminActionRefused $refused) {
            return back()->withErrors(['admin' => $refused->getMessage()]);
        }

        return back()->with('status', $success instanceof Closure ? $success($result) : $success);
    }
}
