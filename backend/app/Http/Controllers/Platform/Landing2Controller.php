<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\LandingRestaurant;
use App\Support\ActivityLogger;
use App\Support\ImageProcessor;
use App\Support\ImageTooLarge;
use App\Support\Landing\Landing2;
use App\Support\Landing\Landing2Texts;
use App\Support\PlatformSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Super Admin → Landing 2 (owner, 2026-11-22): an ON/OFF switch for every part, every text in
 * English and Amharic, the real restaurants, the payment methods, the real numbers, the demo
 * video, and Amharic for the footer texts typed in Content → Footer.
 */
class Landing2Controller extends Controller
{
    public function __construct(private readonly ActivityLogger $activity) {}

    /** The demo video: an MP4 (H.264) of at most 2 MB. */
    public const VIDEO_MAX_KB = 2048;

    public function show(): JsonResponse
    {
        $counted = PlatformSettings::get('landing2_numbers_counted');

        return response()->json(['data' => [
            'urls' => ['en' => url('/landing2'), 'am' => url('/am/landing2')],
            'is_home' => PlatformSettings::get('home_design') === 'landing2',
            'sections' => Landing2::sections(),
            'texts' => ['en' => (object) Landing2Texts::overrides('en'), 'am' => (object) Landing2Texts::overrides('am')],
            'defaults' => ['en' => Landing2Texts::defaults('en'), 'am' => Landing2Texts::defaults('am')],
            // English texts Landing 1 also has: a change there shows here until changed here.
            'shared' => array_values(array_filter(array_keys(Landing2Texts::defaults('en')), Landing2Texts::isShared(...))),
            'max' => array_map(Landing2Texts::max(...), array_combine(array_keys(Landing2Texts::defaults('en')), array_keys(Landing2Texts::defaults('en')))),
            'payments' => Landing2::payments(),
            'payment_icons' => Landing2::PAYMENT_ICONS,
            'numbers' => Landing2::numbersConfig(),
            'counted' => is_array($counted) ? $counted : null,
            'stale' => Landing2::staleNumbers(),
            'stale_days' => Landing2::STALE_DAYS,
            'today' => now()->toDateString(),
            'stale_before' => now()->subDays(Landing2::STALE_DAYS)->toDateString(),
            'restaurants' => LandingRestaurant::query()->orderBy('sort')->orderBy('id')->get()->map->toAdmin()->all(),
            'min_restaurants' => Landing2::MIN_RESTAURANTS,
            'video' => Landing2::video(),
            'footer' => ['texts' => Landing2::footerTexts(), 'am' => (object) $this->footerAm(), 'defaults' => (object) $this->footerDefaults()],
        ]]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sections' => ['sometimes', 'array'],
            'sections.*' => ['boolean'],
            'texts' => ['sometimes', 'array'],
            'texts.en' => ['sometimes', 'array'],
            'texts.am' => ['sometimes', 'array'],
            'texts.*.*' => ['nullable', 'string', 'max:400'],
            'payments' => ['sometimes', 'array', 'max:8'],
            'payments.*.label_en' => ['required', 'string', 'max:40'],
            'payments.*.label_am' => ['nullable', 'string', 'max:40'],
            'payments.*.icon' => ['required', Rule::in(Landing2::PAYMENT_ICONS)],
            'payments.*.on' => ['boolean'],
            'numbers' => ['sometimes', 'array'],
            'numbers.own' => ['sometimes', 'array'],
            'numbers.own.*.on' => ['boolean'],
            'numbers.own.*.min' => ['integer', 'min:0', 'max:100000000'],
            'numbers.outside' => ['sometimes', 'array', 'max:6'],
            'numbers.outside.*.value' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'numbers.outside.*.label_en' => ['required', 'string', 'max:60'],
            'numbers.outside.*.label_am' => ['nullable', 'string', 'max:60'],
            'numbers.outside.*.source' => ['required', 'string', 'max:80'],
            'numbers.outside.*.date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'numbers.outside.*.on' => ['boolean'],
            'numbers.outside.*.min' => ['integer', 'min:0', 'max:1000000000'],
            'footer_am' => ['sometimes', 'array', 'max:80'],
            'footer_am.*' => ['nullable', 'string', 'max:120'],
        ]);

