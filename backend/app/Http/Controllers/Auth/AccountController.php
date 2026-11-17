<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Api\AcceptInvitationController;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\PlatformSettings;
use App\Support\Security\SuperAdminAccess;
use App\Support\SignUp\SignUpForm;
use App\Support\Site\AccountPage;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * The account pages in the landing page's style (QUESTIONS #222): sign-up
 * (or "contact us" while it is off), "check your inbox", "waiting for
 * approval", forgot / reset password, team invitations and sign-out. Server
 * pages with plain forms: they work without JavaScript.
 */
class AccountController extends Controller
{
    public function signUp(Request $request): View|RedirectResponse
    {
        if ($request->user() !== null) {
            return redirect('/admin');
        }
        if (! (bool) PlatformSettings::get('signup_open')) {
            return $this->closed();
        }
        $s = PlatformSettings::all();

        return view('account.sign-up', [
            ...AccountPage::data(),
            'turnstile' => $s['turnstile_enabled'] ? $s['turnstile_site_key'] : null,
            'terms' => trim((string) $s['terms']) !== '',
            'privacy' => trim((string) $s['privacy']) !== '',
            'notice' => $request->query('google') === 'closed' ? ['ok' => false, 'text' => __('account.signup.google_closed')] : null,
        ]);
    }

    public function signUpForm(Request $request, SignUpForm $form): RedirectResponse
    {
        if (! (bool) PlatformSettings::get('signup_open')) {
            return redirect('/signup');
        }
        // The Turnstile widget names its field cf-turnstile-response.
        $request->merge(['turnstile' => (string) $request->input('cf-turnstile-response', $request->input('turnstile', ''))]);
        try {
            $user = $form->submit($request);
        } catch (HttpException $e) {
            // Too many sign-ups from this network or for this address: say so on the form.
            throw_unless($e->getStatusCode() === 429, $e);

            return back()->withInput($request->except('password'))->withErrors(['email' => __('account.signup.too_many')]);
        }
        if ($user !== null) {
            Auth::guard('web')->login($user);
            $request->session()->regenerate();
        }

        // New account, known address or a bot: the same "check your inbox" (no hint who has an account).
        return redirect('/verify-email');
    }

    /** Sign-up off (owner, 2026-11-17): "Contact us on WhatsApp", else e-mail, in the same style. */
    private function closed(): View
    {
        $data = AccountPage::data();
        $text = __('account.closed.message', ['name' => $data['platform']]);
        $contact = $data['contact'];

        return view('account.closed', [
            ...$data,
            'whatsapp' => $contact['whatsapp'] !== null ? $contact['whatsapp'].'?text='.rawurlencode($text) : null,
            'email' => $contact['email'] !== null ? 'mailto:'.$contact['email'].'?subject='.rawurlencode($text) : null,
        ]);
    }

    /** "Check your inbox": for the new owner until the link is opened; a general text for anyone else. */
    public function verifyEmail(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if ($user !== null && ($user->hasVerifiedEmail() || $user->is_super_admin)) {
            return redirect('/admin');
        }

        return view('account.verify-email', [...AccountPage::data(), 'user' => $user]);
    }

    public function resendVerification(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user->hasVerifiedEmail()) {
            return redirect('/admin');
        }
        $user->sendEmailVerificationNotification();

        return back()->with('status', __('account.verify.sent', ['email' => $user->email]));
    }

    public function changeEmail(Request $request, SignUpForm $form): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect('/admin');
        }
        $user = $form->changeEmail($request);

        return redirect('/verify-email')->with('status', __('account.verify.changed', ['email' => $user->email]));
    }

    /** "I opened the link": the admin when it is confirmed, else this page says so. */
    public function checkVerified(Request $request): RedirectResponse
    {
        return $request->user()->hasVerifiedEmail()
            ? redirect('/admin')
            : redirect('/verify-email')->with('warning', __('account.verify.not_yet'));
    }

    /** After the e-mail is confirmed while sign-up needs approval: what happens next. */
    public function waiting(Request $request): View|RedirectResponse
    {
        $tenant = $request->user()->memberships()->with('tenant')->get()->pluck('tenant')
            ->first(fn (?Tenant $t) => $t?->signup_state === Tenant::SIGNUP_PENDING);
        if ($tenant === null) {
            return redirect('/admin');
        }

        return view('account.waiting', [...AccountPage::data(), 'restaurant' => $tenant->name]);
    }

    public function forgotPassword(Request $request): View|RedirectResponse
    {
        if ($request->user() !== null) {
            return redirect('/admin');
        }

        return view('account.forgot-password', AccountPage::data());
    }

    public function resetPassword(Request $request): View|RedirectResponse
    {
        if ($request->user() !== null) {
            return redirect('/admin');
        }
        $token = $request->query('token');
        $email = $request->query('email');

        return view('account.reset-password', [
            ...AccountPage::data(),
            'token' => is_string($token) ? $token : null,
            'email' => is_string($email) ? $email : null,
            'welcome' => $request->boolean('welcome'),
        ]);
    }

    public function invitation(Request $request, string $token): View
    {
        $invitation = AcceptInvitationController::open($token);
        $user = $request->user();

        return view('account.invite', [
            ...AccountPage::data(),
            'token' => $token,
            'invitation' => $invitation,
            'user' => $user,
            'mine' => $invitation !== null && $user !== null && Str::lower($user->email) === $invitation->email,
            'hasAccount' => $invitation !== null && User::where('email', $invitation->email)->exists(),
        ]);
    }

    /** Signed in with the invited address: join the restaurant and open it. */
    public function acceptInvitation(Request $request, string $token, ActivityLogger $activity): RedirectResponse
    {
        $invitation = AcceptInvitationController::open($token);
        abort_if($invitation === null, 404);
        $membership = AcceptInvitationController::join($invitation, $request->user(), $activity);

        return redirect('/admin?restaurant='.$membership->tenant_id);
    }

    /** No account yet: name + password, then signed in to the inviting restaurant. */
    public function joinInvitation(Request $request, string $token, CreatesNewUsers $creator): RedirectResponse
    {
        $invitation = AcceptInvitationController::open($token);
        abort_if($invitation === null, 404);
        $user = $creator->create([
            'invitation_token' => $token,
            'name' => (string) $request->input('name'),
            'email' => $invitation->email,
            'password' => (string) $request->input('password'),
            'password_confirmation' => (string) $request->input('password'),
        ]);
        event(new Registered($user));
        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return redirect('/admin?restaurant='.$invitation->tenant_id);
    }

    /** Sign out (forms on the account pages): back to this area's sign-in page. */
    public function signOut(Request $request): RedirectResponse
    {
        $admin = (bool) $request->user()?->is_super_admin;
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $back = AccountPage::safeRedirect($request->input('redirect'), '/invite');

        return redirect($admin ? SuperAdminAccess::url('sign-in') : ($back !== null ? '/signin?redirect='.rawurlencode($back) : '/signin'));
    }
}
