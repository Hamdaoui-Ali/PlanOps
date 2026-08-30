<x-guest-layout>
    <div class="auth-card-heading"><p class="public-eyebrow">Welcome back</p><h2>Log in to PlanOps</h2><p>Pick up where you left off.</p></div>
    <x-auth-session-status class="auth-status" :status="session('status')" />
    <form method="POST" action="{{ route('login') }}" class="auth-form">
        @csrf
        <div class="auth-field"><x-input-label for="email" :value="__('Email address')" /><x-text-input id="email" class="auth-input" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" placeholder="you@example.com" /><x-input-error :messages="$errors->get('email')" /></div>
        <div class="auth-field"><div class="auth-label-row"><x-input-label for="password" :value="__('Password')" />@if (Route::has('password.request'))<a href="{{ route('password.request') }}">Forgot your password?</a>@endif</div><div class="auth-input-wrap"><x-text-input id="password" class="auth-input" type="password" name="password" required autocomplete="current-password" placeholder="Enter your password" /><button type="button" class="auth-password-toggle" data-password-toggle="password" aria-label="Show password"><i class="ph ph-eye" aria-hidden="true"></i></button></div><x-input-error :messages="$errors->get('password')" /></div>
        <label class="auth-checkbox" for="remember_me"><input id="remember_me" type="checkbox" name="remember"><span>Remember me</span></label>
        <button type="submit" class="auth-submit">Log In <span aria-hidden="true">→</span></button>
    </form>
    <p class="auth-switch">New to PlanOps? <a href="{{ route('register') }}">Create an account</a></p>
</x-guest-layout>
