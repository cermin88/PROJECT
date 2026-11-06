<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@hasSection('title')@yield('title') · CSA Intelligence @else CSA Intelligence @endif</title>
<script>
(() => {
  const saved = localStorage.getItem('csa-theme');
  const theme = saved || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
  document.documentElement.dataset.theme = theme;
})();
</script>
<style>
:root{color-scheme:light;--bg:#f6f7f8;--surface:#fff;--surface-subtle:#f7f8f8;--surface-hover:#fafbfb;--ink:#202a33;--muted:#687580;--nav-link:#43515b;--line:#e3e7e9;--field-border:#cfd7da;--placeholder:#8a969c;--secondary-text:#3f4c55;--secondary-hover-border:#b9c4c7;--secondary-hover-bg:#f7f9f9;--brand:#17666a;--brand-hover:#105458;--brand-soft:#edf5f4;--ok:#28734d;--ok-bg:#f1f8f3;--ok-line:#b8d6c4;--warn:#966318;--bad:#a23d38;--bad-bg:#fcf4f3;--bad-line:#e5c4c1;--radius:6px;--focus:0 0 0 3px rgba(23,102,106,.18)}
:root{--grade-progress:#155cdb;--grade-progress-bg:#f5f8ff;--grade-positive:#16804a;--grade-positive-bg:#f1faf4;--grade-negative:#c43b13;--grade-negative-bg:#fff5f2;--grade-medium:#ad6900;--grade-medium-bg:#fff9ed}
:root[data-theme="dark"]{color-scheme:dark;--bg:#171b1d;--surface:#202628;--surface-subtle:#292f31;--surface-hover:#252c2e;--ink:#e6eceb;--muted:#a0aeac;--nav-link:#c1ccca;--line:#394345;--field-border:#4a5759;--placeholder:#899795;--secondary-text:#d0d9d7;--secondary-hover-border:#627170;--secondary-hover-bg:#2a3233;--brand:#68b7b0;--brand-hover:#82c9c1;--brand-soft:#263b39;--ok:#83c99d;--ok-bg:#20372a;--ok-line:#365d43;--warn:#e6bc70;--bad:#ed8f87;--bad-bg:#3c2827;--bad-line:#67413e;--focus:0 0 0 3px rgba(104,183,176,.28);--grade-progress:#9cbbff;--grade-progress-bg:#242f45;--grade-positive:#86d9a6;--grade-positive-bg:#23392c;--grade-negative:#ff9f82;--grade-negative-bg:#412d29;--grade-medium:#f1c36f;--grade-medium-bg:#3b3222}
*{box-sizing:border-box}
body{margin:0;min-height:100vh;display:flex;background:var(--bg);color:var(--ink);font:14px/1.5 Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif}
nav{position:sticky;top:0;flex:0 0 232px;width:232px;height:100vh;overflow-y:auto;padding:19px 12px;background:var(--surface);border-right:1px solid var(--line)}
.brand-lockup{display:flex;align-items:center;gap:9px;color:var(--ink);font-size:15px;font-weight:650;letter-spacing:-.025em}
.brand-mark{display:grid;width:34px;height:30px;place-items:center;border-radius:4px;background:var(--brand);color:#fff;font-size:10px;font-weight:750;letter-spacing:.045em}
nav h1{margin:0 8px 20px}
.login-brand{margin:0;font-size:22px}
.login-brand .brand-mark{width:42px;height:36px;font-size:12px}
nav a{display:block;margin:1px 0;padding:7px 10px 7px 18px;border-radius:4px;color:var(--nav-link);text-decoration:none;font-size:14px;line-height:1.4;transition:background .15s,color .15s}
nav>a{padding-left:10px;font-weight:600}
nav a:hover,nav a.on{background:var(--brand-soft);color:var(--brand)}
nav a.on{font-weight:650}
nav summary{display:flex;justify-content:space-between;align-items:center;list-style:none;cursor:pointer;padding:8px 9px;border-radius:4px;color:var(--muted);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.075em}
nav summary::-webkit-details-marker{display:none}
nav summary::after{content:"›";font-size:16px;font-weight:400;transition:transform .15s}
nav details[open]>summary::after{transform:rotate(90deg)}
nav summary:hover{background:var(--surface-subtle);color:var(--ink)}
nav details{margin-bottom:2px}
nav details[open]{margin-bottom:5px}
main{flex:1;min-width:0;padding:22px clamp(18px,3vw,40px) 40px}
.app-header{display:flex;justify-content:space-between;align-items:center;gap:16px;min-height:56px;margin:-22px calc(-1 * clamp(18px,3vw,40px)) 24px;padding:0 24px;border-bottom:1px solid var(--line);background:var(--surface)}
.page-context{min-width:0;overflow:hidden;color:#43515b;font-size:13px;font-weight:500;text-overflow:ellipsis;white-space:nowrap}
.header-actions{display:flex;align-items:center;gap:18px;flex:0 0 auto}
.header-button{display:inline-flex;align-items:center;gap:7px;min-height:34px;padding:6px 10px;border:1px solid var(--line);border-radius:6px;background:var(--surface);color:var(--secondary-text);font-size:12px;font-weight:600;text-decoration:none;white-space:nowrap}
.header-button:hover{border-color:var(--secondary-hover-border);background:var(--secondary-hover-bg);color:var(--ink)}
.header-button svg{width:16px;height:16px;flex:0 0 16px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.header-button .sparkle{color:#bd4cff;fill:currentColor;stroke:none}
.quick-create-button,.quick-create-button:hover{border-color:#17666a;background:#17666a;color:#fff}
.quick-create-button:hover{border-color:#105458;background:#105458}
.quick-create-button .sparkle{color:#fff}
.header-icon svg{width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.header-icon .sparkle{color:#bd4cff;fill:currentColor;stroke:none}
.notification-count{position:absolute;top:0;right:-5px;min-width:15px;height:15px;padding:0 3px;border-radius:8px;background:#2876f0;color:#fff;font-size:9px;line-height:15px;text-align:center}
.account-menu{position:relative}
.account-menu>summary{display:flex;align-items:center;gap:8px;max-width:260px;padding:4px 8px 4px 4px;border:1px solid #17666a;border-radius:8px;background:var(--surface);color:var(--ink);cursor:pointer;list-style:none}
.account-menu>summary::-webkit-details-marker{display:none}
.account-menu>summary:hover,.account-menu[open]>summary{box-shadow:0 0 0 2px rgba(23,102,106,.16)}
.account-avatar{display:grid;width:26px;height:26px;flex:0 0 26px;place-items:center;border-radius:50%;background:#17666a;color:#fff;font-size:12px;font-weight:700}
.account-name{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px;font-weight:600}
.account-chevron{color:var(--muted);font-size:12px}
.account-popover{position:absolute;z-index:20;top:calc(100% + 7px);right:0;width:min(280px,calc(100vw - 32px));padding:8px;background:var(--surface);border:1px solid var(--line);border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.14)}
.account-info{display:grid;gap:2px;padding:8px 9px 10px;border-bottom:1px solid var(--line)}
.account-info strong{color:var(--ink);font-size:12px}
.account-info span{overflow:hidden;color:var(--muted);font-size:11px;text-overflow:ellipsis;white-space:nowrap}
.account-links{display:grid;gap:4px;padding-top:7px}
.account-links>a,.account-links button{width:100%;padding:7px 9px;border:0;border-radius:6px;background:transparent;color:var(--secondary-text);font-size:12px;text-align:left;text-decoration:none}
.account-links .logout-button{color:#ed1b24}
.account-links>a:hover,.account-links button:hover{background:var(--surface-subtle);color:var(--ink)}
.profile-details{display:grid;gap:0;margin:0}
.profile-details>div{display:grid;grid-template-columns:150px 1fr;gap:12px;padding:10px 0;border-bottom:1px solid var(--line)}
.profile-details>div:last-child{border-bottom:0}
.profile-details dt{color:var(--muted);font-size:12px}
.profile-details dd{margin:0;font-weight:550}
h2{margin:0;color:var(--ink);font-size:21px;line-height:1.3;font-weight:650;letter-spacing:-.025em}
h3{margin:0 0 10px;font-size:14px;font-weight:650;letter-spacing:-.01em}
.card{margin-bottom:14px;padding:15px 16px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px;margin-bottom:14px}
.kpi{min-height:92px}
.kpi b{display:block;margin:6px 0 2px;font-size:21px;line-height:1.25;font-weight:650;letter-spacing:-.025em;font-variant-numeric:tabular-nums}
.kpi span{color:var(--muted);font-size:12px}
table{width:100%;border-collapse:collapse;font-size:13px}
th,td{padding:9px 10px;border-bottom:1px solid var(--line);text-align:left;vertical-align:top}
th{color:var(--muted);font-size:11px;font-weight:650;letter-spacing:.015em}
tbody tr:last-child td{border-bottom:0}
tr:hover td{background:var(--surface-hover)}
.wrap{overflow-x:auto}
.badge{display:inline-block;padding:2px 7px;border:1px solid var(--line);border-radius:4px;background:var(--surface-subtle);font-size:11px;line-height:1.45}
.grade-chip{display:inline-flex;align-items:center;min-height:28px;padding:4px 11px;border:1px solid var(--line);border-radius:999px;background:var(--surface-subtle);font-size:12px;font-weight:700;line-height:1.25;white-space:nowrap}
.grade-progress{color:var(--grade-progress);background:var(--grade-progress-bg)}
.grade-positive{color:var(--grade-positive);background:var(--grade-positive-bg)}
.grade-negative,.grade-high{color:var(--grade-negative);background:var(--grade-negative-bg)}
.grade-medium{color:var(--grade-medium);background:var(--grade-medium-bg)}
.grade-low{color:var(--grade-progress);background:var(--grade-progress-bg)}
.ok{color:var(--ok)}.warn{color:var(--warn)}.bad{color:var(--bad)}.mute{color:var(--muted)}
.flash{margin-bottom:14px;padding:10px 12px;border:1px solid;border-radius:var(--radius)}
.flash.ok{border-color:var(--ok-line);background:var(--ok-bg);color:var(--ok)}
.flash.err{border-color:var(--bad-line);background:var(--bad-bg);color:var(--bad)}
button,.btn{display:inline-block;padding:7px 11px;border:1px solid var(--brand);border-radius:var(--radius);background:var(--brand);color:#fff;cursor:pointer;font:inherit;font-size:12px;font-weight:600;line-height:1.35;text-align:center;text-decoration:none;transition:background .15s,border-color .15s,color .15s}
button:hover,.btn:hover{border-color:var(--brand-hover);background:var(--brand-hover);color:#fff}
button.alt,.btn.alt{border-color:var(--line);background:var(--surface);color:var(--secondary-text)}
button.alt:hover,.btn.alt:hover{border-color:var(--secondary-hover-border);background:var(--secondary-hover-bg);color:var(--ink)}
button.danger{border-color:var(--bad);background:var(--bad)}
input,select,textarea{max-width:100%;padding:7px 9px;border:1px solid var(--field-border);border-radius:var(--radius);background:var(--surface);color:var(--ink);font:inherit}
input::placeholder,textarea::placeholder{color:var(--placeholder)}
input:focus,select:focus,textarea:focus,button:focus-visible,a:focus-visible,summary:focus-visible{outline:0;box-shadow:var(--focus)}
form.inline{display:inline-flex;align-items:center;gap:6px;flex-wrap:wrap}
a{color:var(--brand)}
.cols{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,360px),1fr));gap:12px}
.board{display:flex;gap:10px;overflow-x:auto}.board>div{min-width:200px;flex:1}.board .card{margin-bottom:8px;padding:8px 10px}
.pager{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;padding:10px 4px 2px;font-size:12px}
.pager .pages{display:flex;gap:4px;flex-wrap:wrap}
.pager a,.pager span.cur,.pager span.off{padding:4px 9px;border:1px solid var(--line);border-radius:4px;text-decoration:none}
.pager span.cur{border-color:var(--brand);background:var(--brand);color:#fff}
.pager span.off{color:var(--muted);opacity:.6}
form.filters{margin-bottom:10px}
#quick-create-dialog{width:min(940px,calc(100% - 28px));max-width:940px;max-height:calc(100vh - 28px);padding:0;border:1px solid var(--line);border-radius:8px;background:var(--surface);color:var(--ink);box-shadow:0 20px 70px rgba(0,0,0,.28)}
#quick-create-dialog::backdrop{background:rgba(15,23,26,.58)}
.quick-create-modal{padding:22px}
.quick-create-heading{display:flex;justify-content:space-between;align-items:flex-start;gap:14px;margin-bottom:16px}
.quick-create-heading h2{margin:0}
.quick-create-heading p{margin:4px 0 0}
.quick-create-type{display:flex;align-items:center;gap:10px;margin-bottom:14px;font-weight:600}
.quick-create-type[hidden]{display:none}
.quick-create-type select{min-width:190px}
.quick-create-form[hidden]{display:none}
.quick-create-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.quick-create-form>label{display:flex;flex-direction:column;gap:5px;font-weight:600}
.quick-create-form input,.quick-create-form select,.quick-create-form textarea{width:100%;font-weight:400}
.quick-create-form fieldset{display:flex;flex-wrap:wrap;gap:4px 12px;border:1px solid var(--line);border-radius:var(--radius)}
.quick-create-form legend{padding:0 5px;color:var(--muted);font-size:12px}
.quick-create-form .quick-create-check{display:inline-flex;flex-direction:row;align-items:center;gap:5px;font-weight:400}
.quick-create-form .quick-create-check input{width:auto}
.quick-create-actions{grid-column:1/-1;display:flex;justify-content:flex-end;gap:8px;padding-top:4px}
@media(max-width:600px){.quick-create-modal{padding:16px}.quick-create-form{grid-template-columns:minmax(0,1fr)}.quick-create-actions{grid-column:auto}.quick-create-type{align-items:flex-start;flex-direction:column}}
@media(max-width:800px){body{flex-direction:column}nav{position:static;width:100%;height:auto;max-height:42vh;flex-basis:auto;border-right:0;border-bottom:1px solid var(--line);padding:12px}nav h1{margin:2px 8px 10px}main{padding:16px 16px 30px}.app-header{min-height:52px;margin:-16px -16px 18px;padding:0 16px}}
@media(max-width:480px){.grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.kpi{padding:12px}.kpi b{font-size:18px}.cols{grid-template-columns:minmax(0,1fr)}.header-actions{gap:9px}.account-menu>summary{max-width:190px}.account-name{max-width:115px}.profile-details>div{grid-template-columns:1fr;gap:3px}}
@media(prefers-reduced-motion:reduce){*,*::before,*::after{scroll-behavior:auto!important;transition:none!important}}
</style>
</head>
<body>
@auth
<nav>
  <h1 class="brand-lockup"><span class="brand-mark" aria-hidden="true">CSA</span><span>Intelligence</span></h1>
  @foreach(config('csa_nav') as $group => $items)
    @php
      // Only built pages the current role may see; the group holding the current page starts open
      $items = array_filter($items, fn ($i) => Route::has($i[0]) && (empty($i[3]) || in_array(auth()->user()->role, $i[3], true)));
      $open = collect($items)->contains(fn ($i) => request()->routeIs($i[0], \Illuminate\Support\Str::before($i[0], '.').'.*'));
    @endphp
    @if(count($items) === 1 && $group === 'Home')
      @foreach($items as [$name, $label])<a href="{{ route($name) }}" class="{{ request()->routeIs($name) ? 'on' : '' }}">{{ $label }}</a>@endforeach
    @elseif($items)
    <details @if($open) open @endif>
      <summary>{{ $group }}</summary>
      @foreach($items as [$name, $label, $ux])
        <a href="{{ route($name) }}" class="{{ request()->routeIs($name) ? 'on' : '' }}" title="{{ $ux }}">{{ $label }}</a>
      @endforeach
    </details>
    @endif
  @endforeach
</nav>
@endauth
<main>
@auth
<header class="app-header">
  @php
    $me = auth()->user();
    $myUnread = $me->unreadNotifications()->count();
  @endphp
  <div class="page-context">@yield('header-context', trim($__env->yieldContent('title')))</div>
  <div class="header-actions">
    <a class="header-button" href="{{ route('notifications.index') }}" title="Notifications{{ $myUnread ? " ({$myUnread} unread)" : '' }}">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/></svg>
      <span>Notifications</span>
      @if($myUnread)<b>{{ $myUnread > 9 ? '9+' : $myUnread }}</b>@endif
    </a>
    <button type="button" class="header-button quick-create-button" data-quick-create-open>
      <svg class="sparkle" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.5 14.7 9.3 21.5 12l-6.8 2.7L12 21.5l-2.7-6.8L2.5 12l6.8-2.7L12 2.5Z"/></svg>
      <span>Quick Create</span>
    </button>
    <details class="account-menu">
      <summary aria-label="Open account menu">
        <span class="account-avatar" aria-hidden="true">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($me->name, 0, 1)) }}</span>
        <span class="account-name">{{ $me->name }}</span>
        <span class="account-chevron" aria-hidden="true">⌄</span>
      </summary>
      <div class="account-popover">
        <div class="account-info">
          <strong>{{ $me->name }}</strong>
          <span>{{ $me->email }}</span>
        </div>
        <div class="account-links">
          <a href="{{ route('profile.show') }}">View profile</a>
          <button type="button" id="theme-toggle" aria-label="Switch to dark mode" aria-pressed="false">Dark mode</button>
          <form method="POST" action="{{ route('logout') }}">@csrf<button class="logout-button" type="submit">Logout</button></form>
        </div>
      </div>
    </details>
  </div>
</header>
@include('components.quick-create')
@endauth
@if(session('ok'))<div class="flash ok">{{ session('ok') }}</div>@endif
@if(session('err'))<div class="flash err">{{ session('err') }}</div>@endif
@if($errors->any())<div class="flash err">{{ $errors->first() }}</div>@endif
@yield('content')
</main>
<script>
(() => {
  const toggle = document.getElementById('theme-toggle');
  if (!toggle) return;

  const syncLabel = () => {
    const isDark = document.documentElement.dataset.theme === 'dark';
    toggle.textContent = isDark ? 'Light mode' : 'Dark mode';
    toggle.setAttribute('aria-label', `Switch to ${isDark ? 'light' : 'dark'} mode`);
    toggle.setAttribute('aria-pressed', String(isDark));
  };

  syncLabel();
  toggle.addEventListener('click', () => {
    const theme = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
    document.documentElement.dataset.theme = theme;
    localStorage.setItem('csa-theme', theme);
    syncLabel();
  });
})();
</script>
</body>
</html>
