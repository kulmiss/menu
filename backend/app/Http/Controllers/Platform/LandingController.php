<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Controllers\LandingClickController;
use App\Support\ActivityLogger;
use App\Support\ImageProcessor;
use App\Support\ImageTooLarge;
use App\Support\Landing\LandingImages;
use App\Support\Landing\LandingTexts;
use App\Support\PlatformSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Super Admin → Landing page (owner, 2026-11-17): every text, the pictures,
 * which sections show and in which order, and the button clicks. Colour,
 * WhatsApp number, testimonials and footer links are saved with the other
 * settings (PUT /platform/settings).
 */
class LandingController extends Controller
{
    public function __construct(private readonly ActivityLogger $activity) {}

    public function show(): JsonResponse
    {
        $sections = PlatformSettings::DEFAULTS['landing_sections'];
        $saved = PlatformSettings::get('landing_sections');
        foreach (is_array($saved) ? $saved : [] as $key => $on) {
            if (array_key_exists($key, $sections)) {
                $sections[$key] = (bool) $on;
            }
        }
        $known = PlatformSettings::DEFAULTS['landing_order'];
        $order = PlatformSettings::get('landing_order');
        $order = array_values(array_unique(array_filter(is_array($order) ? $order : [], fn ($k) => is_string($k) && in_array($k, $known, true))));
        $defaults = LandingTexts::defaults();

        $images = [];
        foreach (array_keys(LandingImages::SLOTS) as $slot) {
            $image = $slot === 'og' ? LandingImages::og() : LandingImages::phone($slot);
            $images[$slot] = ['url' => $image['url'] ?? $image['src'], 'custom' => $image['custom']];
        }

        return response()->json(['data' => [
            'url' => url('/'),
            'texts' => (object) LandingTexts::overrides(),
            'defaults' => $defaults,
            'max' => array_map(fn (string $k) => LandingTexts::max($k), array_combine(array_keys($defaults), array_keys($defaults))),
            'sections' => $sections,
            'order' => [...$order, ...array_values(array_diff($known, $order))],
            'images' => $images,
            'clicks' => LandingClickController::report(30),
        ]]);
    }

    public function update(Request $request): JsonResponse
    {
        $known = PlatformSettings::DEFAULTS['landing_order'];
        $data = $request->validate([
            'texts' => ['sometimes', 'array'],
            'texts.*' => ['nullable', 'string', 'max:400'],
            'sections' => ['sometimes', 'array'],
            'sections.*' => ['boolean'],
            'order' => ['sometimes', 'array', 'max:'.count($known)],
            'order.*' => ['string', 'distinct', Rule::in($known)],
        ]);

        $save = [];
        if (isset($data['texts'])) {
            $errors = [];
            foreach ($data['texts'] as $key => $text) {
                if (array_key_exists($key, LandingTexts::defaults()) && mb_strlen(trim((string) $text)) > LandingTexts::max($key)) {
                    $errors["texts.{$key}"] = __('validation.max.string', ['attribute' => $key, 'max' => LandingTexts::max($key)]);
                }
            }
            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }
            $save['landing_texts'] = LandingTexts::clean($data['texts']);
            // The first version's headline and subline now live with the other texts.
            $save['landing_headline'] = '';
            $save['landing_subline'] = '';
        }
        if (isset($data['sections'])) {
            $save['landing_sections'] = array_map('boolval', array_intersect_key($data['sections'], PlatformSettings::DEFAULTS['landing_sections']) + PlatformSettings::DEFAULTS['landing_sections']);
        }
        if (isset($data['order'])) {
            $save['landing_order'] = [...$data['order'], ...array_values(array_diff($known, $data['order']))];
        }
        PlatformSettings::save($save);
        $this->activity->log('platform.landing_saved', null, ['texts' => ['to' => count($save['landing_texts'] ?? LandingTexts::overrides())]], null);

        return $this->show();
    }

    public function saveImage(Request $request, string $slot, ImageProcessor $images): JsonResponse
    {
        abort_unless(array_key_exists($slot, LandingImages::SLOTS), 404);
        $size = $slot === 'og' ? 'min_width=600,min_height=315' : 'min_width=300,min_height=500';
        $request->validate(['image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240', "dimensions:{$size}"]]);
        try {
            LandingImages::store($slot, $request->file('image'), $images);
        } catch (ImageTooLarge) {
            throw ValidationException::withMessages(['image' => __('menu.media.too_many_pixels')]);
        }
        $this->activity->log('platform.landing_image_saved', null, ['picture' => ['to' => $slot]], null);

        return $this->show();
    }

    public function deleteImage(string $slot): JsonResponse
    {
        abort_unless(array_key_exists($slot, LandingImages::SLOTS), 404);
        LandingImages::forget($slot);
        $this->activity->log('platform.landing_image_removed', null, ['picture' => ['to' => $slot]], null);

        return $this->show();
    }
}
