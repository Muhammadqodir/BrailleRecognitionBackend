<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /** @var string */
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /** @return array<string, mixed> */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user('admin')?->only(['id', 'name', 'email']),
            ],
            /*
             * The dashboard's URLs live under config('braille.admin_path'), so the
             * frontend resolves them from here rather than hardcoding paths -- one
             * place to keep in sync with routes/web.php.
             */
            'routes' => [
                'login' => route('admin.login'),
                'logout' => route('admin.logout'),
                'dashboard' => route('admin.dashboard'),
                'users' => route('admin.users'),
                'translations' => route('admin.translations'),
                'subscriptions' => route('admin.subscriptions'),
                'settingsPassword' => route('admin.settings.password'),
                'settingsPasswordUpdate' => route('admin.settings.password.update'),
            ],
        ];
    }
}
