<?php

namespace App\Http\Controllers\Platform;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Super Admin global search (owner, 2026-11-13): restaurants, owners,
 * invoices, payments and tags, at most 6 of each. Plain indexed queries
 * with LIMIT, so it stays fast with thousands of restaurants.
 */
class SearchController extends Controller
{
    private const LIMIT = 6;

    public function __invoke(Request $request): JsonResponse
    {
        $q = trim((string) $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']])['q']);
        $like = '%'.addcslashes($q, '%_\\').'%';
        $prefix = addcslashes($q, '%_\\').'%';

        $restaurants = DB::table('tenants')->whereNull('deleted_at')
            ->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('slug', 'like', $prefix))
            ->orderByRaw('slug like ? desc', [$prefix])->orderBy('name')->limit(self::LIMIT)
            ->get(['id', 'name', 'slug'])
            ->map(fn ($t) => ['id' => (int) $t->id, 'title' => $t->name, 'subtitle' => '/'.$t->slug, 'tenant_id' => (int) $t->id]);

        $owners = DB::table('users')->join('memberships', 'memberships.user_id', '=', 'users.id')
            ->join('tenants', 'tenants.id', '=', 'memberships.tenant_id')
            ->whereNull('tenants.deleted_at')->where('memberships.role', Role::Owner->value)
            ->where(fn ($w) => $w->where('users.email', 'like', $prefix)->orWhere('users.name', 'like', $like)->orWhere('users.email', 'like', $like))
            ->orderBy('users.name')->limit(self::LIMIT)
            ->get(['users.id', 'users.name', 'users.email', 'tenants.id as tenant_id', 'tenants.name as tenant'])
            ->map(fn ($u) => ['id' => (int) $u->id, 'title' => $u->name, 'subtitle' => $u->email.' · '.$u->tenant, 'tenant_id' => (int) $u->tenant_id]);

        $invoices = DB::table('invoices')->where('number', 'like', $like)->orderByDesc('id')->limit(self::LIMIT)
            ->get(['id', 'number', 'tenant_id', 'total_minor', 'currency', 'status'])
            ->map(fn ($i) => ['id' => (int) $i->id, 'title' => $i->number, 'subtitle' => $i->currency.' '.number_format($i->total_minor / 100).($i->status === 'void' ? ' · void' : ''), 'tenant_id' => $i->tenant_id ? (int) $i->tenant_id : null]);

        $payments = DB::table('payments')->join('tenants', 'tenants.id', '=', 'payments.tenant_id')
            ->where('payments.reference', 'like', $like)->orderByDesc('payments.paid_at')->limit(self::LIMIT)
            ->get(['payments.id', 'payments.reference', 'payments.paid_at', 'payments.amount_minor', 'payments.currency', 'tenants.id as tenant_id', 'tenants.name as tenant'])
            ->map(fn ($p) => ['id' => (int) $p->id, 'title' => (string) $p->reference, 'subtitle' => $p->tenant.' · '.$p->paid_at.' · '.$p->currency.' '.number_format($p->amount_minor / 100), 'tenant_id' => (int) $p->tenant_id]);

        $tags = DB::table('platform_tags')->where('name', 'like', $like)->orderBy('name')->limit(self::LIMIT)
            ->get(['id', 'name', 'color'])
            ->map(fn ($g) => ['id' => (int) $g->id, 'title' => $g->name, 'subtitle' => null, 'color' => $g->color, 'tenant_id' => null]);

        return response()->json(['data' => [
            'restaurants' => $restaurants->values(), 'owners' => $owners->values(), 'invoices' => $invoices->values(),
            'payments' => $payments->values(), 'tags' => $tags->values(),
        ]]);
    }
}
