<?php

namespace App\Http\Controllers\Auth\Concerns;

use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Auth pages can load inside the landing page's iframe with ?embed=1.
 */
trait HandlesEmbed
{
    protected function isEmbed(Request $request): bool
    {
        return $request->input('embed') === '1' || $request->query('embed') === '1';
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    protected function embedRoute(Request $request, string $name, array $parameters = []): string
    {
        if ($this->isEmbed($request)) {
            $parameters['embed'] = 1;
        }

        return route($name, $parameters);
    }

    /**
     * Breaks out of the iframe so the dashboard opens in the full window.
     */
    protected function topWindowRedirect(string $url): View
    {
        return view('auth.top-redirect', ['url' => $url]);
    }
}
