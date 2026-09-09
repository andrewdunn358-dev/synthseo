@extends('layouts.app')

@section('title', 'Register — SynthSEO')

@section('styles')
  .auth-wrap{ max-width:400px; margin:80px auto; padding:0 24px; }
  .auth-card{ background:var(--panel); border:1px solid var(--border); border-radius:8px; padding:36px 32px; }
  .auth-card h1{ font-size:26px; margin-bottom:24px; }
  label{ display:block; font-size:13px; color:var(--grey); margin-bottom:6px; }
  input{ width:100%; padding:11px 12px; margin-bottom:18px; background:#11161A; border:1px solid var(--border-strong); border-radius:4px; color:var(--paper); font-size:14px; }
  button{ width:100%; padding:12px; background:var(--lime); color:#1A1204; border:none; border-radius:4px; font-weight:600; font-size:15px; cursor:pointer; }
  button:hover{ background:#F0813C; }
  .error{ background:rgba(255,107,87,0.12); color:#FF6B57; padding:10px 14px; border-radius:4px; font-size:13.5px; margin-bottom:18px; }
  .alt-link{ text-align:center; margin-top:18px; font-size:13.5px; color:var(--grey); }
  .alt-link a{ color:var(--lime); text-decoration:none; }
@endsection

@section('content')
<div class="auth-wrap">
  <div class="auth-card">
    <h1>Create account</h1>
    @if ($errors->any())
      <div class="error">{{ $errors->first() }}</div>
    @endif
    <form method="POST" action="/register">
      @csrf
      <label>Name</label>
      <input type="text" name="name" value="{{ old('name') }}" required autofocus>
      <label>Email</label>
      <input type="email" name="email" value="{{ old('email') }}" required>
      <label>Password</label>
      <input type="password" name="password" required minlength="8">
      <button type="submit">Create account</button>
    </form>
    <div class="alt-link">Already have an account? <a href="/login">Log in</a></div>
  </div>
</div>
@endsection
