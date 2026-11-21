<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\HelpArticle;
use App\Models\Tenant;
use App\Support\ActivityLogger;
use App\Support\Features;
use App\Support\Help\HelpLibrary;
use App\Support\Help\HelpRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Super Admin → Help articles: change any article (with preview), add new ones, see "Was this helpful?" (QUESTIONS #194). */
class HelpArticleController extends Controller
{
    public function __construct(private readonly ActivityLogger $activity) {}

    public function index(Request $request): JsonResponse
    {
        $audience = $this->audience($request->query('audience', 'restaurant'));
        $votes = DB::table('help_feedback')->where('audience', $audience)
            ->selectRaw('slug, SUM(helpful = 1) AS yes, SUM(helpful = 0) AS no')->groupBy('slug')->get()->keyBy('slug');
        $topics = (array) __('help.topics.'.$audience);
        $rows = [];
        foreach (HelpLibrary::all($audience) as $a) {
            $v = $votes[$a['slug']] ?? null;
            $rows[] = [
                'slug' => $a['slug'],
                'title' => $a['title'],
                'topic' => $a['topic'],
                'topic_title' => $topics[$a['topic']] ?? $a['topic'],
                'features' => $a['features'],
                'roles' => $a['roles'],
                'source' => $a['source'],
                'hidden' => (bool) $a['hidden'],
                'yes' => (int) ($v->yes ?? 0),
                'no' => (int) ($v->no ?? 0),
                'updated_at' => $a['updated_at'],
            ];
        }

        return response()->json(['data' => $rows, 'meta' => [
            'topics' => array_map(fn ($k, $t) => ['key' => $k, 'title' => $t], array_keys($topics), $topics),
            'features' => array_keys(Features::global()),
        ]]);
    }

    public function show(string $audience, string $slug): JsonResponse
    {
        $this->audience($audience);
        $a = HelpLibrary::all($audience)[$slug] ?? abort(404);

        return response()->json(['data' => [...$this->fields($a), 'source' => $a['source'], 'default' => isset(HelpLibrary::defaults($audience)[$slug]) ? $this->fields(HelpLibrary::defaults($audience)[$slug]) : null]]);
    }

    public function update(Request $request, string $audience, string $slug): JsonResponse
    {
        $this->audience($audience);
        abort_unless(preg_match('/^[a-z0-9][a-z0-9\-]{1,78}$/', $slug) === 1, 404);
        $features = array_keys(Features::global());
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'topic' => ['required', Rule::in(HelpLibrary::topics($audience))],
            'summary' => ['nullable', 'string', 'max:300'],
            'body' => ['required', 'string', 'max:60000'],
            'features' => ['sometimes', 'array'],
            'features.*' => ['string', Rule::in([...$features, ...array_map(fn ($f) => '!'.$f, $features)])],
            'roles' => ['sometimes', 'array'],
            'roles.*' => [Rule::in(HelpLibrary::ROLES)],
            'pages' => ['sometimes', 'array', 'max:20'],
            'pages.*' => ['string', 'max:120', 'regex:~^/[a-z0-9/\-:$#_]*$~'],
            'app' => ['sometimes', 'array', 'max:20'],
            'app.*' => ['string', 'max:120', 'regex:#^/[a-z0-9/\-:]*$#'],
            'keywords' => ['sometimes', 'array', 'max:30'],
            'keywords.*' => ['string', 'max:40'],
            'order' => ['sometimes', 'integer', 'min:0', 'max:999'],
            'hidden' => ['sometimes', 'boolean'],
        ]);
        $unknown = (new HelpRenderer)->unknown($data['body']);
        if ($unknown !== []) {
            return response()->json(['message' => __('help.unknown_placeholder', ['names' => implode(', ', $unknown)]), 'errors' => ['body' => [__('help.unknown_placeholder', ['names' => implode(', ', $unknown)])]]], 422);
        }
        $row = HelpArticle::updateOrCreate(['audience' => $audience, 'slug' => $slug], [
            'topic' => $data['topic'],
            'title' => $data['title'],
            'summary' => $data['summary'] ?? '',
            'body' => $data['body'],
            'features' => array_values($data['features'] ?? []),
            'roles' => array_values($data['roles'] ?? []),
            'pages' => array_values($data['pages'] ?? []),
            'app_pages' => array_values($data['app'] ?? []),
            'keywords' => array_values($data['keywords'] ?? []),
            'sort' => $data['order'] ?? 50,
            'is_hidden' => $data['hidden'] ?? false,
            'updated_by' => $request->user()->id,
        ]);
        $this->activity->log('platform.help_saved', $row, ['audience' => $audience, 'slug' => $slug]);

        return $this->show($audience, $slug);
    }

    /** A changed default goes back to the shipped text; an article of your own is deleted. */
    public function destroy(Request $request, string $audience, string $slug): Response
    {
        $this->audience($audience);
        $row = HelpArticle::where('audience', $audience)->where('slug', $slug)->firstOrFail();
        $row->delete();
        $this->activity->log('platform.help_reset', null, ['audience' => $audience, 'slug' => $slug]);

        return response()->noContent();
    }

    /** What a restaurant would see (any restaurant, to check feature blocks and live numbers). */
    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:60000'],
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'role' => ['nullable', Rule::in(HelpLibrary::ROLES)],
        ]);
        $render = new HelpRenderer(isset($data['tenant_id']) ? Tenant::find($data['tenant_id']) : null, $data['role'] ?? null);
        $body = $render->markdown($data['body']);

        return response()->json(['data' => ['body' => $body, 'html' => $render->html($body), 'unknown' => $render->unknown($data['body'])]]);
    }

    private function audience(mixed $audience): string
    {
        abort_unless(is_string($audience) && in_array($audience, HelpLibrary::AUDIENCES, true), 404);

        return $audience;
    }

    /**
     * @param  array<string, mixed>  $a
     * @return array<string, mixed>
     */
    private function fields(array $a): array
    {
        return [
            'slug' => $a['slug'], 'title' => $a['title'], 'topic' => $a['topic'], 'summary' => $a['summary'],
            'body' => $a['body'], 'features' => $a['features'], 'roles' => $a['roles'], 'pages' => $a['pages'],
            'app' => $a['app'], 'keywords' => $a['keywords'], 'order' => $a['order'], 'hidden' => (bool) $a['hidden'],
        ];
    }
}
