<x-guest-layout>
    <div class="auth-card-heading"><p class="public-eyebrow">Get started</p><h2>Create your PlanOps account</h2><p>A clear place for the work that matters.</p></div>
    <form method="POST" action="{{ route('register') }}" class="auth-form">
        @csrf
        <div class="auth-field"><x-input-label for="name" :value="__('Full name')" /><x-text-input id="name" class="auth-input" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" placeholder="Your name" /><x-input-error :messages="$errors->get('name')" /></div>
        <div class="auth-field"><x-input-label for="email" :value="__('Email address')" /><x-text-input id="email" class="auth-input" type="email" name="email" :value="old('email')" required autocomplete="username" placeholder="you@example.com" /><x-input-error :messages="$errors->get('email')" /></div>
        <div class="auth-field"><x-input-label for="password" :value="__('Password')" /><div class="auth-input-wrap"><x-text-input id="password" class="auth-input" type="password" name="password" required autocomplete="new-password" placeholder="At least 8 characters" /><button type="button" class="auth-password-toggle" data-password-toggle="password" aria-label="Show password"><i class="ph ph-eye" aria-hidden="true"></i></button></div><x-input-error :messages="$errors->get('password')" /></div>
        <div class="auth-field"><x-input-label for="password_confirmation" :value="__('Confirm password')" /><div class="auth-input-wrap"><x-text-input id="password_confirmation" class="auth-input" type="password" name="password_confirmation" required autocomplete="new-password" placeholder="Repeat your password" /><button type="button" class="auth-password-toggle" data-password-toggle="password_confirmation" aria-label="Show password"><i class="ph ph-eye" aria-hidden="true"></i></button></div><x-input-error :messages="$errors->get('password_confirmation')" /></div>
        <button type="submit" class="auth-submit">Create account <span aria-hidden="true">→</span></button>
    </form>
    <p class="auth-switch">Already have an account? <a href="{{ route('login') }}">Log In</a></p>
</x-guest-layout>
