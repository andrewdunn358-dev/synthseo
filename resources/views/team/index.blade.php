@extends('layouts.app')
@section('title', 'Team — SynthSEO')

@section('styles')
  .wrap-pad{ padding:var(--sp-7) 32px 80px; }

  .addform{ display:flex; gap:10px; flex-wrap:wrap; margin-top:var(--sp-3); align-items:flex-start; }
  input[type=text],input[type=email],input[type=password]{ background:var(--ink); border:1px solid var(--border-strong);
    color:var(--paper); padding:10px 12px; border-radius:var(--radius-sm); font:inherit; font-size:var(--fs-base);
    min-width:160px; flex:1; }
  select{ background:var(--ink); border:1px solid var(--border-strong); color:var(--paper); padding:10px 12px;
          border-radius:var(--radius-sm); font:inherit; font-size:var(--fs-base); }
  .err{ color:var(--poor); font-size:var(--fs-sm); margin-top:8px; }

  .site-checks{ display:flex; flex-wrap:wrap; gap:10px 16px; width:100%; margin-top:2px; }
  .site-check{ display:flex; align-items:center; gap:6px; font-size:var(--fs-sm); color:var(--grey); }
  .site-check input{ width:auto; min-width:0; }
  .role-note{ font-size:var(--fs-xs); color:var(--grey-dim); width:100%; margin-top:-4px; }

  table.team{ width:100%; border-collapse:collapse; margin-top:var(--sp-5); }
  table.team th{ text-align:left; font-size:var(--fs-xs); font-weight:600; color:var(--grey-dim);
                  text-transform:uppercase; letter-spacing:.04em; padding:0 2px 10px; border-bottom:1px solid var(--border); }
  table.team td{ padding:16px 2px; border-bottom:1px solid var(--border); vertical-align:top; }
  table.team tr:last-child td{ border-bottom:0; }
  .member-name{ font-weight:600; font-size:var(--fs-md); }
  .member-email{ color:var(--grey-dim); font-family:'IBM Plex Mono',monospace; font-size:var(--fs-2xs); }
  .access-form{ display:flex; flex-wrap:wrap; gap:8px 14px; align-items:center; }
  .access-form label{ display:flex; align-items:center; gap:5px; font-size:var(--fs-xs); color:var(--grey); }
  .access-form input{ width:auto; min-width:0; }
  .remove-form{ display:inline; }
@endsection

@section('content')
<div class="wrap wrap-pad">
  <h1>Team</h1>
  <p class="muted" style="margin-top:8px; font-size:var(--fs-base)">
    Admins see every site in the account. Members only see the sites explicitly ticked below - useful for a
    contractor or a specific colleague who should only work on a subset of clients.
  </p>

  @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

  <div class="section">
    <p class="subhead">Add a team member</p>
    <form method="POST" action="/team" class="addform">
      @csrf
      <input type="text" name="name" placeholder="Name" value="{{ old('name') }}" required>
      <input type="email" name="email" placeholder="Email" value="{{ old('email') }}" required>
      <input type="password" name="password" placeholder="Password" minlength="8" required>
      <select name="role" id="role-select" onchange="document.getElementById('site-picker').style.display = this.value === 'member' ? 'flex' : 'none'">
        <option value="admin">Admin — sees everything</option>
        <option value="member">Member — only selected sites</option>
      </select>
      <button class="btn btn-primary" type="submit">Add</button>

      <div class="site-checks" id="site-picker" style="display:none">
        <span class="role-note">Sites this member can access:</span>
        @foreach ($sites as $site)
          <label class="site-check">
            <input type="checkbox" name="site_ids[]" value="{{ $site->id }}">
            {{ $site->name }}
          </label>
        @endforeach
      </div>
    </form>
    @error('name')<div class="err">{{ $message }}</div>@enderror
    @error('email')<div class="err">{{ $message }}</div>@enderror
    @error('password')<div class="err">{{ $message }}</div>@enderror

    <p class="role-note" style="margin-top:12px">
      There's no invite email yet - tell them the password you just set directly.
    </p>
  </div>

  <table class="team">
    <thead>
      <tr><th>Name</th><th>Role</th><th>Site access</th><th></th></tr>
    </thead>
    <tbody>
      @foreach ($team as $member)
        <tr>
          <td>
            <div class="member-name">{{ $member->name }}</div>
            <div class="member-email">{{ $member->email }}</div>
          </td>
          <td class="muted">{{ ucfirst($member->role) }}</td>
          <td>
            @if ($member->role === 'member')
              <form method="POST" action="/team/{{ $member->id }}/access" class="access-form">
                @csrf
                @foreach ($sites as $site)
                  <label>
                    <input type="checkbox" name="site_ids[]" value="{{ $site->id }}"
                      @checked(in_array($site->id, $grants[$member->id] ?? []))
                      onchange="this.form.requestSubmit()">
                    {{ $site->name }}
                  </label>
                @endforeach
              </form>
            @else
              <span class="muted" style="font-size:var(--fs-sm)">Everything</span>
            @endif
          </td>
          <td>
            @if ($member->id !== auth()->id())
              <form method="POST" action="/team/{{ $member->id }}" class="remove-form"
                onsubmit="return confirm('Remove {{ $member->name }}? This deletes their login.')">
                @csrf @method('DELETE')
                <button class="linklike" type="submit" style="color:var(--poor)">Remove</button>
              </form>
            @endif
          </td>
        </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endsection
