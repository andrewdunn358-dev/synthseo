@extends('layouts.app')

@section('title', 'Dashboard — SynthSEO')

@section('styles')
  .dash-wrap{ max-width:1160px; margin:0 auto; padding:48px 32px; }
  .dash-head{ display:flex; justify-content:space-between; align-items:center; margin-bottom:32px; }
  .dash-head h1{ font-size:28px; }
  .logout-btn{ background:none; border:1px solid var(--border-strong); color:var(--paper); padding:9px 18px; border-radius:4px; cursor:pointer; font-size:14px; }
  .logout-btn:hover{ border-color:var(--lime); }
  .placeholder-note{ background:var(--panel); border:1px solid var(--border); border-radius:8px; padding:40px; color:var(--grey); text-align:center; }
@endsection

@section('content')
<div class="dash-wrap">
  <div class="dash-head">
    <h1>Welcome, {{ auth()->user()->name }}</h1>
    <form method="POST" action="/logout">
      @csrf
      <button class="logout-btn" type="submit">Log out</button>
    </form>
  </div>
  <div class="placeholder-note">Dashboard shell — audit data, content, and reporting will land here as each feature gets built.</div>
</div>
@endsection
