@extends('layouts.app')
@section('title', $site->name . ' — SynthSEO')

{{-- Same rule as the audit page: refresh only while something is
     actually pending, and stop the moment it isn't. Content pending
     counts too, so a generating draft also keeps this page live. --}}
@if ($audits->contains(fn ($a) => in_array($a->status, ['queued', 'running'])) || $content->contains(fn ($c) => $c->isPending()) || $competitors->contains(fn ($c) => $c->isPending()) || $socialPosts->contains(fn ($p) => $p->isPending()) || $newsletters->contains(fn ($n) => $n->isPending()))
  @section('head')
    <meta http-equiv="refresh" content="5">
  @endsection
@endif

@section('styles')
  .wrap-pad{ padding:var(--sp-7) 32px 80px; }
  .row-top{ display:flex; align-items:flex-start; justify-content:space-between; gap:20px; flex-wrap:wrap; margin-top:var(--sp-3); }
  a.back{ color:var(--grey); text-decoration:none; font-size:var(--fs-sm); }
  a.back:hover{ color:var(--paper); }
  .url{ color:var(--grey-dim); font-family:'IBM Plex Mono',monospace; font-size:var(--fs-xs); word-break:break-all; margin-top:2px; }

  .freq-row{ margin-top:var(--sp-4); display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
  .freq-row select{ background:var(--ink); border:1px solid var(--border-strong); border-radius:var(--radius-sm);
                     padding:7px 10px; color:var(--paper); font:inherit; font-size:var(--fs-sm); }

  .topic-form{ display:flex; gap:10px; flex-wrap:wrap; margin-top:var(--sp-3); margin-bottom:var(--sp-2); }
  .topic-form input{ flex:1; min-width:240px; background:var(--ink); border:1px solid var(--border-strong);
                      border-radius:var(--radius-sm); padding:11px 13px; color:var(--paper); font:inherit; }

  .trend{ margin:14px 0 6px; }
  .trend-meta{ font-size:var(--fs-2xs); display:flex; justify-content:space-between; margin-top:6px; }

  /* The position number as an actual headline figure, not a tiny
     badge - "#15" in a pill reads as a label, where a client reading
     this wants to see the number itself as the answer. Colour-banded
     the same way the audit gauges are: top 10 good, 11-30 fair,
     beyond that poor. */
  .kw-position{ font-size:var(--fs-metric); font-weight:650; line-height:1; }
  .kw-position.good{ color:var(--good); }
  .kw-position.fair{ color:var(--fair); }
  .kw-position.poor{ color:var(--poor); }
  .kw-position.unknown{ color:var(--grey-dim); }
  .kw-position-label{ font-size:var(--fs-2xs); color:var(--grey-dim); margin-top:3px; }

  /* Column headers, same treatment as the sites table - makes the
     list read as data with labelled columns rather than a stack of
     unexplained numbers. */
  .kw-head{ display:flex; align-items:center; justify-content:space-between; gap:16px;
            padding:0 2px 10px; margin-top:var(--sp-4); border-bottom:1px solid var(--border);
            font-size:var(--fs-xs); font-weight:600; color:var(--grey-dim);
            text-transform:uppercase; letter-spacing:.04em; }
  .kw-head-right{ padding-right:96px; }
  .kw-link{ font-weight:600; font-size:var(--fs-md); text-decoration:none;
            background:none; border:0; padding:0; color:inherit; font-family:inherit; cursor:pointer; text-align:left; }
  .kw-link:hover{ color:var(--brand); }

  /* Modal shell itself is shared in the layout - only the history
     table inside it is specific to keywords. */
  .comp-metrics{ display:grid; grid-template-columns:1fr 1fr; gap:var(--sp-4); margin-top:var(--sp-5); }
  /* Search Console results - real data from Google, so presented as
     a plain table rather than dressed up. Average position is
     colour-banded on the same top-10 / 11-30 thresholds used for
     tracked keywords, so the two panels read consistently. */
  table.gsc-table{ width:100%; border-collapse:collapse; margin-top:var(--sp-4); }
  table.gsc-table th{ text-align:left; font-size:var(--fs-xs); font-weight:600; color:var(--grey-dim);
                       text-transform:uppercase; letter-spacing:.04em; padding:0 2px 10px;
                       border-bottom:1px solid var(--border); }
  table.gsc-table td{ padding:12px 2px; border-bottom:1px solid var(--border); font-size:var(--fs-sm); }
  table.gsc-table tr:last-child td{ border-bottom:0; }
  .gsc-good{ color:var(--good); font-weight:600; }
  .gsc-fair{ color:var(--fair); font-weight:600; }
  .gsc-poor{ color:var(--poor); font-weight:600; }

  /* Instructions read as a document, so they get real prose spacing
     rather than the tighter rhythm the rest of this page uses. */
  .gsc-help{ margin-top:var(--sp-5); font-size:var(--fs-sm); line-height:1.7; color:var(--grey); }
  .gsc-help p{ margin:0 0 var(--sp-4); }
  .gsc-help h3{ font-size:var(--fs-base); font-weight:650; color:var(--paper);
                 margin:var(--sp-5) 0 var(--sp-2); }
  .gsc-help ol{ margin:0 0 var(--sp-4); padding-left:20px; }
  .gsc-help li{ margin-bottom:8px; }
  .gsc-help strong{ color:var(--paper); font-weight:600; }
  .gsc-help a{ color:var(--brand); }
  .gsc-help-warn{ background:rgba(225,105,31,.10); border-left:3px solid var(--brand);
                   padding:12px 14px; border-radius:var(--radius-sm); color:var(--paper); }  .comp-metric{ text-align:center; padding:var(--sp-4) var(--sp-3); border:1px solid var(--border);
                border-radius:var(--radius); }
  .comp-metric.leader{ border-color:var(--brand); }
  .comp-num{ font-size:var(--fs-metric); font-weight:650; line-height:1; }
  .comp-label{ font-size:var(--fs-sm); color:var(--grey); margin-top:6px; word-break:break-all; }
  .comp-sub{ font-size:var(--fs-xs); color:var(--grey-dim); margin-top:6px; }

  table.kw-history{ width:100%; border-collapse:collapse; margin-top:var(--sp-5); }
  table.kw-history th{ text-align:left; font-size:var(--fs-xs); font-weight:600; color:var(--grey-dim);
                        text-transform:uppercase; letter-spacing:.04em; padding:0 2px 10px;
                        border-bottom:1px solid var(--border); }
  table.kw-history td{ padding:12px 2px; border-bottom:1px solid var(--border); font-size:var(--fs-sm); }
  table.kw-history tr:last-child td{ border-bottom:0; }
  .kw-hist-pos{ font-weight:650; font-size:var(--fs-md); }
  .kw-hist-pos.good{ color:var(--good); }
  .kw-hist-pos.fair{ color:var(--fair); }
  .kw-hist-pos.poor{ color:var(--poor); }
  .kw-hist-pos.unknown{ color:var(--grey-dim); }

  /* Two tabs, nothing fancier - SEO and Marketing were sharing one
     long scroll of cards, and that got unreadable once social posts
     landed alongside audits, comparisons, and content drafts. Active
     tab remembered in sessionStorage (not localStorage - no reason
     for this to outlive the browser session) specifically because the
     page auto-refreshes every 5s while something is pending; without
     that, watching a social post generate would keep bouncing back to
     the SEO tab on every reload. .tabs/.tab-btn/.tab-panel themselves
     are shared in the layout now - only this page-specific reasoning
     comment and the sessionStorage key below belong here. */

  /* Every workflow on this page (run an audit, find competitors,
     generate a draft...) was wrapped in an identical boxed card,
     which made a page with seven distinct areas read as seven equally
     weighted things rather than a page with real structure. None of
     them are the one thing that should dominate the page the way AI
     Recommendations does on the audit page, so all of them use the
     lighter .section treatment instead - a heading and generous
     spacing does the separating, not a background and a border. */
  .section:first-of-type{ margin-top:var(--sp-6); }
@endsection

@section('content')
<div class="wrap wrap-pad">
  <a class="back" href="/sites">← All sites</a>
  <div class="row-top">
    <div>
      <h1>{{ $site->name }}</h1>
      <div class="url">{{ $site->url }}</div>
    </div>
  </div>

  {{-- CMS is auto-detected from the last audit crawl (see
       SeoAuditService::detectCms) - shown, not editable, since it's a
       fact about the site, not a preference. Host and location are
       both set by hand: neither is reliably detectable from outside
       (hosting providers aren't visible in page content; a business's
       actual town is often only on a Contact page, or not stated at
       all), and Frankie already knows both answers. Host feeds the AI
       recommendations prompt so advice can be platform-specific;
       location feeds the one-click competitor lookup below. Kept
       outside the tabs - it's site-level information, not specific to
       either SEO or marketing work. --}}
  <form method="POST" action="/sites/{{ $site->id }}/host" class="freq-row">
    @csrf
    @if ($site->cms)
      <span class="muted" style="font-size:var(--fs-sm)">Platform: <strong style="color:var(--paper)">{{ $site->cms }}</strong></span>
      <span class="muted" style="font-size:var(--fs-sm)">·</span>
    @endif
    <label class="muted" style="font-size:var(--fs-sm)">Hosted with:</label>
    <input type="text" name="host" value="{{ $site->host }}" placeholder="e.g. 20i, SiteGround, Cloudways"
      style="background:var(--ink); border:1px solid var(--border-strong); border-radius:var(--radius-sm);
             padding:6px 10px; color:var(--paper); font:inherit; font-size:var(--fs-sm); width:180px">
    <label class="muted" style="font-size:var(--fs-sm)">Location:</label>
    <input type="text" name="location" value="{{ $site->location }}" placeholder="e.g. North Shields"
      style="background:var(--ink); border:1px solid var(--border-strong); border-radius:var(--radius-sm);
             padding:6px 10px; color:var(--paper); font:inherit; font-size:var(--fs-sm); width:150px">
    <button class="btn" type="submit" style="padding:7px 14px; font-size:var(--fs-sm)">Save</button>
  </form>

  @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

  <div class="tabs">
    <button type="button" class="tab-btn" id="tab-btn-seo" onclick="showSiteTab('seo')">SEO</button>
    <button type="button" class="tab-btn" id="tab-btn-marketing" onclick="showSiteTab('marketing')">Marketing</button>
  </div>

  <div id="tab-seo" class="tab-panel">
    <div class="section">
      <div class="section-head">
        <p class="subhead">Run an audit</p>
        <form method="POST" action="/sites/{{ $site->id }}/audits">
          @csrf<button class="btn btn-primary" type="submit">Run audit</button>
        </form>
      </div>
      <form method="POST" action="/sites/{{ $site->id }}/audit-frequency" class="freq-row" style="margin-top:0">
        @csrf
        <label class="muted" style="font-size:var(--fs-sm)">Automatic audits:</label>
        <select name="audit_frequency" onchange="this.form.submit()">
          <option value="off" @selected($site->audit_frequency === 'off')>Off</option>
          <option value="weekly" @selected($site->audit_frequency === 'weekly')>Weekly</option>
          <option value="monthly" @selected($site->audit_frequency === 'monthly')>Monthly</option>
        </select>
        @if ($site->audit_frequency !== 'off' && $site->next_audit_at)
          <span class="muted" style="font-size:var(--fs-sm)">Next: {{ $site->next_audit_at->format('j M, H:i') }}</span>
        @endif
      </form>
    </div>

    <div class="section">
      <div class="section-head">
        <p class="subhead">Competitor comparison</p>
        <a class="btn btn-primary" href="/sites/{{ $site->id }}/competitors/lookup">Look up competitors</a>
      </div>
      <p class="muted" style="margin:0 0 14px; font-size:var(--fs-sm)">
        Reads {{ $site->name }}'s own site to work out what kind of business it is, then searches live results for
        that plus its location. Needs a location set above first.
      </p>
      <form method="POST" action="/sites/{{ $site->id }}/competitors/search" class="topic-form" style="margin-top:0">
        @csrf
        <input type="text" name="query" placeholder="Or search a specific phrase yourself, e.g. &quot;IT support North Shields&quot;" required maxlength="255">
        <button class="btn" type="submit">Search</button>
      </form>
      <form method="POST" action="/sites/{{ $site->id }}/competitors" class="topic-form" style="margin-top:10px">
        @csrf
        <input type="text" name="competitor_domain" placeholder="Or compare a domain you already know, e.g. example.co.uk" required maxlength="255">
        <button class="btn" type="submit">Compare</button>
      </form>

      @forelse ($competitors as $comparison)
        @php
          $leader = $comparison->status === 'completed' ? $comparison->leader() : null;
        @endphp
        <div class="row" style="cursor:pointer" onclick="openModal('comp-{{ $comparison->id }}')">
          <div>
            <div class="rtitle">vs {{ $comparison->competitor_domain }}</div>
            <div class="rmeta">{{ $comparison->created_at->format('j M Y, H:i') }} · {{ ucfirst($comparison->status) }}</div>
          </div>
          @if ($comparison->status === 'completed')
            <span class="badge {{ $leader === 'us' ? 'good' : 'fair' }}">{{ $leader === 'us' ? 'Ahead' : 'Behind' }}</span>
          @elseif ($comparison->status === 'failed')
            <span class="badge poor">Failed</span>
          @else
            <span class="badge unknown">—</span>
          @endif
        </div>

        <div class="modal" id="modal-comp-{{ $comparison->id }}" onclick="if (event.target === this) closeModal('comp-{{ $comparison->id }}')">
          <div class="modal-inner">
            <div class="modal-head">
              <div>
                <div class="modal-title">{{ parse_url($site->url, PHP_URL_HOST) ?? $site->name }} vs {{ $comparison->competitor_domain }}</div>
                <div class="modal-sub">{{ $comparison->created_at->format('j M Y, H:i') }} · {{ ucfirst($comparison->status) }}</div>
              </div>
              <button type="button" class="modal-close" onclick="closeModal('comp-{{ $comparison->id }}')" aria-label="Close">×</button>
            </div>

            @if ($comparison->isPending())
              <div class="notice waiting">
                <span class="spinner" aria-hidden="true"></span>
                <span class="muted">Fetching search data for both domains.</span>
              </div>
            @endif

            @if ($comparison->error)
              <div class="notice">{{ $comparison->error }}</div>
            @endif

            @if ($comparison->status === 'completed')
              <div class="comp-metrics">
                <div class="comp-metric {{ $leader === 'us' ? 'leader' : '' }}">
                  <div class="comp-num">{{ $comparison->our_traffic !== null ? number_format($comparison->our_traffic) : '—' }}</div>
                  <div class="comp-label">{{ $site->name }}</div>
                  <div class="comp-sub">{{ $comparison->our_keywords !== null ? number_format($comparison->our_keywords) . ' ranking keywords' : '' }}</div>
                </div>
                <div class="comp-metric {{ $leader === 'them' ? 'leader' : '' }}">
                  <div class="comp-num">{{ $comparison->competitor_traffic !== null ? number_format($comparison->competitor_traffic) : '—' }}</div>
                  <div class="comp-label">{{ $comparison->competitor_domain }}</div>
                  <div class="comp-sub">{{ $comparison->competitor_keywords !== null ? number_format($comparison->competitor_keywords) . ' ranking keywords' : '' }}</div>
                </div>
              </div>
              <div class="muted" style="text-align:center; font-size:var(--fs-xs); margin-top:8px">
                Estimated monthly visits from unpaid search results
              </div>

              <p style="font-weight:650; margin:var(--sp-5) 0 6px; color:{{ $leader === 'us' ? 'var(--good)' : 'var(--fair)' }}">
                @if ($leader === 'us')
                  {{ $site->name }} is ahead right now
                @elseif ($leader === 'them')
                  {{ $comparison->competitor_domain }} is ahead right now
                @else
                  Not enough data to say which is ahead
                @endif
              </p>

              {{-- The full page carries the longer explainer and the
                   cross-link into this site's own audit findings -
                   deliberately not duplicated here, since the point of
                   the modal is the quick answer. --}}
              <a class="btn" href="/competitors/{{ $comparison->id }}" style="margin-top:var(--sp-4)">Full comparison</a>
            @endif
          </div>
        </div>
      @empty
        <div class="muted" style="margin-top:10px">No comparisons yet. Enter a competitor's domain above.</div>
      @endforelse
    </div>

    <div class="section">
      <div class="section-head">
        <p class="subhead">Search performance</p>
        {{-- Connecting is an agency setup task and exposes the Google
             account's full property list, so it's admin-only - see
             SearchConsoleController::middleware(). Members still see
             the data below, just not the controls. --}}
        @if (auth()->user()->canManageTeam())
          @if ($site->hasSearchConsole())
            <form method="POST" action="/sites/{{ $site->id }}/search-console"
              onsubmit="return confirm('Disconnect Search Console for {{ $site->name }}?')">
              @csrf @method('DELETE')
              <button class="linklike" type="submit" style="color:var(--poor); font-size:var(--fs-xs)">Disconnect</button>
            </form>
          @else
            <div style="display:flex; gap:10px; flex-wrap:wrap">
              <button type="button" class="btn" onclick="openModal('gsc-help')">How to set this up</button>
              <a class="btn btn-primary" href="/sites/{{ $site->id }}/search-console/connect">Connect Search Console</a>
            </div>
          @endif
        @endif
      </div>

      @if (! $site->hasSearchConsole())
        <p class="muted" style="margin:0; font-size:var(--fs-sm)">
          {{ $site->name }}'s own real search data from Google — which searches actually showed this site, how many
          people clicked, and where it actually ranked. Unlike everything else here, these are Google's own recorded
          figures rather than an outside estimate.
        </p>
      @elseif ($searchConsole && $searchConsole['error'])
        <div class="notice">{{ $searchConsole['error'] }}</div>
      @elseif ($searchConsole && $searchConsole['rows'])
        @php $t = $searchConsole['totals']; @endphp
        <p class="muted" style="margin:0 0 4px; font-size:var(--fs-sm)">
          {{ number_format($t['clicks']) }} clicks from {{ number_format($t['impressions']) }} appearances across
          these searches, {{ \Carbon\Carbon::parse($t['start'])->format('j M') }} to
          {{ \Carbon\Carbon::parse($t['end'])->format('j M') }}.
        </p>
        <table class="gsc-table">
          <thead>
            <tr><th>Search term</th><th>Clicks</th><th>Appearances</th><th>Avg position</th></tr>
          </thead>
          <tbody>
            @foreach (array_slice($searchConsole['rows'], 0, 10) as $row)
              <tr>
                <td>{{ $row['query'] }}</td>
                <td>{{ number_format($row['clicks']) }}</td>
                <td class="muted">{{ number_format($row['impressions']) }}</td>
                <td>
                  <span class="{{ $row['position'] <= 10 ? 'gsc-good' : ($row['position'] <= 30 ? 'gsc-fair' : 'gsc-poor') }}">
                    {{ number_format($row['position'], 1) }}
                  </span>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
        <p class="muted" style="margin-top:12px; font-size:var(--fs-xs)">
          Google's data lags a few days, so this ends {{ \Carbon\Carbon::parse($t['end'])->diffForHumans() }}.
          Any of these worth watching properly? Add it under Keyword tracking below.
        </p>
      @else
        <p class="muted" style="margin:0; font-size:var(--fs-sm)">
          Connected, but Google returned no search data for this period yet.
        </p>
      @endif
    </div>

    <div class="section">
      <p class="subhead">Keyword tracking</p>
      <p class="muted" style="margin:0 0 14px; font-size:var(--fs-sm)">
        Where {{ $site->name }} actually ranks in Google for phrases that matter to it, checked weekly. A gap of a
        few positions is normal week to week; the trend over months is what to watch.
      </p>
      <form method="POST" action="/sites/{{ $site->id }}/keywords" class="topic-form">
        @csrf
        <input type="text" name="keyword" placeholder="e.g. &quot;estate agents North Shields&quot;" required maxlength="255">
        <button class="btn btn-primary" type="submit">Track keyword</button>
      </form>

      @if ($trackedKeywords->isNotEmpty())
        <div class="kw-head">
          <span style="flex:1; min-width:200px">Keyword</span>
          <span class="kw-head-right">Position</span>
        </div>
      @endif

      @forelse ($trackedKeywords as $tracked)
        @php
          $latest = $tracked->latestRanking;
          $history = $tracked->rankings->whereNotNull('position')->values();

          // Movement since the previous check - the actual story a
          // client cares about ("we went up 4 places"), not just where
          // they sit today. Positive = improved, because a LOWER
          // position number is better.
          $previous = $history->count() >= 2 ? $history[$history->count() - 2] : null;
          $movement = ($previous && $latest && $latest->position !== null)
            ? $previous->position - $latest->position : null;
        @endphp
        <div class="row" style="align-items:flex-start">
          <div style="flex:1; min-width:200px">
            <button type="button" class="kw-link" onclick="openModal('kw-{{ $tracked->id }}')">{{ $tracked->keyword }}</button>
            <div class="rmeta">
              @if (! $latest)
                First check pending
              @elseif ($latest->error)
                Last check failed: {{ $latest->error }}
              @elseif ($latest->position === null)
                Not found in the first 100 results · checked {{ $latest->checked_at->diffForHumans() }}
              @else
                Page {{ (int) ceil($latest->position / 10) }} of Google
                @if ($movement > 0)
                  · <span style="color:var(--good)">up {{ $movement }} since last check</span>
                @elseif ($movement < 0)
                  · <span style="color:var(--poor)">down {{ abs($movement) }} since last check</span>
                @elseif ($movement === 0)
                  · no change since last check
                @endif
                · checked {{ $latest->checked_at->diffForHumans() }}
              @endif
            </div>

            @if ($history->count() >= 2)
              @php
                $w = 100; $h = 28; $pad = 3;
                $positions = $history->pluck('position');
                // Inverted: rank 1 is the best possible outcome, so it
                // plots at the TOP of the sparkline, same visual sense
                // as the audit fail-count trend where lower is better.
                $max = max(100, $positions->max());
                $step = ($w - $pad * 2) / max(1, $history->count() - 1);
                $points = $positions->values()->map(function ($position, $i) use ($step, $pad, $h, $max) {
                  $x = $pad + $i * $step;
                  $y = $pad + (($position - 1) / max(1, $max - 1)) * ($h - $pad * 2);
                  return round($x, 1) . ',' . round($y, 1);
                })->implode(' ');
              @endphp
              <svg viewBox="0 0 {{ $w }} {{ $h }}" preserveAspectRatio="none" style="width:140px;height:26px;display:block;margin-top:6px">
                <polyline points="{{ $points }}" fill="none" stroke="var(--brand)" stroke-width="1.6"
                  stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke"/>
              </svg>
            @endif
          </div>

          <div style="display:flex; align-items:center; gap:14px">
            @if ($latest && $latest->position !== null)
              <div style="text-align:right">
                <div class="kw-position {{ $latest->position <= 10 ? 'good' : ($latest->position <= 30 ? 'fair' : 'poor') }}">{{ $latest->position }}</div>
                <div class="kw-position-label">of 100</div>
              </div>
            @else
              <div style="text-align:right">
                <div class="kw-position unknown">—</div>
                <div class="kw-position-label">not ranking</div>
              </div>
            @endif
            <button type="button" class="linklike" style="font-size:var(--fs-xs)" onclick="openModal('kw-{{ $tracked->id }}')">History</button>
          </div>
        </div>

        {{-- One modal per keyword, rendered hidden. The rankings are
             already eager-loaded for the list above, so building these
             inline costs nothing extra and the modal opens instantly
             with no request - which is the whole reason a popup makes
             sense here over a separate page. --}}
        <div class="modal" id="modal-kw-{{ $tracked->id }}" onclick="if (event.target === this) closeModal('kw-{{ $tracked->id }}')">
          <div class="modal-inner">
            <div class="modal-head">
              <div>
                <div class="modal-title">{{ $tracked->keyword }}</div>
                <div class="modal-sub">
                  @if ($latest && $latest->position !== null)
                    Currently position {{ $latest->position }} — page {{ (int) ceil($latest->position / 10) }} of Google
                  @else
                    Not found in the first 100 results
                  @endif
                  @if ($history->isNotEmpty())
                    · best so far: {{ $history->min('position') }}
                  @endif
                </div>
              </div>
              <button type="button" class="modal-close" onclick="closeModal('kw-{{ $tracked->id }}')" aria-label="Close">×</button>
            </div>

            <table class="kw-history">
              <thead>
                <tr><th>Date</th><th>Position</th><th>Change</th><th>Page</th></tr>
              </thead>
              <tbody>
                {{-- Newest first for reading; the change column still
                     compares against the previous check in real
                     chronological order, not the row above it. --}}
                @foreach ($tracked->rankings->sortByDesc('checked_at') as $ranking)
                  @php
                    $idx = $history->search(fn ($r) => $r->id === $ranking->id);
                    $prior = ($idx !== false && $idx > 0) ? $history[$idx - 1] : null;
                    $change = ($prior && $ranking->position !== null) ? $prior->position - $ranking->position : null;
                  @endphp
                  <tr>
                    <td class="muted">{{ $ranking->checked_at->format('j M Y, H:i') }}</td>
                    <td>
                      @if ($ranking->error)
                        <span class="muted">Check failed</span>
                      @else
                        <span class="kw-hist-pos {{ $ranking->position === null ? 'unknown' : ($ranking->position <= 10 ? 'good' : ($ranking->position <= 30 ? 'fair' : 'poor')) }}">
                          {{ $ranking->position ?? 'Not found' }}
                        </span>
                      @endif
                    </td>
                    <td>
                      @if ($change === null)
                        <span class="muted">—</span>
                      @elseif ($change > 0)
                        <span style="color:var(--good)">▲ {{ $change }}</span>
                      @elseif ($change < 0)
                        <span style="color:var(--poor)">▼ {{ abs($change) }}</span>
                      @else
                        <span class="muted">No change</span>
                      @endif
                    </td>
                    <td class="muted">{{ $ranking->position ? 'Page ' . (int) ceil($ranking->position / 10) : '—' }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>

            @if ($tracked->rankings->isEmpty())
              <div class="muted" style="margin-top:12px">No checks recorded yet — the first runs within a minute of adding a keyword.</div>
            @endif

            <div style="margin-top:var(--sp-5); display:flex; gap:12px; flex-wrap:wrap">
              <form method="POST" action="/keywords/{{ $tracked->id }}/check">
                @csrf
                <button class="btn btn-primary" type="submit">Check now</button>
              </form>
              <form method="POST" action="/keywords/{{ $tracked->id }}"
                onsubmit="return confirm('Stop tracking &quot;{{ $tracked->keyword }}&quot;? This deletes its history too.')">
                @csrf @method('DELETE')
                <button class="btn" type="submit" style="color:var(--poor); border-color:var(--poor)">Stop tracking</button>
              </form>
            </div>
          </div>
        </div>
      @empty
        <div class="muted" style="margin-top:10px">No keywords tracked yet. Add one above.</div>
      @endforelse
    </div>

    {{-- Setup instructions, written out rather than left to be
         remembered. Search Console genuinely can't be set up from
         inside this app - Google only accepts domain verification
         through its own flow - so the honest thing is to explain the
         whole path clearly instead of hiding the manual part. --}}
    <div class="modal" id="modal-gsc-help" onclick="if (event.target === this) closeModal('gsc-help')">
      <div class="modal-inner">
        <div class="modal-head">
          <div>
            <div class="modal-title">Setting up Search Console</div>
            <div class="modal-sub">For {{ $site->name }}</div>
          </div>
          <button type="button" class="modal-close" onclick="closeModal('gsc-help')" aria-label="Close">&times;</button>
        </div>

        <div class="gsc-help">
          <p>
            Search Console is Google's own record of how a site performs in search — the real searches people typed,
            how many clicked, and where the site actually ranked. It's free, and it's the only data here that comes
            straight from Google rather than an outside estimate.
          </p>

          <p class="gsc-help-warn">
            This part can't be done inside SynthSEO. Google only accepts domain verification through its own site,
            because it's proving who owns the domain — no third-party app is allowed to do that on someone's behalf.
          </p>

          <h3>If the site already has Search Console</h3>
          <p>Most sites built by an agency already do. Ask whoever set it up to add your Google account:</p>
          <ol>
            <li>In Search Console, pick the property, then <strong>Settings → Users and permissions</strong>.</li>
            <li><strong>Add user</strong>, enter your Google account, permission <strong>Full</strong>.</li>
            <li>Come back here and press Connect Search Console.</li>
          </ol>

          <h3>If it doesn't exist yet</h3>
          <ol>
            <li>Go to <a href="https://search.google.com/search-console" target="_blank" rel="noopener">search.google.com/search-console</a> and sign in.</li>
            <li><strong>Add property</strong> → <strong>Domain</strong>, and enter {{ parse_url($site->url, PHP_URL_HOST) ?? $site->url }}.</li>
            <li>Google gives a TXT record. Add it to the domain's DNS (for domains on 20i, that's the DNS panel for that domain).</li>
            <li>Press <strong>Verify</strong>. If it fails, DNS hasn't propagated yet — wait an hour and try again.</li>
            <li>Come back here and press Connect Search Console.</li>
          </ol>

          <h3>What to expect afterwards</h3>
          <p>
            Search Console only starts collecting from the day it's verified. A newly verified site shows nothing for
            a day or two, and very little for the first few weeks. That's normal — it isn't broken, there just isn't
            any history for Google to report yet.
          </p>
        </div>
      </div>
    </div>

    <div class="section">
      <p class="subhead">Audit history</p>

      @php
        // Oldest-to-newest for a left-to-right trend, current page only -
        // a sparkline spanning a pagination boundary would be reading two
        // different time windows as one continuous line.
        //
        // Plots fail-count, not $audit->score. The score field still
        // exists on the row but audits/show deliberately stopped
        // displaying it - it's an invented weighting that moved between
        // runs when response time crossed a threshold, and told a reader
        // nothing on its own. "Fail count" is the same honest measure
        // already used everywhere else on this page; a downward line
        // means real progress, not a shifted internal number.
        $trend = $audits->filter(fn ($a) => $a->status === 'completed')->reverse()->values();
      @endphp

      @if ($trend->count() >= 2)
        @php
          $w = 100; $h = 34; $pad = 3;
          $failCounts = $trend->map(fn ($a) => $a->issueCounts()['fail']);
          $max = max(1, $failCounts->max());
          $min = 0;
          $range = max(1, $max - $min);
          $step = ($w - $pad * 2) / ($trend->count() - 1);
          $points = $failCounts->values()->map(function ($fails, $i) use ($step, $pad, $h, $min, $range) {
            $x = $pad + $i * $step;
            $y = $h - (($fails - $min) / $range) * ($h - $pad * 2) - $pad;
            return round($x, 1) . ',' . round($y, 1);
          })->implode(' ');
        @endphp
        <div class="trend">
          <svg viewBox="0 0 {{ $w }} {{ $h }}" preserveAspectRatio="none" style="width:100%;height:48px;display:block">
            <polyline points="{{ $points }}" fill="none" stroke="var(--brand)" stroke-width="1.6"
              stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke"/>
          </svg>
          <div class="muted trend-meta">
            <span>{{ $failCounts->first() }} to fix on {{ $trend->first()->created_at->format('j M') }}</span>
            <span>{{ $failCounts->last() }} to fix on {{ $trend->last()->created_at->format('j M') }}</span>
          </div>
        </div>
      @endif

      @forelse ($audits as $audit)
        <a class="row" href="/audits/{{ $audit->id }}">
          <div>
            <div class="rtitle">{{ $audit->created_at->format('j M Y, H:i') }}</div>
            <div class="rmeta">{{ ucfirst($audit->status) }}</div>
          </div>
          @if ($audit->status === 'completed')
            @php
              $c = $audit->issueCounts();
            @endphp
            <span class="badge {{ $c['fail'] ? 'poor' : ($c['warn'] ? 'fair' : 'good') }}">
              {{ $c['fail'] }} to fix · {{ $c['warn'] }} to review
            </span>
          @else
            <span class="badge unknown">—</span>
          @endif
        </a>
      @empty
        <div class="muted" style="margin-top:10px">No audits yet. Run the first one above.</div>
      @endforelse
      <div style="margin-top:16px">{{ $audits->links() }}</div>
    </div>
  </div>

  <div id="tab-marketing" class="tab-panel">
    <div class="section">
      <p class="subhead">Content drafts</p>
      <form method="POST" action="/sites/{{ $site->id }}/content" class="topic-form" style="margin-top:12px">
        @csrf
        <input type="text" name="topic" placeholder="Topic, e.g. &quot;why regular servicing matters&quot;" required maxlength="255">
        <button class="btn btn-primary" type="submit">Generate draft</button>
      </form>

      @forelse ($content as $piece)
        <a class="row" href="/content/{{ $piece->id }}">
          <div>
            <div class="rtitle">{{ $piece->title ?? $piece->topic }}</div>
            <div class="rmeta">{{ $piece->created_at->format('j M Y, H:i') }} · {{ ucfirst($piece->status) }}</div>
          </div>
          @if ($piece->status === 'completed')
            <span class="badge good">{{ $piece->word_count }} words</span>
          @elseif ($piece->status === 'failed')
            <span class="badge poor">Failed</span>
          @else
            <span class="badge unknown">—</span>
          @endif
        </a>
      @empty
        <div class="muted" style="margin-top:10px">No drafts yet. Enter a topic above to generate the first one.</div>
      @endforelse
    </div>

    <div class="section">
      <p class="subhead">Social posts</p>
      <p class="muted" style="margin:8px 0 0; font-size:var(--fs-sm)">
        A caption and an image, ready to review and post yourself — nothing here posts anywhere automatically.
      </p>
      <form method="POST" action="/sites/{{ $site->id }}/social" class="topic-form">
        @csrf
        <select name="platform" style="background:var(--ink); border:1px solid var(--border-strong); border-radius:var(--radius-sm);
                padding:11px 12px; color:var(--paper); font:inherit; font-size:var(--fs-base)">
          <option value="general">General</option>
          <option value="instagram">Instagram</option>
          <option value="facebook">Facebook</option>
          <option value="linkedin">LinkedIn</option>
        </select>
        <input type="text" name="topic" placeholder="Topic, e.g. &quot;spring MOT check reminder&quot;" required maxlength="255">
        <button class="btn btn-primary" type="submit">Generate post</button>
      </form>

      @forelse ($socialPosts as $post)
        <div class="row" style="cursor:pointer" onclick="openModal('social-{{ $post->id }}')">
          <div>
            <div class="rtitle" style="text-transform:capitalize">{{ $post->platform }} — {{ $post->topic }}</div>
            <div class="rmeta">{{ $post->created_at->format('j M Y, H:i') }} · {{ ucfirst($post->status) }}</div>
          </div>
          @if ($post->status === 'completed')
            <span class="badge good">Ready</span>
          @elseif ($post->status === 'failed')
            <span class="badge poor">Failed</span>
          @else
            <span class="badge unknown">—</span>
          @endif
        </div>

        <div class="modal" id="modal-social-{{ $post->id }}" onclick="if (event.target === this) closeModal('social-{{ $post->id }}')">
          <div class="modal-inner">
            <div class="modal-head">
              <div>
                <div class="modal-title" style="text-transform:capitalize">{{ $post->platform }} post</div>
                <div class="modal-sub">{{ $post->topic }} · {{ $post->created_at->format('j M Y, H:i') }}</div>
              </div>
              <button type="button" class="modal-close" onclick="closeModal('social-{{ $post->id }}')" aria-label="Close">×</button>
            </div>

            @if ($post->isPending())
              <div class="notice waiting">
                <span class="spinner" aria-hidden="true"></span>
                <span class="muted">Writing the caption and generating an image.</span>
              </div>
            @endif

            {{-- Caption and image are independent outcomes - see the
                 social_posts migration. Each shown or explained on its
                 own so a working caption is never hidden behind an
                 image that failed. --}}
            @if ($post->image_path)
              <img src="{{ $post->imageUrl() }}" alt="" style="width:100%; border-radius:var(--radius); display:block; margin-top:var(--sp-5)">
            @elseif ($post->image_error)
              <div class="notice">Image: {{ $post->image_error }}</div>
            @endif

            @if ($post->caption)
              <div id="caption-{{ $post->id }}" style="font-size:var(--fs-base); line-height:1.7; white-space:pre-wrap; margin-top:var(--sp-5)">{{ $post->caption }}</div>
              <button class="btn" type="button" style="margin-top:var(--sp-4)"
                onclick="navigator.clipboard.writeText(document.getElementById('caption-{{ $post->id }}').innerText)">
                Copy caption
              </button>
            @elseif ($post->caption_error)
              <div class="notice">Caption: {{ $post->caption_error }}</div>
            @endif
          </div>
        </div>
      @empty
        <div class="muted" style="margin-top:10px">No posts yet. Pick a platform and topic above.</div>
      @endforelse
    </div>

    <div class="section">
      <p class="subhead">Newsletter</p>
      <p class="muted" style="margin:8px 0 0; font-size:var(--fs-sm)">
        Drafted for review first — nothing sends to subscribers until you open it and click Send.
      </p>
      <form method="POST" action="/sites/{{ $site->id }}/newsletters" class="topic-form">
        @csrf
        <input type="text" name="topic" placeholder="Topic, e.g. &quot;what's new this month&quot;" required maxlength="255">
        <button class="btn btn-primary" type="submit">Draft newsletter</button>
      </form>

      @forelse ($newsletters as $newsletter)
        <a class="row" href="/newsletters/{{ $newsletter->id }}">
          <div>
            <div class="rtitle">{{ $newsletter->subject ?? $newsletter->topic }}</div>
            <div class="rmeta">{{ $newsletter->created_at->format('j M Y, H:i') }} · {{ ucfirst($newsletter->status) }}</div>
          </div>
          @if ($newsletter->isSent())
            <span class="badge good">Sent</span>
          @elseif ($newsletter->status === 'completed')
            <span class="badge unknown">Draft ready</span>
          @elseif ($newsletter->status === 'failed')
            <span class="badge poor">Failed</span>
          @else
            <span class="badge unknown">—</span>
          @endif
        </a>
      @empty
        <div class="muted" style="margin-top:10px">No newsletters yet. Enter a topic above.</div>
      @endforelse
    </div>

    <div class="section">
      <p class="subhead">Subscribers</p>
      <form method="POST" action="/sites/{{ $site->id }}/subscribers" class="topic-form">
        @csrf
        <input type="email" name="email" placeholder="subscriber@example.com" required maxlength="255">
        <input type="text" name="name" placeholder="Name (optional)" maxlength="255" style="max-width:180px">
        <button class="btn btn-primary" type="submit">Add subscriber</button>
      </form>

      @forelse ($subscribers as $subscriber)
        <div class="row">
          <div>
            <div class="rtitle">{{ $subscriber->name ?? $subscriber->email }}</div>
            @if ($subscriber->name)<div class="rmeta">{{ $subscriber->email }}</div>@endif
          </div>
          <form method="POST" action="/subscribers/{{ $subscriber->id }}"
            onsubmit="return confirm('Remove {{ $subscriber->email }}?')">
            @csrf @method('DELETE')
            <button class="linklike" type="submit" style="color:var(--poor); font-size:var(--fs-sm)">Remove</button>
          </form>
        </div>
      @empty
        <div class="muted" style="margin-top:10px">No subscribers yet.</div>
      @endforelse
    </div>
  </div>
</div>

<script>
function showSiteTab(name) {
  document.getElementById('tab-seo').style.display = name === 'seo' ? '' : 'none';
  document.getElementById('tab-marketing').style.display = name === 'marketing' ? '' : 'none';
  document.getElementById('tab-btn-seo').classList.toggle('active', name === 'seo');
  document.getElementById('tab-btn-marketing').classList.toggle('active', name === 'marketing');
  sessionStorage.setItem('siteTab', name);
}
showSiteTab(sessionStorage.getItem('siteTab') || 'seo');

/*
 * Modal open state is remembered in sessionStorage for the same
 * reason the active tab is, and it matters more here: this page
 * reloads itself every 5 seconds while anything is generating, which
 * would otherwise slam an open modal shut repeatedly - exactly while
 * someone is watching a social post or comparison finish. Reopening
 * it on load makes the refresh invisible instead.
 *
 * Keys are prefixed by type (kw-3, social-3, comp-3) because a
 * keyword, a post and a comparison can all legitimately be id 3.
 */
function openModal(key) {
  var el = document.getElementById('modal-' + key);
  if (el) {
    el.classList.add('open');
    document.body.style.overflow = 'hidden';
    sessionStorage.setItem('openModal', key);
  }
}

function closeModal(key) {
  var el = document.getElementById('modal-' + key);
  if (el) {
    el.classList.remove('open');
    document.body.style.overflow = '';
  }
  sessionStorage.removeItem('openModal');
}

function closeAllModals() {
  document.querySelectorAll('.modal.open').forEach(function (el) {
    el.classList.remove('open');
  });
  document.body.style.overflow = '';
  sessionStorage.removeItem('openModal');
}

document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape') {
    closeAllModals();
  }
});

// Restore after an auto-refresh. Silently forgets the key if that
// modal no longer exists - a tracked keyword that was deleted, a post
// that finished and re-rendered - rather than leaving a stale entry
// that never matches anything again.
(function () {
  var key = sessionStorage.getItem('openModal');
  if (! key) return;
  if (document.getElementById('modal-' + key)) {
    openModal(key);
  } else {
    sessionStorage.removeItem('openModal');
  }
})();
</script>
@endsection
