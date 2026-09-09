<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>@yield('title', 'SynthSEO')</title>
<link rel="icon" type="image/png" href="{{ asset('assets/synthseo-favicon.png') }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600&family=IBM+Plex+Mono:wght@400;600&display=swap" rel="stylesheet">
<style>
  :root{
    --ink:#0E1316;
    --panel:#161D21;
    --paper:#EEF1F0;
    --grey:#8B979A;
    --grey-dim:#59656A;
    --lime:#E1691F;
    --lime-dim:#B8501A;
    --border: rgba(238,241,240,0.09);
    --border-strong: rgba(238,241,240,0.16);
  }
  *{box-sizing:border-box;}
  body{
    margin:0; background:var(--ink); color:var(--paper);
    font-family:'Inter',sans-serif; font-size:16px; line-height:1.6;
    -webkit-font-smoothing:antialiased; min-height:100vh;
  }
  h1{ font-family:'Space Grotesk',sans-serif; font-weight:700; margin:0; }
  a{ color:inherit; }
  .wrap{ max-width:1160px; margin:0 auto; padding:0 32px; }
  header{ padding:18px 0; border-bottom:1px solid var(--border); }
  .brand-logo{ height:44px; display:block; }
  @yield('styles')
</style>
</head>
<body>
<header>
  <div class="wrap">
    <a href="/"><img class="brand-logo" src="{{ asset('assets/synthseo-logo.png') }}" alt="SynthSEO"></a>
  </div>
</header>
<main>
@yield('content')
</main>
</body>
</html>
