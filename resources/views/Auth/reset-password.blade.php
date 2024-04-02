<div class="container">
    <h1>Reset Password</h1>
    <form method="POST" action="{{ route('password.store') }}">
        @csrf

        <input type="hidden" name="token" value="{{ $token }}">

        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" name="email" id="email" value="{{ $request->email }}" required autofocus>
        </div>

        <div class="form-group">
            <label for="password">New Password</label>
            <input type="password" name="password" id="password" required>
        </div>

        <div class="form-group">
            <label for="password-confirm">Confirm New Password</label>
            <input type="password" name="password_confirmation" id="password-confirm" required>
        </div>

        <div class="form-group">
            <button type="submit">Reset Password</button>
        </div>
    </form>
</div>