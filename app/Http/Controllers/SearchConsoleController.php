<?php

namespace App\Http\Controllers;

use App\Models\Site;
use Illuminate\Routing\Controllers\HasMiddleware;
use App\Services\SearchConsoleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SearchConsoleController extends Controller implements HasMiddleware
{
    /**
     * Admin/staff only, every method - including the callback.
     *
     * Not just tidiness: listProperties() authenticates with the
     * SITE's stored token, which belongs to whoever connected it
     * (normally the agency's own Google account). So a restricted team
     * member opening the property picker for one site they have access
     * to would have seen every Search Console property that account
     * can read - i.e. other clients' domains. Gating the whole
     * controller is the fix; connecting Search Console is an agency
     * setup task, not something a per-site member needs at all.
     */
    public static function middleware(): array
    {
        return [
            function ($request, $next) {
                abort_unless(Auth::user()?->canManageTeam(), 403);

                return $next($request);
            },
        ];
    }

    public function connect(Site $site, SearchConsoleService $gsc)
    {
        if (! $gsc->isConfigured()) {
            return redirect('/sites/' . $site->id)
                ->with('status', 'Google credentials are not configured. Add GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET in .env.');
        }

        return redirect()->away($gsc->authUrl($site));
    }

    /**
     * Google's one fixed redirect for the whole app - which site this
     * belongs to comes back in `state`, set when the auth URL was
     * built. Looked up without the account scope because the scope is
     * about who's logged in, and the site is already identified by an
     * id we issued ourselves; the ownership check immediately after
     * is what actually matters.
     */
    public function callback(Request $request, SearchConsoleService $gsc)
    {
        if ($request->query('error')) {
            return redirect('/sites')->with('status', 'Google access was declined.');
        }

        $site = Site::find((int) $request->query('state'));

        if (! $site) {
            return redirect('/sites')->with('status', 'That connection could not be matched to a site.');
        }

        $result = $gsc->exchangeCode($site, (string) $request->query('code'));

        if ($result['error']) {
            return redirect('/sites/' . $site->id)->with('status', 'Could not connect: ' . $result['error']);
        }

        // Auto-select when exactly one property plausibly matches this
        // site's own domain - the common case, and asking someone to
        // pick from a list of one is pointless ceremony. Anything else
        // (several matches, none, a domain property vs URL-prefix
        // ambiguity) goes to the picker rather than guessing.
        $listed = $gsc->listProperties($site);

        $matches = array_values(array_filter(
            $listed['properties'],
            fn ($property) => SearchConsoleService::propertyMatchesSite($property, $site),
        ));

        if (count($matches) === 1) {
            $site->update(['gsc_property' => $matches[0]]);

            return redirect('/sites/' . $site->id)->with('status', 'Search Console connected.');
        }

        return redirect('/sites/' . $site->id . '/search-console/property')
            ->with('status', 'Connected — now choose which property to use.');
    }

    public function chooseProperty(Site $site, SearchConsoleService $gsc)
    {
        $listed = $gsc->listProperties($site);

        // Tagged rather than filtered - a non-matching property is
        // still selectable (see propertyMatchesSite for why an
        // apparent mismatch can be legitimate), it just has to be
        // chosen deliberately.
        $properties = array_map(fn ($property) => [
            'name' => $property,
            'matches' => SearchConsoleService::propertyMatchesSite($property, $site),
        ], $listed['properties']);

        return view('search-console.property', [
            'site' => $site,
            'properties' => $properties,
            'error' => $listed['error'],
        ]);
    }

    public function saveProperty(Request $request, Site $site, SearchConsoleService $gsc)
    {
        $data = $request->validate([
            'property' => ['required', 'string', 'max:255'],
        ]);

        // Only ever accepts a property this login genuinely has - a
        // value posted from a form is a value someone can edit.
        $listed = $gsc->listProperties($site);

        if (! in_array($data['property'], $listed['properties'], true)) {
            return redirect('/sites/' . $site->id . '/search-console/property')
                ->with('status', 'That property is not available on this Google login.');
        }

        // Guards the real mistake this is here to prevent: wiring one
        // client's search data into a different client's dashboard.
        // Rejected unless the form explicitly acknowledged it, so a
        // blind post can't do it silently either.
        if (! SearchConsoleService::propertyMatchesSite($data['property'], $site)
            && ! $request->boolean('confirm_mismatch')) {
            return redirect('/sites/' . $site->id . '/search-console/property')
                ->with('status', 'That property does not look like it belongs to ' . $site->url . '.');
        }

        $site->update(['gsc_property' => $data['property']]);

        return redirect('/sites/' . $site->id)->with('status', 'Search Console connected.');
    }

    public function disconnect(Site $site)
    {
        $site->update([
            'gsc_access_token' => null,
            'gsc_refresh_token' => null,
            'gsc_token_expires_at' => null,
            'gsc_property' => null,
        ]);

        return redirect('/sites/' . $site->id)->with('status', 'Search Console disconnected.');
    }
}
