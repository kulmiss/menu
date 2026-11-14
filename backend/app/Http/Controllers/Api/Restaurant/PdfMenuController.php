<?php

namespace App\Http\Controllers\Api\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Tenant;
use App\Models\TenantAppearance;
use App\Support\PublicMenu\MenuPageData;
use App\Support\PublicMenu\PdfMenu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/** Admin → "Download PDF menu": a printable A4 menu with the chosen options. */
class PdfMenuController extends Controller
{
    public function download(Request $request, Tenant $tenant, MenuPageData $data): Response
    {
        Gate::authorize('restaurant.manage');
        $options = $request->validate([
            'menu_id' => ['nullable', 'integer'],
            'branch_id' => ['nullable', 'integer'],
            'photos' => ['sometimes', 'boolean'],
            'columns' => ['sometimes', 'integer', 'in:1,2'],
        ]);
        $branch = isset($options['branch_id']) ? Branch::where('is_active', true)->findOrFail($options['branch_id']) : null;
        $appearance = TenantAppearance::current();
        // "With photos" in the dialog decides, whatever the menu page shows.
        $appearance->show_photos = $request->boolean('photos');
        $page = $data->build($tenant, $appearance, $branch, periods: false);

        $pdf = (new PdfMenu)->render($page, [
            'menu_id' => isset($options['menu_id']) ? (int) $options['menu_id'] : null,
            'photos' => $request->boolean('photos'),
            'columns' => (int) ($options['columns'] ?? 1),
        ]);
        $name = Str::slug($tenant->name.($branch && ! $branch->is_default ? ' '.$branch->slug : '')).'-menu.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
            'Cache-Control' => 'no-store',
        ]);
    }
}
