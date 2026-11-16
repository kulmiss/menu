<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Support\Help\HelpLibrary;
use App\Support\Help\HelpRenderer;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Help centre for readers (QUESTIONS #194): the web admin and the app load
 * every article they may read in one answer (the app keeps it for offline).
 */
class HelpController extends Controller
{
    /** Restaurant team: articles for this restaurant's features and the person's role. */
    public function restaurant(Request $request, CurrentTenant $current): JsonResponse|Response
    {
        $tenant = $current->get();
        $role = $current->membership()?->role->value ?? 'owner';

        return $this->answer($request, 'restaurant', $tenant, $role);
    }

    /** Super Admin guide inside the panel. */
    public function platform(Request $request): JsonResponse|Response
    {
        return $this->answer($request, 'platform', null, null);
    }

    /** Signed out (sign-in problems): only articles marked `public`. */
    public function public(Request $request): JsonResponse|Response
    {
        return $this->answer($request, 'restaurant', null, null, publicOnly: true);
    }

    public function feedback(Request $request): Response
    {
        $data = $request->validate([
            'audience' => ['required', Rule::in(HelpLibrary::AUDIENCES)],
            'slug' => ['required', 'string', 'max:80'],
            'helpful' => ['required', 'boolean'],
        ]);
        abort_unless(array_key_exists($data['slug'], HelpLibrary::all($data['audience'])), 404);
        abort_if($data['audience'] === 'platform' && ! $request->user()->is_super_admin, 403);
        DB::table('help_feedback')->upsert(
            [['audience' => $data['audience'], 'slug' => $data['slug'], 'user_id' => $request->user()->id, 'helpful' => (bool) $data['helpful'], 'created_at' => now(), 'updated_at' => now()]],
            ['audience', 'slug', 'user_id'],
            ['helpful', 'updated_at'],
        );

        return response()->noContent();
    }

    private function answer(Request $request, string $audience, ?Tenant $tenant, ?string $role, bool $publicOnly = false): JsonResponse|Response
    {
        $render = new HelpRenderer($tenant, $role);
        $topics = (array) __('help.topics.'.$audience);
        $voted = $request->user()
            ? DB::table('help_feedback')->where('audience', $audience)->where('user_id', $request->user()->id)->pluck('helpful', 'slug')->map(fn ($v) => (bool) $v)->all()
            : [];
        $articles = [];
        foreach (HelpLibrary::visible($audience, $tenant, $role, $publicOnly) as $a) {
            $body = $render->markdown((string) $a['body']);
            $articles[] = [
                'slug' => $a['slug'],
                'topic' => $a['topic'],
                'title' => $a['title'],
                'summary' => trim($render->markdown((string) $a['summary'])),
                'pages' => $a['pages'],
                'app' => $a['app'],
                'keywords' => $a['keywords'],
                'body' => $body,
                'html' => $render->html($body),
                'updated_at' => $a['updated_at'],
            ];
        }
        $used = array_unique(array_column($articles, 'topic'));
        $data = [
            'topics' => array_values(array_map(fn ($k) => ['key' => $k, 'title' => $topics[$k]], array_filter(array_keys($topics), fn ($k) => in_array($k, $used, true)))),
            'articles' => $articles,
            'voted' => (object) $voted,
        ];
        $etag = '"'.md5((string) json_encode($data)).'"';
        if ($request->header('If-None-Match') === $etag) {
            return response()->noContent(304)->setEtag(trim($etag, '"'));
        }

        return response()->json(['data' => $data])->setEtag(trim($etag, '"'))->header('Cache-Control', 'private, no-cache');
    }
}