        $save = [];
        $changes = [];
        if (isset($data['sections'])) {
            $save['landing2_sections'] = array_map('boolval', array_intersect_key($data['sections'], array_flip(Landing2::SECTIONS)) + Landing2::sections());
            $changes['sections'] = ['to' => count(array_filter($save['landing2_sections']))];
        }
        if (isset($data['texts'])) {
            $errors = [];
            $texts = PlatformSettings::get('landing2_texts');
            $texts = is_array($texts) ? $texts : [];
            foreach (Landing2Texts::LOCALES as $locale) {
                if (! isset($data['texts'][$locale])) {
                    continue;
                }
                foreach ($data['texts'][$locale] as $key => $text) {
                    if (array_key_exists($key, Landing2Texts::defaults()) && mb_strlen(trim((string) $text)) > Landing2Texts::max($key)) {
                        $errors["texts.{$locale}.{$key}"] = __('validation.max.string', ['attribute' => $key, 'max' => Landing2Texts::max($key)]);
                    }
                }
                $texts[$locale] = Landing2Texts::clean($locale, $data['texts'][$locale]);
            }
            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }
            $save['landing2_texts'] = ['en' => $texts['en'] ?? [], 'am' => $texts['am'] ?? []];
            $changes['texts'] = ['to' => count($save['landing2_texts']['en']) + count($save['landing2_texts']['am'])];
        }
        if (isset($data['payments'])) {
            $save['landing2_payments'] = array_values(array_map(fn (array $p) => [
                'label_en' => trim($p['label_en']), 'label_am' => trim((string) ($p['label_am'] ?? '')), 'icon' => $p['icon'], 'on' => (bool) ($p['on'] ?? true),
            ], $data['payments']));
            $changes['payments'] = ['to' => count($save['landing2_payments'])];
        }
        if (isset($data['numbers'])) {
            $current = Landing2::numbersConfig();
            $own = $current['own'];
            foreach ($data['numbers']['own'] ?? [] as $key => $n) {
                if (isset($own[$key])) {
                    $own[$key] = ['on' => (bool) ($n['on'] ?? $own[$key]['on']), 'min' => (int) ($n['min'] ?? $own[$key]['min'])];
                }
            }
            $outside = isset($data['numbers']['outside']) ? array_values(array_map(fn (array $n) => [
                'value' => (int) $n['value'], 'label_en' => trim($n['label_en']), 'label_am' => trim((string) ($n['label_am'] ?? '')),
                'source' => trim($n['source']), 'date' => $n['date'], 'on' => (bool) ($n['on'] ?? true), 'min' => (int) ($n['min'] ?? 0),
            ], $data['numbers']['outside'])) : $current['outside'];
            $save['landing2_numbers'] = ['own' => $own, 'outside' => $outside];
            $changes['numbers'] = ['to' => count($outside)];
        }
        if (isset($data['footer_am'])) {
            $known = array_flip(Landing2::footerTexts());
            $defaults = $this->footerDefaults();
            $map = [];
            foreach ($data['footer_am'] as $from => $to) {
                $to = trim((string) $to);
                if (isset($known[$from]) && $to !== '' && $to !== ($defaults[$from] ?? null)) {
                    $map[$from] = $to;
                }
            }
            $save['landing2_footer_am'] = $map;
            $changes['footer'] = ['to' => count($map)];
        }
        PlatformSettings::save($save);
        $this->activity->log('platform.landing2_saved', null, $changes, null);

        return $this->show();
    }

    public function storeRestaurant(Request $request): JsonResponse
    {
        $r = LandingRestaurant::create($this->restaurantData($request) + ['sort' => (int) LandingRestaurant::max('sort') + 1]);
        $this->activity->log('platform.landing2_restaurant_added', null, ['name' => ['to' => $r->name]], null);

        return $this->show();
    }

    public function updateRestaurant(Request $request, LandingRestaurant $restaurant): JsonResponse
    {
        $restaurant->update($this->restaurantData($request) + $request->validate(['sort' => ['sometimes', 'integer', 'min:0', 'max:10000']]));
        $this->activity->log('platform.landing2_restaurant_saved', null, ['name' => ['to' => $restaurant->name]], null);

        return $this->show();
    }

    public function destroyRestaurant(LandingRestaurant $restaurant): JsonResponse
    {
        $this->deleteLogo($restaurant);
        $restaurant->delete();
        $this->activity->log('platform.landing2_restaurant_removed', null, ['name' => ['from' => $restaurant->name]], null);

        return $this->show();
    }

    public function saveLogo(Request $request, LandingRestaurant $restaurant, ImageProcessor $images): JsonResponse
    {
        $request->validate(['image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=64,min_height=64']]);
        $file = $request->file('image');
        assert($file instanceof UploadedFile);
        $folder = LandingRestaurant::LOGO_FOLDER.'/'.$restaurant->id.'-'.Str::lower(Str::random(8));
        try {
            foreach ([64, 128] as $size) {
                $images->coverWebp((string) $file->getRealPath(), (string) $file->getMimeType(), 'public', "{$folder}/{$size}.webp", $size, $size, 80);
            }
        } catch (ImageTooLarge) {
            Storage::disk('public')->deleteDirectory($folder);
            throw ValidationException::withMessages(['image' => __('menu.media.too_many_pixels')]);
        }
        $this->deleteLogo($restaurant);
        $restaurant->forceFill(['logo' => $folder])->save();

        return $this->show();
    }

    public function removeLogo(LandingRestaurant $restaurant): JsonResponse
    {
        $this->deleteLogo($restaurant);
        $restaurant->forceFill(['logo' => null])->save();

        return $this->show();
    }

    /**
     * The demo video (Super Admin replaces the built-in one): MP4 at most 2 MB, its first
     * picture (made 960 × 540 WebP), and captions in English and Amharic (WebVTT).
     */
    public function saveVideo(Request $request, ImageProcessor $images): JsonResponse
    {
        $request->validate([
            'video' => ['required', 'file', 'mimetypes:video/mp4', 'max:'.self::VIDEO_MAX_KB],
            'poster' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=640,min_height=360'],
            'captions_en' => ['nullable', 'file', 'max:64'],
            'captions_am' => ['nullable', 'file', 'max:64'],
        ]);
        $captions = [];
        foreach (['en', 'am'] as $l) {
            $file = $request->file("captions_{$l}");
            if ($file instanceof UploadedFile) {
                $text = (string) file_get_contents((string) $file->getRealPath());
                if (! str_starts_with(ltrim($text, "\u{FEFF}"), 'WEBVTT') || ! mb_check_encoding($text, 'UTF-8')) {
                    throw ValidationException::withMessages(["captions_{$l}" => __('platform.landing2.vtt')]);
                }
                $captions[$l] = $text;
            }
        }
        $video = $request->file('video');
        $poster = $request->file('poster');
        assert($video instanceof UploadedFile && $poster instanceof UploadedFile);
        // An MP4 file starts with an "ftyp" box (the browser's type is only a claim).
        $head = (string) file_get_contents((string) $video->getRealPath(), false, null, 0, 12);
        if (substr($head, 4, 4) !== 'ftyp') {
            throw ValidationException::withMessages(['video' => __('platform.landing2.mp4')]);
        }
        $disk = Storage::disk('public');
        $folder = 'landing/video-'.Str::lower(Str::random(10));
        try {
            $images->coverWebp((string) $poster->getRealPath(), (string) $poster->getMimeType(), 'public', "{$folder}/poster.webp", 960, 540, 70);
        } catch (ImageTooLarge) {
            throw ValidationException::withMessages(['poster' => __('menu.media.too_many_pixels')]);
        }
        $disk->putFileAs($folder, $video, 'video.mp4');
        foreach ($captions as $l => $text) {
            $disk->put("{$folder}/{$l}.vtt", $text);
        }
        $this->forgetVideo();
        PlatformSettings::save(['landing2_video' => ['folder' => $folder]]);
        $this->activity->log('platform.landing2_video_saved', null, ['video' => ['to' => 'custom']], null);

        return $this->show();
    }

    public function deleteVideo(): JsonResponse
    {
        $this->forgetVideo();
        PlatformSettings::save(['landing2_video' => null]);
        $this->activity->log('platform.landing2_video_removed', null, ['video' => ['to' => 'built-in']], null);

        return $this->show();
    }

    /** @return array<string, mixed> */
    private function restaurantData(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'city' => ['nullable', 'string', 'max:60'],
            'quote_en' => ['nullable', 'string', 'max:300'],
            'quote_am' => ['nullable', 'string', 'max:300'],
            'permission' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ]);

        return array_map(fn ($v) => is_string($v) ? (trim($v) === '' ? null : trim($v)) : $v, $data);
    }

    private function deleteLogo(LandingRestaurant $restaurant): void
    {
        if ($restaurant->logo !== null && str_starts_with($restaurant->logo, LandingRestaurant::LOGO_FOLDER.'/')) {
            Storage::disk('public')->deleteDirectory($restaurant->logo);
        }
    }

    private function forgetVideo(): void
    {
        $old = PlatformSettings::get('landing2_video');
        if (is_array($old) && is_string($old['folder'] ?? null) && str_starts_with($old['folder'], 'landing/video-')) {
            Storage::disk('public')->deleteDirectory($old['folder']);
        }
    }

    /** @return array<string, string> the Super Admin's Amharic for footer texts */
    private function footerAm(): array
    {
        $saved = PlatformSettings::get('landing2_footer_am');

        return is_array($saved) ? array_filter($saved, fn ($v, $k) => is_string($k) && is_string($v), ARRAY_FILTER_USE_BOTH) : [];
    }

    /** @return array<string, string> built-in Amharic for the built-in footer texts */
    private function footerDefaults(): array
    {
        return array_intersect_key(Landing2::footerMap(false), array_flip(Landing2::footerTexts()));
    }
}
