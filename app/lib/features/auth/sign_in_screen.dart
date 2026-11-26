import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../app/env.dart';
import '../../core/api/api_error.dart';
import '../../core/app_config.dart';
import '../../core/auth/auth_repository.dart';
import '../../core/auth/google_auth.dart';
import '../../core/providers.dart';
import '../../core/widgets/app_logo.dart';
import '../../core/widgets/kit.dart';

/// Sign-in and the two-factor step (backend QUESTIONS #187).
class SignInScreen extends ConsumerStatefulWidget {
  const SignInScreen({super.key});

  /// The website's "forgot password" page, if the server's config has no link (the old auth/… page is gone).
  static const forgotPasswordPath = 'forgot-password';
  @override
  ConsumerState<SignInScreen> createState() => _SignInScreenState();
}

class _SignInScreenState extends ConsumerState<SignInScreen> {
  final _form = GlobalKey<FormState>();
  final _email = TextEditingController();
  final _password = TextEditingController();
  final _code = TextEditingController();
  String? _challenge;
  bool _recovery = false;
  bool _busy = false;
  bool _showPassword = false;
  String? _error;
  Map<String, String?> _fieldErrors = {};
  int _logoTaps = 0;

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    _code.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!(_form.currentState?.validate() ?? false)) return;
    setState(() {
      _busy = true;
      _error = null;
      _fieldErrors = {};
    });
    final auth = ref.read(authRepositoryProvider);
    try {
      if (_challenge == null) {
        final r = await auth.login(_email.text, _password.text);
        switch (r) {
          case LoggedIn(:final token):
            await ref.read(sessionProvider.notifier).signIn(token);
          case NeedsTwoFactor(:final challenge):
            if (mounted) setState(() => _challenge = challenge);
          case NeedsRestaurant():
            break; // only after "Continue with Google"
        }
      } else {
        final token = await auth.twoFactor(_challenge!, code: _recovery ? null : _code.text, recoveryCode: _recovery ? _code.text : null);
        await ref.read(sessionProvider.notifier).signIn(token);
      }
    } on ApiError catch (e) {
      if (!mounted) return;
      setState(() {
        if (e.kind == ApiErrorKind.notFound && _challenge != null) {
          _challenge = null;
          _code.clear();
          _error = context.l.challengeExpired;
        } else if (e.kind == ApiErrorKind.validation) {
          _fieldErrors = {for (final f in e.fieldErrors.keys) f: e.field(f)};
        } else {
          _error = errorText(context, e);
        }
      });
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  /// "Continue with Google" (backend 2026-11-17): Google gives an ID token on
  /// the phone, the server answers like a password sign-in.
  Future<void> _google(String clientId) async {
    setState(() {
      _busy = true;
      _error = null;
      _fieldErrors = {};
    });
    final auth = ref.read(authRepositoryProvider);
    try {
      final idToken = await ref.read(googleIdTokensProvider).idToken(clientId);
      if (idToken == null) return; // the person closed Google's sheet
      var result = await auth.google(idToken);
      if (result case NeedsRestaurant(:final ticket, :final email)) {
        if (!mounted) return;
        setState(() => _busy = false); // the sheet has its own progress
        final token = await showModalBottomSheet<String>(
          context: context,
          isScrollControlled: true,
          showDragHandle: true,
          builder: (_) => GoogleFinishSheet(ticket: ticket, email: email),
        );
        if (token == null) return;
        if (mounted) setState(() => _busy = true);
        result = LoggedIn(token);
      }
      switch (result) {
        case LoggedIn(:final token, :final googleLinked):
          await ref.read(sessionProvider.notifier).signIn(token);
          if (googleLinked && mounted) showSnack(context, context.l.googleLinked);
        case NeedsTwoFactor(:final challenge):
          setState(() => _challenge = challenge);
        case NeedsRestaurant():
          break;
      }
    } on GoogleIdTokenFailure {
      if (mounted) setState(() => _error = context.l.googleUnavailable);
    } on ApiError catch (e) {
      if (mounted) setState(() => _error = errorText(context, e));
    } catch (e) {
      if (mounted) setState(() => _error = errorText(context, e));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _changeServer() async {
    final c = TextEditingController(text: ref.read(apiProvider).siteUrl);
    final v = await showDialog<String>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(ctx.l.serverAddress),
        content: TextField(
          controller: c,
          keyboardType: TextInputType.url,
          decoration: InputDecoration(hintText: ctx.l.serverHint),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: Text(ctx.l.cancel)),
          FilledButton(onPressed: () => Navigator.pop(ctx, c.text.trim()), child: Text(ctx.l.save)),
        ],
      ),
    );
    if (v != null && v.isNotEmpty) {
      ref.read(apiProvider).setServer(v);
      await ref.read(tokenStoreProvider).write('server', v);
      if (mounted) showSnack(context, v);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l;
    final config = ref.watch(appConfigProvider);
    final twoFactor = _challenge != null;
    return Scaffold(
      backgroundColor: context.colors.surface,
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: AutofillGroup(
                child: Form(
                  key: _form,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Center(
                        child: GestureDetector(
                          onTap: Env.canChangeServer ? () => ++_logoTaps >= 5 ? (_logoTaps = 0, _changeServer()) : null : null,
                          child: const AppLogo(),
                        ),
                      ),
                      const SizedBox(height: 16),
                      Text(
                        twoFactor ? l.twoFactorTitle : l.signInTitle,
                        textAlign: TextAlign.center,
                        style: context.text.headlineSmall?.copyWith(fontWeight: FontWeight.w700),
                      ),
                      const SizedBox(height: 6),
                      Text(
                        twoFactor ? l.twoFactorBody : l.signInSubtitle,
                        textAlign: TextAlign.center,
                        style: TextStyle(color: context.colors.onSurfaceVariant),
                      ),
                      const SizedBox(height: 28),
                      if (_error != null) ...[
                        Semantics(
                          liveRegion: true,
                          child: Text(_error!, style: TextStyle(color: context.colors.error)),
                        ),
                        const SizedBox(height: 12),
                      ],
                      if (!twoFactor) ...[
                        TextFormField(
                          key: const Key('email'),
                          controller: _email,
                          keyboardType: TextInputType.emailAddress,
                          autofillHints: const [AutofillHints.email, AutofillHints.username],
                          textInputAction: TextInputAction.next,
                          decoration: InputDecoration(labelText: l.email, errorText: _fieldErrors['email']),
                          validator: (v) => (v == null || !v.contains('@')) ? l.requiredField : null,
                        ),
                        const SizedBox(height: 14),
                        TextFormField(
                          key: const Key('password'),
                          controller: _password,
                          obscureText: !_showPassword,
                          autofillHints: const [AutofillHints.password],
                          onFieldSubmitted: (_) => _submit(),
                          decoration: InputDecoration(
                            labelText: l.password,
                            errorText: _fieldErrors['password'],
                            suffixIcon: IconButton(
                              tooltip: l.password,
                              icon: Icon(_showPassword ? AppIcons.eyeOff : AppIcons.eye),
                              onPressed: () => setState(() => _showPassword = !_showPassword),
                            ),
                          ),
                          validator: (v) => (v == null || v.isEmpty) ? l.requiredField : null,
                        ),
                      ] else
                        TextFormField(
                          key: const Key('code'),
                          controller: _code,
                          autofocus: true,
                          keyboardType: _recovery ? TextInputType.text : TextInputType.number,
                          autofillHints: const [AutofillHints.oneTimeCode],
                          inputFormatters: _recovery ? null : [FilteringTextInputFormatter.digitsOnly, LengthLimitingTextInputFormatter(6)],
                          style: _recovery ? null : const TextStyle(fontSize: 22, letterSpacing: 8, fontWeight: FontWeight.w600),
                          textAlign: _recovery ? TextAlign.start : TextAlign.center,
                          onChanged: (v) => !_recovery && v.length == 6 ? _submit() : null,
                          decoration: InputDecoration(labelText: _recovery ? l.recoveryCode : l.twoFactorCode, errorText: _fieldErrors['code']),
                          validator: (v) => (v == null || v.isEmpty) ? l.requiredField : null,
                        ),
                      const SizedBox(height: 20),
                      if (!twoFactor && (config?.googleEnabled ?? false)) ...[
                        GoogleButton(key: const Key('google'), onPressed: _busy ? null : () => _google(config!.googleClientId!)),
                        const SizedBox(height: 12),
                        Row(
                          children: [
                            const Expanded(child: Divider()),
                            Padding(
                              padding: const EdgeInsets.symmetric(horizontal: 12),
                              child: Text(l.orDivider, style: TextStyle(color: context.colors.onSurfaceVariant)),
                            ),
                            const Expanded(child: Divider()),
                          ],
                        ),
                        const SizedBox(height: 12),
                      ],
                      FilledButton(
                        key: const Key('submit'),
                        onPressed: _busy ? null : _submit,
                        child: _busy
                            ? const SizedBox.square(dimension: 20, child: CircularProgressIndicator(strokeWidth: 2))
                            : Text(twoFactor ? l.verify : l.signIn),
                      ),
                      const SizedBox(height: 8),
                      if (twoFactor)
                        TextButton(
                          onPressed: () => setState(() {
                            _recovery = !_recovery;
                            _code.clear();
                          }),
                          child: Text(_recovery ? l.useAuthenticatorCode : l.useRecoveryCode),
                        )
                      else
                        TextButton(
                          onPressed: () =>
                              openExternal(context, config?.link('forgot_password') ?? '${ref.read(apiProvider).siteUrl}${SignInScreen.forgotPasswordPath}'),
                          child: Text(l.forgotPassword),
                        ),
                      const SizedBox(height: 24),
                      Text(
                        l.newRestaurant,
                        textAlign: TextAlign.center,
                        style: TextStyle(fontSize: 13, color: context.colors.onSurfaceVariant),
                      ),
                      // Wraps: three links overflowed a 360 dp phone with large text.
                      Wrap(
                        alignment: WrapAlignment.center,
                        children: [
                          TextButton(
                            onPressed: () => openExternal(context, config?.link('privacy') ?? '${ref.read(apiProvider).siteUrl}privacy'),
                            child: Text(l.privacy),
                          ),
                          TextButton(
                            onPressed: () => openExternal(context, config?.link('terms') ?? '${ref.read(apiProvider).siteUrl}terms'),
                            child: Text(l.terms),
                          ),
                          TextButton(onPressed: () => context.push('/help'), child: Text(l.helpTitle)),
                        ],
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// Google's own look for its sign-in button: white (dark grey in dark mode), the four-colour G.
class GoogleButton extends StatelessWidget {
  const GoogleButton({super.key, required this.onPressed});
  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) {
    final dark = Theme.of(context).brightness == Brightness.dark;
    return OutlinedButton(
      onPressed: onPressed,
      style: OutlinedButton.styleFrom(
        minimumSize: const Size.fromHeight(48),
        backgroundColor: dark ? const Color(0xFF131314) : Colors.white,
        foregroundColor: dark ? const Color(0xFFE3E3E3) : const Color(0xFF1F1F1F),
        side: BorderSide(color: dark ? const Color(0xFF8E918F) : const Color(0xFF747775)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Image.asset('assets/images/google_g.png', width: 20, height: 20, excludeFromSemantics: true),
          const SizedBox(width: 12),
          Text(context.l.continueWithGoogle, style: const TextStyle(fontWeight: FontWeight.w500)),
        ],
      ),
    );
  }
}

/// A new e-mail from Google: the restaurant name and the Terms, then the app token.
class GoogleFinishSheet extends ConsumerStatefulWidget {
  const GoogleFinishSheet({super.key, required this.ticket, required this.email});
  final String ticket;
  final String email;
  @override
  ConsumerState<GoogleFinishSheet> createState() => _GoogleFinishSheetState();
}

class _GoogleFinishSheetState extends ConsumerState<GoogleFinishSheet> {
  final _form = GlobalKey<FormState>();
  final _name = TextEditingController();
  bool _terms = false;
  bool _termsError = false;
  bool _busy = false;
  String? _error;

  @override
  void dispose() {
    _name.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final ok = _form.currentState?.validate() ?? false;
    setState(() => _termsError = !_terms);
    if (!ok || !_terms) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final token = await ref.read(authRepositoryProvider).googleFinish(widget.ticket, _name.text, locale: Localizations.localeOf(context).languageCode);
      if (mounted) Navigator.pop(context, token);
    } catch (e) {
      if (mounted) setState(() => _error = errorText(context, e));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l;
    final config = ref.watch(appConfigProvider);
    final site = ref.read(apiProvider).siteUrl;
    return Padding(
      padding: EdgeInsets.fromLTRB(24, 0, 24, 24 + MediaQuery.viewInsetsOf(context).bottom),
      child: Form(
        key: _form,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(l.googleFinishTitle, style: context.text.titleLarge?.copyWith(fontWeight: FontWeight.w700)),
            const SizedBox(height: 6),
            Text(l.googleFinishBody(widget.email), style: TextStyle(color: context.colors.onSurfaceVariant)),
            const SizedBox(height: 20),
            if (_error != null) ...[
              Semantics(
                liveRegion: true,
                child: Text(_error!, style: TextStyle(color: context.colors.error)),
              ),
              const SizedBox(height: 12),
            ],
            TextFormField(
              key: const Key('restaurantName'),
              controller: _name,
              autofocus: true,
              maxLength: 120,
              textCapitalization: TextCapitalization.words,
              autofillHints: const [AutofillHints.organizationName],
              decoration: InputDecoration(labelText: l.restaurantName, counterText: ''),
              validator: (v) => (v == null || v.trim().length < 2) ? l.requiredField : null,
            ),
            const SizedBox(height: 8),
            CheckboxListTile(
              key: const Key('terms'),
              value: _terms,
              contentPadding: EdgeInsets.zero,
              controlAffinity: ListTileControlAffinity.leading,
              onChanged: (v) => setState(() => _terms = v ?? false),
              title: Text(l.acceptTerms),
              subtitle: _termsError ? Text(l.acceptTermsRequired, style: TextStyle(color: context.colors.error)) : null,
            ),
            Wrap(
              spacing: 8,
              children: [
                TextButton(onPressed: () => openExternal(context, config?.link('terms') ?? '${site}terms'), child: Text(l.terms)),
                TextButton(onPressed: () => openExternal(context, config?.link('privacy') ?? '${site}privacy'), child: Text(l.privacy)),
              ],
            ),
            const SizedBox(height: 12),
            FilledButton(
              key: const Key('createMenu'),
              onPressed: _busy ? null : _submit,
              child: _busy ? const SizedBox.square(dimension: 20, child: CircularProgressIndicator(strokeWidth: 2)) : Text(l.createMyFreeMenu),
            ),
          ],
        ),
      ),
    );
  }
}
