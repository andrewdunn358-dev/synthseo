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
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;650;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<style>
  :root{
    /*
     | Redesign pass, based on external design review (see commit
     | message). Three surface levels, solid colours rather than
     | translucent-white overlays on black - a considered dark palette
     | reads as designed, a black background with white-at-8%-opacity
     | borders reads as a default someone didn't get around to
     | finishing.
     */
    --ink:#080c11;
    --panel:#0d131a;
    --panel-raised:#121a23;
    --paper:#f1f5f9;
    --grey:#9aa8b6;
    --grey-dim:#647382;

    /* Brand - the burnt-orange from the logo and marketing site,
       kept deliberately (see commit message: the external review's
       own advice was that colour isn't the main lever here, hierarchy
       and restraint are - so brand consistency with the logo wins).
       Reserved for actions and identity, never reused as a status
       colour, so "click this" and "this needs review" are never the
       same signal. */
    --brand:#E1691F;
    --brand-bright:#F0813C;
    /* Back-compat aliases from an earlier token rename - remove once
       every view is confirmed off them. */
    --lime:var(--brand);
    --lime-dim:var(--brand-bright);

    --good:#32d583;
    --fair:#f2b84b;
    --poor:#f0645b;

    --border:#202b36;
    --border-strong:#304050;

    /*
     | Type scale, sized per the external review's table. Same
     | variable names as before so every existing usage site picks up
     | the new sizes without individually touching each one - only the
     | values changed, not what they mean.
     */
    --fs-2xs: 10px;   /* tiny metadata */
    --fs-xs: 11px;    /* labels */
    --fs-sm: 13px;    /* secondary body */
    --fs-base: 14px;  /* body */
    --fs-md: 15px;    /* card/feature heading */
    --fs-lg: 18px;    /* section heading */
    --fs-xl: 28px;    /* page title */
    --fs-2xl: 28px;   /* kept equal to page title - nothing in this app needs larger */
    --fs-metric: 26px; /* large standalone numbers - gauge values, hero stats */
    --fs-mono: 12px;  /* technical data - URLs, timestamps, raw values */

    --sp-1: 4px; --sp-2: 8px; --sp-3: 12px; --sp-4: 16px;
    --sp-5: 24px; --sp-6: 32px; --sp-7: 48px; --sp-8: 64px;

    /* One radius, not two - excessive or inconsistent rounding is
       one of the concrete things flagged as reading as undesigned. */
    --radius: 7px;
    --radius-sm: 7px;
  }
  *{box-sizing:border-box;}
  body{
    margin:0; background:var(--ink); color:var(--paper);
    font-family:'Inter',sans-serif; font-size:var(--fs-base); line-height:1.6;
    -webkit-font-smoothing:antialiased; min-height:100vh;
  }
  /* Inter throughout, not a separate display face for headings - one
     well-chosen typeface varying by size/weight reads as more
     considered than a second "display font" bolted on, and matches
     the Linear/Vercel-style reference directly asked for. IBM Plex
     Mono is reserved for genuinely technical data (see .mono) only. */
  h1,h2,h3{ font-family:'Inter',sans-serif; font-weight:650; margin:0; letter-spacing:-0.01em; }
  h1{ font-size:var(--fs-xl); }
  a{ color:inherit; }
  .mono{ font-family:'IBM Plex Mono',monospace; font-size:var(--fs-mono); }
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
     not applied to every panel identically. Most content should not
     need a card at all; a simple section with a heading is enough
     unless a boundary genuinely helps. */
  .card--raised{ background:var(--panel-raised); border-color:var(--border-strong); }
  .card--accent{ border-left:3px solid var(--brand); }

  .section{ margin-top:var(--sp-7); }
  .section-head{ display:flex; align-items:center; justify-content:space-between; gap:16px;
                 flex-wrap:wrap; margin-bottom:var(--sp-3); }
  .subhead{ font-size:var(--fs-lg); font-weight:650; letter-spacing:-0.01em; }

  .btn{ display:inline-block; border:1px solid var(--border-strong); background:transparent;
        color:var(--paper); padding:10px 18px; border-radius:var(--radius-sm);
        font:inherit; font-size:var(--fs-sm); font-weight:600; cursor:pointer; text-decoration:none;
        transition:background .12s ease, border-color .12s ease, transform .08s ease; }
  .btn:hover{ border-color:var(--brand); }
  .btn:active{ transform:translateY(1px); }
  .btn-primary{ background:var(--brand); border-color:var(--brand); color:#fff; }
  .btn-primary:hover{ background:var(--brand-bright); border-color:var(--brand-bright); }

  /* Pills represent STATE (Completed, Ahead, Failed), never
     individual content - turning every list item into a colourful
     pill is one of the concrete things flagged as reading as
     generated rather than designed. */
  .badge{ display:inline-flex; align-items:center; gap:6px; font-size:var(--fs-xs); font-weight:600;
          padding:4px 10px; border-radius:20px; white-space:nowrap; }
  .badge.good{ background:rgba(50,213,131,.14); color:var(--good); }
  .badge.fair{ background:rgba(242,184,75,.16); color:var(--fair); }
  .badge.poor{ background:rgba(240,100,91,.16); color:var(--poor); }
  .badge.unknown{ background:rgba(241,245,249,.07); color:var(--grey); }

  .notice{ background:rgba(225,105,31,.10); border:1px solid rgba(225,105,31,.28);
           padding:13px 16px; border-radius:var(--radius-sm); margin-top:var(--sp-5); font-size:var(--fs-sm); }
  .flash{ background:rgba(50,213,131,.10); border:1px solid rgba(50,213,131,.28);
          padding:11px 15px; border-radius:var(--radius-sm); margin-top:var(--sp-5); font-size:var(--fs-sm); }

  .waiting{ display:flex; align-items:center; gap:13px; }
  .spinner{ width:16px; height:16px; flex:0 0 16px; border-radius:50%;
    border:2px solid rgba(225,105,31,.25); border-top-color:var(--brand);
    animation:spin .9s linear infinite; }
  @keyframes spin{ to{ transform:rotate(360deg); } }
  @media (prefers-reduced-motion: reduce){ .spinner{ animation:none; } }

  /* Application data as rows with dividers, not a card per item -
     the other concrete thing flagged as reading as undesigned. Every
     list in this app (audit history, content drafts, comparisons,
     social posts, newsletters, sites) uses this same row instead of
     each page redefining its own near-identical version. */
  .row{ display:flex; align-items:center; justify-content:space-between; gap:16px;
        padding:16px 2px; border-bottom:1px solid var(--border); flex-wrap:wrap;
        text-decoration:none; color:inherit; min-height:64px;
        transition:background .14s ease, transform .14s ease; }
  .row:last-child{ border-bottom:0; }
  a.row:hover{ background:var(--panel); transform:translateX(3px); }
  .row .rtitle{ font-weight:600; font-size:var(--fs-md); }
  .row .rmeta{ font-size:var(--fs-xs); color:var(--grey); margin-top:2px; }

  /* Small status dot used anywhere a compact list of pass/fail/warn
     items needs a state indicator without wrapping every item in a
     coloured pill - the audit page's own findings list, and the
     shorter "your own audit" summary on the competitor page. */
  .check-dot{ width:8px; height:8px; border-radius:50%; margin-top:7px; flex-shrink:0; display:inline-block; }
  .check-dot.fail{ background:var(--poor); }
  .check-dot.warn{ background:var(--fair); }
  .check-dot.pass{ background:var(--good); }

  /* Subtle entrance for content that just finished generating (an
     audit completing, a draft appearing) - not decorative motion on
     things that were already there. Reduced-motion users get none. */
  .reveal{ animation:revealIn .22s ease both; }
  @keyframes revealIn{ from{ opacity:0; transform:translateY(5px); } to{ opacity:1; transform:none; } }
  @media (prefers-reduced-motion: reduce){ .reveal{ animation:none; } }

  /* ---- Gauge: a real circular indicator, not a number in a box.
     Used for both Google's Lighthouse scores and our own on-page
     check results. For Lighthouse, the fill is the score itself. For
     our own checks, the fill is the honest pass-ratio (passes divided
     by total checks) - a proportion actually observed, never an
     invented weighting. The number shown underneath is always a plain
     count ("7 to fix"), not a score, for the same reason. Kept
     deliberately (see commit message) rather than replaced with plain
     numbers - this is the one place the product should still look and
     feel like the PageSpeed-style reference it was built to match. ---- */
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
                font-family:'Inter',sans-serif; font-weight:650; font-size:var(--fs-metric); }
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
      @if (auth()->user()->canManageTeam())
        <a href="/team">Team</a>
      @endif
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
