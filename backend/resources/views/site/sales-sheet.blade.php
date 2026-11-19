@php($wa = $contact['whatsapp'] ? preg_replace('#^https://wa.me/#', '+', $contact['whatsapp']) : null)
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>{{ __('launch.title', ['name' => $platform]) }}</title>
<style>
@page{size:A4;margin:0}
*{box-sizing:border-box}
html{color-scheme:light}
body{margin:0;background:#e5e7eb;font-family:ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,"Noto Sans","Noto Sans Ethiopic",Arial,sans-serif;color:#0f172a;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.page{width:210mm;height:297mm;margin:16px auto;background:#fff;padding:12mm 13mm 10mm;display:flex;flex-direction:column;gap:5mm;overflow:hidden;box-shadow:0 4px 24px #0002}
@media print{body{background:#fff}.page{margin:0;box-shadow:none}.hint{display:none}}
.hint{text-align:center;color:#475569;font-size:14px;margin:12px}
header{display:flex;align-items:center;gap:4mm}
header img{width:14mm;height:14mm;border-radius:3mm;object-fit:cover}
header b{font-size:20pt;letter-spacing:-.01em}
.hero{display:grid;grid-template-columns:1fr 40mm;gap:7mm;align-items:center;padding:6mm 7mm;border-radius:6mm;background:{{ $colors['brand'] }};color:{{ $colors['on_brand'] }}}
.eyebrow{font-size:10pt;font-weight:700;text-transform:uppercase;letter-spacing:.08em;opacity:.85}
h1{margin:1.5mm 0 2.5mm;font-size:24pt;line-height:1.1;letter-spacing:-.02em}
.hero p{margin:0;font-size:11.5pt;line-height:1.45}
.qr{background:#fff;border-radius:4mm;padding:3mm;text-align:center;color:#0f172a}
.qr svg{display:block;width:100%;height:auto}
.qr span{display:block;font-size:8.5pt;font-weight:700;margin-top:1.5mm;line-height:1.25}
h2{margin:0 0 2.5mm;font-size:13pt}
.benefits{display:grid;grid-template-columns:1fr 1fr 1fr;gap:3mm}
.benefits div{border:1px solid #e4e7ec;border-radius:3mm;padding:2.5mm 3mm}
.benefits b{display:block;font-size:10.5pt;margin-bottom:1mm}
.benefits span{font-size:9pt;color:#475569;line-height:1.35}
.steps{display:grid;grid-template-columns:repeat(3,1fr);gap:3mm;counter-reset:s;margin:0;padding:0;list-style:none}
.steps li{counter-increment:s;display:flex;gap:2.5mm;align-items:flex-start;font-size:10pt;line-height:1.35}
.steps li:before{content:counter(s);flex:none;width:7mm;height:7mm;border-radius:50%;background:{{ $colors['ink'] }};color:#fff;font-weight:800;display:flex;align-items:center;justify-content:center;font-size:10pt}
.plans{display:grid;grid-template-columns:repeat({{ max(1, min(4, count($plans))) }},1fr);gap:3mm}
.plan{border:1px solid #e4e7ec;border-radius:3mm;padding:3.5mm}
.plan.hl{border:2px solid {{ $colors['ink'] }}}
.plan b{font-size:11pt}
.price{font-size:17pt;font-weight:800;margin:1mm 0 0;white-space:nowrap}
.per{font-size:8.5pt;color:#475569}
.plan ul{margin:2mm 0 0;padding:0 0 0 4mm;font-size:8.5pt;line-height:1.35;color:#334155}
.note{font-size:9pt;color:#475569;margin-top:2mm}
footer{margin-top:auto;border-top:1px solid #e4e7ec;padding-top:3mm;font-size:10pt}
footer b{font-size:11.5pt}
footer dl{display:flex;flex-wrap:wrap;gap:1mm 7mm;margin:1.5mm 0 0}footer dl div{display:flex;gap:2mm}
footer dt{color:#475569}
footer dd{margin:0;font-weight:700}
</style>
</head>
<body>
<p class="hint">{{ __('launch.print_hint') }}</p>
<main class="page">
    <header>
        @if ($logo)<img src="{{ $logo }}" alt="">@endif
        <b>{{ $platform }}</b>
    </header>
    <section class="hero">
        <div>
            <div class="eyebrow">{{ __('launch.eyebrow') }}</div>
            <h1>{{ __('launch.headline') }}</h1>
            <p>{{ __('launch.subline') }}</p>
        </div>
        <div class="qr">
            <svg viewBox="0 0 {{ $qr['size'] }} {{ $qr['size'] }}" role="img" aria-label="{{ __('launch.demo') }}" shape-rendering="crispEdges"><path fill="#0b0f19" d="{{ $qr['path'] }}"/></svg>
            <span>{{ __('launch.demo') }}</span>
        </div>
    </section>
    <section>
        <h2>{{ __('launch.why') }}</h2>
        <div class="benefits">
            @foreach (__('launch.benefits') as $i => [$title, $text])
                <div><b>{{ $i === 5 ? str_replace('17', (string) $styles, $title) : $title }}</b><span>{{ $text }}</span></div>
            @endforeach
        </div>
    </section>
    <section>
        <h2>{{ __('launch.steps_title') }}</h2>
        <ol class="steps">
            @foreach (__('launch.steps') as $step)
                <li>{{ str_replace(':url', $site, $step) }}</li>
            @endforeach
        </ol>
    </section>
    @if ($plans)
        <section>
            <h2>{{ __('launch.plans') }}</h2>
            <div class="plans">
                @foreach (array_slice($plans, 0, 4) as $p)
                    <div class="plan{{ $p['highlight'] ? ' hl' : '' }}">
                        <b>{{ $p['name'] }}</b>
                        <div class="price">{{ $p['price'] }}</div>
                        <div class="per">{{ $p['per'] }}</div>
                        <ul>
                            @if ($p['everything'])<li>{{ $p['everything'] }}</li>@endif
                            @foreach (array_slice([...$p['limits'], ...$p['features']], 0, $p['everything'] ? 4 : 5) as $line)<li>{{ $line }}</li>@endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
            <p class="note">{{ collect($plans)->contains('free', true) ? __('launch.free_note').' ' : '' }}{{ __('launch.pay_note') }}</p>
        </section>
    @endif
    <footer>
        <div>
            <b>{{ __('launch.contact') }}</b>
            <dl>
                <div><dt>{{ __('launch.web') }}</dt><dd>{{ $site }}</dd></div>
                @if ($wa)<div><dt>{{ __('launch.whatsapp') }}</dt><dd>{{ $wa }}</dd></div>@endif
                @if ($contact['email'])<div><dt>{{ __('launch.email') }}</dt><dd>{{ $contact['email'] }}</dd></div>@endif
            </dl>
        </div>
    </footer>
</main>
</body>
</html>
