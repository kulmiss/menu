<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\FooterVersion;
use App\Support\ActivityLogger;
use App\Support\BrandColor;
use App\Support\PlatformSettings;
use App\Support\Site\Footer;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Super Admin → Footer (owner, 2026-11-18, QUESTIONS #230): edit the website
 * footer as a draft, preview it (phone, computer, light, dark), publish it,
 * and restore any earlier version in one click. Every change is in the
 * activity log.
 */
class FooterController extends Controller
{
    public function __construct(private readonly ActivityLogger $activity) {}

    public function show(): JsonResponse
    {
        return response()->json(['data' => $this->payload()]);
    }

    /** Saves the draft (guests keep seeing the live footer until "Publish"). */
    public function update(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->rules(), $this->messages());
        // A link that is on needs a page or an address (a switched-off one may wait for its address).
        $validator->after(function ($v) use ($request) {
            foreach ((array) $request->input('columns', []) as $c => $column) {
                foreach ((array) ($column['links'] ?? []) as $l => $link) {
                    if (is_array($link) && ($link['on'] ?? false) === true && empty($link['page']) && trim((string) ($link['url'] ?? '')) === '') {
                        $v->errors()->add("columns.{$c}.links.{$l}.url", __('platform.footer.link_needed'));
                    }
                }
            }
        });
        $data = $validator->validate();
        $draft = Footer::normalize($data);
        PlatformSettings::save(['footer_draft' => $draft]);
        $this->activity->log('footer.draft_saved', null, ['columns' => ['to' => count($draft['columns'])]], null);

        return response()->json(['data' => $this->payload()]);
    }

    public function publish(Request $request): JsonResponse
    {
        if (! is_array(PlatformSettings::get('footer_draft'))) {
            throw ValidationException::withMessages(['draft' => __('platform.footer.nothing_to_publish')]);
        }
        $version = FooterVersion::create(['content' => Footer::draft(), 'published_by' => $request->user()->id]);
        PlatformSettings::save(['footer_draft' => null]);
        Footer::forget();
        $this->activity->log('footer.published', $version, null, null);

        return response()->json(['data' => $this->payload()]);
    }

    /** Throws the draft away: the editor shows the live footer again. */
    public function discard(): JsonResponse
    {
        PlatformSettings::save(['footer_draft' => null]);
        $this->activity->log('footer.draft_discarded', null, null, null);

        return response()->json(['data' => $this->payload()]);
    }

    /** One click: an earlier version is live again (as a new version, so nothing is lost). */
    public function restore(Request $request, FooterVersion $version): JsonResponse
    {
        $copy = FooterVersion::create(['content' => Footer::normalize($version->content), 'published_by' => $request->user()->id, 'restored_from' => $version->id]);
        PlatformSettings::save(['footer_draft' => null]);
        Footer::forget();
        $this->activity->log('footer.restored', $copy, ['from' => ['to' => $version->id]], null);

        return response()->json(['data' => $this->payload()]);
    }

    /**
     * The footer alone on a page, for the editor's preview: the draft (or the
     * live one), light or dark whatever the computer is set to.
     */
    public function preview(Request $request): Response
    {
        $q = $request->validate(['scheme' => ['sometimes', Rule::in(['light', 'dark'])], 'source' => ['sometimes', Rule::in(['draft', 'live'])]]);
        $content = ($q['source'] ?? 'draft') === 'live' ? Footer::published() : Footer::draft();
        $html = view('site.footer-preview', [
            'footer' => Footer::view($content),
            'dark' => ($q['scheme'] ?? 'light') === 'dark',
            'colors' => BrandColor::palette(PlatformSettings::get('brand_color')),
        ])->render();

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-store',
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-Robots-Tag' => 'noindex',
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        $versions = FooterVersion::with('publisher:id,name')->latest('id')->limit(30)->get();

        return [
            'draft' => Footer::draft(),
            'live' => Footer::published(),
            'changed' => Footer::hasChanges(),
            'pages' => collect(Footer::PAGES)->map(fn (string $path, string $key) => ['key' => $key, 'label' => __("footer.pages.{$key}"), 'path' => $path])->values(),
            'available' => array_keys(Footer::availablePages()),
            'socials' => Footer::SOCIALS,
            'limits' => ['columns' => Footer::MAX_COLUMNS, 'links' => Footer::MAX_LINKS],
            'versions' => $versions->map(fn (FooterVersion $v) => [
                'id' => $v->id,
                'at' => $v->created_at->toIso8601String(),
                'by' => $v->publisher?->name,
                'restored_from' => $v->restored_from,
                'live' => $v->id === $versions->first()?->id,
            ])->values(),
        ];
    }

    /** @return array<string, mixed> */
    private function rules(): array
    {
        $safe = function (string $attribute, mixed $value, Closure $fail) {
            if (is_string($value) && $value !== '' && Footer::safeUrl($value) === null) {
                $fail(__('platform.footer.unsafe_link'));
            }
        };
        $social = function (string $attribute, mixed $value, Closure $fail) {
            if (is_string($value) && trim($value) !== '' && ! Footer::isSocialUrl($value)) {
                $fail(__('platform.footer.social_link'));
            }
        };
        $play = function (string $attribute, mixed $value, Closure $fail) {
            if (is_string($value) && trim($value) !== '' && ! Footer::isPlayUrl($value)) {
                $fail(__('platform.footer.play_link'));
            }
        };
        $id = ['required', 'string', 'regex:/^[A-Za-z0-9_-]{1,40}$/'];

        return [
            'illustration' => ['required', 'boolean'],
            'columns' => ['present', 'array', 'max:'.Footer::MAX_COLUMNS],
            'columns.*.id' => $id,
            'columns.*.title' => ['required', 'string', 'max:60'],
            'columns.*.on' => ['required', 'boolean'],
            'columns.*.links' => ['present', 'array', 'max:'.Footer::MAX_LINKS],
            'columns.*.links.*.id' => $id,
            'columns.*.links.*.label' => ['required', 'string', 'max:60'],
            'columns.*.links.*.page' => ['nullable', 'string', Rule::in(array_keys(Footer::PAGES))],
            'columns.*.links.*.url' => ['nullable', 'string', 'max:500', $safe],
            'columns.*.links.*.on' => ['required', 'boolean'],
            'contact' => ['required', 'array'],
            'contact.on' => ['required', 'boolean'],
            'contact.title' => ['nullable', 'string', 'max:60'],
            'contact.whatsapp' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9][0-9 ()-]{5,28}$/'],
            'contact.phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9][0-9 ()-]{2,28}$/'],
            'contact.email' => ['nullable', 'email', 'max:120'],
            'contact.address' => ['nullable', 'string', 'max:160'],
            'contact.hours' => ['nullable', 'string', 'max:120'],
            'socials' => ['required', 'array'],
            ...collect(Footer::SOCIALS)->mapWithKeys(fn (string $n) => ["socials.{$n}" => ['nullable', 'string', 'max:255', $social]])->all(),
            'app' => ['required', 'array'],
            'app.on' => ['required', 'boolean'],
            'app.play_url' => ['nullable', 'string', 'max:255', $play],
            'trust' => ['required', 'array'],
            'trust.on' => ['required', 'boolean'],
            'trust.text' => ['nullable', 'string', 'max:120', 'required_if_accepted:trust.on'],
            'bottom' => ['required', 'array'],
            'bottom.company' => ['nullable', 'string', 'max:80'],
            'bottom.made_in' => ['nullable', 'string', 'max:80'],
            'bottom.back_to_top' => ['required', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    private function messages(): array
    {
        return [
            'contact.whatsapp.regex' => __('platform.footer.phone_format'),
            'contact.phone.regex' => __('platform.footer.phone_format'),
            'columns.max' => __('platform.footer.too_many_columns', ['max' => Footer::MAX_COLUMNS]),
            'columns.*.links.max' => __('platform.footer.too_many_links', ['max' => Footer::MAX_LINKS]),
        ];
    }
}
