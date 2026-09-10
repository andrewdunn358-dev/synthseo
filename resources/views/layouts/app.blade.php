<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>@yield('title', 'SynthSEO')</title>
@yield('head')
<link rel="icon" type="image/png" href="{{ asset('assets/synthseo-favicon.png') }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600&family=IBM+Plex+Mono:wght@400;600&display=swap" rel="stylesheet">
<style>
  :root{
    /* Surfaces - three levels of depth, not one flat panel colour
       repeated everywhere. Base sits on ink; raised sits on base. */
    --ink:#0B0F12;
    --panel:#131A1E;
    --panel-raised:#1B2329;
    --paper:#EEF1F0;
    --grey:#8B979A;
    --grey-dim:#59656A;

    /* Brand - the burnt-orange from the logo and marketing site.
       Reserved for actions and identity, not reused as a status
       colour (see below) so "click this" and "this needs review"
       never look like the same signal. */
    --brand:#E1691F;
    --brand-bright:#F0813C;
    /* Back-compat aliases - earlier pages were built against these
       names before the token system got a proper rename. Remove once
       every view has been migrated off them. */
    --lime:var(--brand);
    --lime-dim:var(--brand-bright);

    /* Status - used for audit findings and site health only.
       Deliberately distinct from --brand so a warning chip is never
       mistaken for a call to action. */
    --good:#4FD69C;
    --fair:#F0A63E;
    --poor:#F0645A;

    --border: rgba(238,241,240,0.08);
    --border-strong: rgba(238,241,240,0.16);

    /* Type scale - Space Grotesk carries headings and big numbers,
       Inter carries body copy, IBM Plex Mono carries data/meta -
       three roles, not three arbitrary sizes per page. */
    --fs-2xs: 11.5px;
    --fs-xs: 12.5px;
    --fs-sm: 14px;
    --fs-base: 15.5px;
    --fs-md: 17px;
    --fs-lg: 22px;
    --fs-xl: 30px;
    --fs-2xl: 42px;

    --sp-1: 4px; --sp-2: 8px; --sp-3: 12px; --sp-4: 16px;
    --sp-5: 24px; --sp-6: 32px; --sp-7: 48px; --sp-8: 64px;

    --radius: 10px;
    --radius-sm: 7px;
  }
  *{box-sizing:border-box;}
  body{
    margin:0; background:var(--ink); color:var(--paper);
    font-family:'Inter',sans-serif; font-size:var(--fs-base); line-height:1.6;
    -webkit-font-smoothing:antialiased; min-height:100vh;
  }
  h1,h2,h3{ font-family:'Space Grotesk',sans-serif; font-weight:700; margin:0; }
  h1{ font-size:var(--fs-xl); }
  a{ color:inherit; }
  .mono{ font-family:'IBM Plex Mono',monospace; }
  .muted{ color:var(--grey); }
  .wrap{ max-width:1160px; margin:0 auto; padding:0 32px; }
  header{ padding:18px 0; border-bottom:1px solid var(--border); }
  header .wrap{ display:flex; justify-content:space-between; align-items:center; }
  .brand-logo{ height:44px; display:block; }
  .top-nav{ display:flex; gap:26px; align-items:center; font-size:var(--fs-sm); color:var(--grey); }
  .top-nav a{ text-decoration:none; }
  .top-nav a:hover{ color:var(--paper); }
  .linklike{ background:none; border:0; color:var(--grey); font:inherit; font-size:var(--fs-sm);
             cursor:pointer; padding:0; }
  .linklike:hover{ color:var(--paper); }

  /* ---- Shared components every page can use without redefining ---- */

  .card{ background:var(--panel); border:1px solid var(--border);
         border-radius:var(--radius); padding:var(--sp-5); margin-top:var(--sp-5); }
  /* Reserved for the one or two things on a page that should read as
     more important than a plain list - a hero stat, a primary insight -
     not applied to every panel identically. */
  .card--raised{ background:var(--panel-raised); border-color:var(--border-strong); }
  .card--accent{ border-left:3px solid var(--brand); }

  .btn{ display:inline-block; border:1px solid var(--border-strong); background:transparent;
        color:var(--paper); padding:10px 18px; border-radius:var(--radius-sm);
        font:inherit; font-size:var(--fs-sm); font-weight:600; cursor:pointer; text-decoration:none; }
  .btn:hover{ border-color:var(--brand); }
  .btn-primary{ background:var(--brand); border-color:var(--brand); color:#fff; }
  .btn-primary:hover{ background:var(--brand-bright); border-color:var(--brand-bright); }

  .badge{ display:inline-flex; align-items:center; gap:6px; font-size:var(--fs-xs); font-weight:600;
          padding:5px 11px; border-radius:20px; white-space:nowrap; }
  .badge.good{ background:rgba(79,214,156,.14); color:var(--good); }
  .badge.fair{ background:rgba(240,166,62,.16); color:var(--fair); }
  .badge.poor{ background:rgba(240,100,90,.16); color:var(--poor); }
  .badge.unknown{ background:rgba(238,241,240,.07); color:var(--grey); }

  .notice{ background:rgba(225,105,31,.10); border:1px solid rgba(225,105,31,.28);
           padding:13px 16px; border-radius:var(--radius-sm); margin-top:var(--sp-5); font-size:var(--fs-sm); }
  .flash{ background:rgba(79,214,156,.10); border:1px solid rgba(79,214,156,.28);
          padding:11px 15px; border-radius:var(--radius-sm); margin-top:var(--sp-5); font-size:var(--fs-sm); }

  .waiting{ display:flex; align-items:center; gap:13px; }
  .spinner{ width:16px; height:16px; flex:0 0 16px; border-radius:50%;
    border:2px solid rgba(225,105,31,.25); border-top-color:var(--brand);
    animation:spin .9s linear infinite; }
  @keyframes spin{ to{ transform:rotate(360deg); } }
  @media (prefers-reduced-motion: reduce){ .spinner{ animation:none; } }

  /* ---- Gauge: a real circular indicator, not a number in a box.
     Used for both Google's Lighthouse scores and our own on-page
     check results. For Lighthouse, the fill is the score itself. For
     our own checks, the fill is the honest pass-ratio (passes divided
     by total checks) - a proportion actually observed, never an
     invented weighting. The number shown underneath is always a plain
     count ("7 to fix"), not a score, for the same reason. ---- */
  .gauges{ display:flex; gap:var(--sp-5); flex-wrap:wrap; }
  .gauge-card{ display:flex; flex-direction:column; align-items:center; gap:var(--sp-2);
               flex:1 1 130px; min-width:120px; }
  .gauge{ position:relative; width:104px; height:104px; }
  .gauge svg{ width:100%; height:100%; transform:rotate(-90deg); }
  .gauge-track{ fill:none; stroke:var(--border-strong); stroke-width:9; }
  .gauge-fill{ fill:none; stroke-width:9; stroke-linecap:round;
               transition:stroke-dashoffset .6s cubic-bezier(.4,0,.2,1); }
  .gauge-fill.good{ stroke:var(--good); }
  .gauge-fill.fair{ stroke:var(--fair); }
  .gauge-fill.poor{ stroke:var(--poor); }
  .gauge-fill.unknown{ stroke:var(--grey-dim); }
  .gauge-value{ position:absolute; inset:0; display:flex; align-items:center; justify-content:center;
                font-family:'Space Grotesk',sans-serif; font-weight:700; font-size:26px; }
  .gauge-label{ font-size:var(--fs-sm); color:var(--grey); text-align:center; }
  .gauge-sub{ font-size:var(--fs-2xs); color:var(--grey-dim); text-align:center; margin-top:-4px; }

  @yield('styles')
</style>
</head>
<body>
<header>
  <div class="wrap">
    <a href="/"><img class="brand-logo" src="{{ asset('assets/synthseo-logo.png') }}" alt="SynthSEO"></a>
    @auth
    <nav class="top-nav">
      <a href="/dashboard">Sites</a>
      <form method="POST" action="/logout" style="margin:0">
        @csrf
        <button type="submit" class="linklike">Log out</button>
      </form>
    </nav>
    @endauth
  </div>
</header>
<main>
@yield('content')
</main>
</body>
</html>
