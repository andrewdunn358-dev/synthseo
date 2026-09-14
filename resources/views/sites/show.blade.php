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
        <a class="row" href="/competitors/{{ $comparison->id }}">
          <div>
            <div class="rtitle">vs {{ $comparison->competitor_domain }}</div>
            <div class="rmeta">{{ $comparison->created_at->format('j M Y, H:i') }} · {{ ucfirst($comparison->status) }}</div>
          </div>
          @if ($comparison->status === 'completed')
            @php
              $leadsThem = $comparison->leader() === 'us';
            @endphp
            <span class="badge {{ $leadsThem ? 'good' : 'fair' }}">{{ $leadsThem ? 'Ahead' : 'Behind' }}</span>
          @elseif ($comparison->status === 'failed')
            <span class="badge poor">Failed</span>
          @else
            <span class="badge unknown">—</span>
          @endif
        </a>
      @empty
        <div class="muted" style="margin-top:10px">No comparisons yet. Enter a competitor's domain above.</div>
      @endforelse
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
        <a class="row" href="/social/{{ $post->id }}">
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
        </a>
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
      <p class="muted" style="margin:8px 0 0; font-size:var(--fs-sm)">
        Managed directly in Resend — nothing here is stored locally, so unsubscribes and bounces stay accurate
        automatically.
      </p>
      <form method="POST" action="/sites/{{ $site->id }}/subscribers" class="topic-form">
        @csrf
        <input type="email" name="email" placeholder="subscriber@example.com" required maxlength="255">
        <input type="text" name="name" placeholder="Name (optional)" maxlength="255" style="max-width:180px">
        <button class="btn btn-primary" type="submit">Add subscriber</button>
      </form>
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
</script>
@endsection
