{{-- The website footer's CSS (QUESTIONS #230), for the <head> of every site page that shows it.
     It uses the page's own colours (--bg, --fg, --muted, --line, --card, --soft, --brand, --on-brand, --ink). --}}
<style>
.sf{margin-top:auto;border-top:1px solid var(--line);background:var(--soft);color:var(--fg);font-size:.95rem;line-height:1.55}
.sf a{color:inherit}
.sf-art{display:block;width:100%;height:72px;color:var(--ink);opacity:.55;margin-bottom:-8px}
.sf-art svg{display:block;width:100%;height:100%}
.sf-in{width:100%;max-width:1200px;margin:0 auto;padding:40px 20px 0}
@media (min-width:768px){.sf-in{padding:48px 32px 0}}
.sf-grid{display:grid;gap:28px}
@media (min-width:960px){.sf-grid{grid-template-columns:minmax(16rem,1.1fr) 2.9fr;gap:48px}}
.sf-brand{display:inline-flex;align-items:center;gap:10px;min-height:44px;font-weight:800;font-size:1.15rem;text-decoration:none}
.sf-brand img{height:32px;width:auto}
.sf-mark{width:32px;height:32px;border-radius:9px;background:var(--brand);color:var(--on-brand);display:grid;place-items:center;font-size:1rem}
.sf-trust{margin:8px 0 0;color:var(--muted)}
.sf h2{margin:0;line-height:1.3;font-size:.8rem;font-weight:750;letter-spacing:.08em;text-transform:uppercase;color:var(--muted)}
.sf-contact{margin-top:20px;display:grid;gap:4px}
.sf-contact h2{margin-bottom:6px}
.sf-wa{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:48px;padding:0 20px;margin:2px 0 8px;border-radius:999px;background:var(--brand);color:var(--on-brand)!important;font-weight:700;text-decoration:none;justify-self:start}
.sf-ci{margin:0;display:flex;align-items:center;gap:10px;min-height:44px;text-decoration:none;overflow-wrap:anywhere}
a.sf-ci:hover{text-decoration:underline;text-underline-offset:3px}
.sf-ci svg,.sf-wa svg{flex:none;width:20px;height:20px}
.sf-ci svg{color:var(--ink)}
.sf-cols{display:grid;gap:0}
@media (min-width:640px){.sf-cols{grid-template-columns:repeat(2,minmax(0,1fr));gap:8px 32px}}
@media (min-width:1040px){.sf-cols{grid-template-columns:repeat(var(--sf-n,4),minmax(0,1fr))}}
.sf-col{margin:0;border-bottom:1px solid var(--line)}
.sf-col summary{color:inherit;font-weight:inherit;display:flex;align-items:center;justify-content:space-between;gap:12px;min-height:52px;cursor:pointer;list-style:none}
.sf-col summary::-webkit-details-marker{display:none}
.sf-col summary svg{width:18px;height:18px;color:var(--muted);transition:transform .2s}
.sf-col[open] summary svg{transform:rotate(180deg)}
.sf-col ul{list-style:none;margin:0;padding:0 0 12px}
.sf-col li a{display:inline-flex;align-items:center;gap:6px;min-height:44px;text-decoration:none}
.sf-col li a:hover{text-decoration:underline;text-underline-offset:3px}
.sf-col li svg{width:14px;height:14px;color:var(--muted)}
/* Wide screens: plain columns, always open (the summary is just the heading). */
@media (min-width:640px){
.sf-col{border:0}
.sf-col summary{min-height:auto;margin-bottom:8px;cursor:default;pointer-events:none}
.sf-col summary svg{display:none}
.sf-col::details-content{content-visibility:visible;display:block}
}
.sf-social{display:flex;flex-wrap:wrap;gap:8px;margin:24px 0 0;padding:0;list-style:none}
.sf .sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
.sf-social a{display:inline-grid;place-items:center;width:44px;height:44px;border-radius:999px;border:1px solid var(--line);background:var(--card);color:var(--fg)}
.sf-social a:hover{border-color:var(--muted)}
.sf-social svg{width:20px;height:20px}
.sf-app{display:inline-block;margin-top:16px;line-height:0}
.sf-app img{height:56px;width:auto}
.sf-bar{display:flex;flex-wrap:wrap;align-items:center;gap:8px 20px;margin-top:32px;padding:16px 0 calc(20px + env(safe-area-inset-bottom));border-top:1px solid var(--line);color:var(--muted);font-size:.9rem}
.sf-bar .sf-mark{width:24px;height:24px;border-radius:7px;font-size:.8rem}
.sf-bar img{height:24px;width:auto}
.sf-bar .sf-up{margin-inline-start:auto}
.sf-up{display:inline-flex;align-items:center;gap:6px;min-height:44px;padding:0 14px;border-radius:999px;border:1px solid var(--line);background:var(--card);color:var(--fg)!important;font-weight:600;text-decoration:none}
.sf-up svg{width:16px;height:16px}
.sf a:focus-visible,.sf summary:focus-visible{outline:3px solid var(--ink);outline-offset:3px;border-radius:6px}
@media (prefers-reduced-motion:reduce){.sf-col summary svg{transition:none}}
</style>
