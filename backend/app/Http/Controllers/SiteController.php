<?php

namespace App\Http\Controllers;

use App\Support\EmailLogo;
use App\Support\PlatformSettings;
use Illuminate\Contracts\View\View;

/**
 * The platform's own public pages: the home page and the Terms / Privacy
 * pages the sign-up form links to (owner, 2026-11-13). Texts come from
 * Super Admin → Settings.
 */
class SiteController extends Controller
{
    public function home(): View
    {
        return view('site.home', [
            'platform' => (string) PlatformSettings::get('name'),
            'logo' => EmailLogo::url(),
            'signupOpen' => (bool) PlatformSettings::get('signup_open'),
        ]);
    }

    public function legal(string $page): View
    {
        $text = trim((string) PlatformSettings::get($page));

        return view('site.legal', [
            'platform' => (string) PlatformSettings::get('name'),
            'title' => __("site.{$page}"),
            // Blank lines separate paragraphs; each paragraph keeps its line breaks.
            'paragraphs' => $text === '' ? [] : preg_split('/\R{2,}/u', $text),
        ]);
    }
}
