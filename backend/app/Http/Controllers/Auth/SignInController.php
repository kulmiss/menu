<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Security\SuperAdminAccess;
use App\Support\Site\AccountPage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The two sign-in pages (QUESTIONS #216): /signin for restaurant teams, in the
 * landing page's style, and the Super Admin's plain page at
 * /{SUPER_ADMIN_PATH}/sign-in. Both forms post to Fortify's own sign-in
 * (same checks, limits and two-factor step as before); the page decides
 * which kind of account may sign in there (SignInArea).
 */
class SignInController extends Controller
{
    /** Notices the e-mail links and "Continue with Google" send people here with. */
    private const VERIFY = ['done', 'already', 'expired', 'invalid', 'change_done', 'change_expired', 'change_invalid'];

    private const GOOGLE = ['cancelled', 'failed', 'unverified', 'super_admin', 'expired'];

    public function restaurant(Request $request): View|RedirectResponse
    {
        if ($request->user() !== null) {
            return redirect('/admin');
        }
        // Back to the admin page, or the invitation, they came from.
        $redirect = $request->query('redirect');
        $request->session()->put('url.intended', AccountPage::safeRedirect($redirect, '/admin') ?? AccountPage::safeRedirect($redirect, '/invite') ?? '/admin');

        return view('account.sign-in', [
            ...AccountPage::data(),
            ...$this->notices($request),
            'action' => '/signin',
            'plain' => false,
        ]);
    }

    public function super(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if ($user !== null) {
            // Signed in already: their own area; a restaurant account sees nothing here.
            abort_unless($user->is_super_admin, 404);

            return redirect(SuperAdminAccess::url());
        }
        $request->session()->put('url.intended', AccountPage::safeRedirect($request->query('redirect'), SuperAdminAccess::url()) ?? SuperAdminAccess::url());

        return view('account.sign-in', [
            ...AccountPage::data(),
            'notice' => $request->boolean('expired') ? ['ok' => false, 'text' => __('account.signin.expired')] : null,
            'action' => SuperAdminAccess::url('sign-in'),
            'plain' => true,
        ]);
    }

    /**
     * The code step after a right password (Fortify keeps `login.id` in the
     * session). The Super Admin gets the plain page.
     */
    public function twoFactor(Request $request): View|RedirectResponse
    {
        $user = User::find($request->session()->get('login.id'));
        if ($user === null) {
            return redirect('/signin')->withErrors(['code' => __('account.two_factor.gone')]);
        }

        return view('account.two-factor', [
            ...AccountPage::data(),
            'plain' => $user->is_super_admin,
            'restart' => $user->is_super_admin ? SuperAdminAccess::url('sign-in') : '/signin',
        ]);
    }

    /** @return array{notice: array{ok: bool, text: string}|null} */
    private function notices(Request $request): array
    {
        $verify = $request->query('verify');
        if (is_string($verify) && in_array($verify, self::VERIFY, true)) {
            return ['notice' => ['ok' => in_array($verify, ['done', 'already', 'change_done'], true), 'text' => __("account.notices.verify.{$verify}")]];
        }
        $google = $request->query('google');
        if (is_string($google) && in_array($google, self::GOOGLE, true)) {
            return ['notice' => ['ok' => false, 'text' => __("account.notices.google.{$google}")]];
        }
        $password = $request->query('password');
        if ($password === 'changed' || $password === 'welcome') {
            return ['notice' => ['ok' => true, 'text' => __($password === 'changed' ? 'account.notices.password_changed' : 'account.notices.welcome_password_set')]];
        }
        if ($request->boolean('expired')) {
            return ['notice' => ['ok' => false, 'text' => __('account.signin.expired')]];
        }

        return ['notice' => null];
    }
}
