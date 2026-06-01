<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Authorize {{ $client->name }} — Family Timeline</title>
    <style>
        :root { color-scheme: light dark; }
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: #0f1115; color: #e7e9ee; padding: 24px;
        }
        .card {
            width: 100%; max-width: 420px; background: #181b22; border: 1px solid #272b34;
            border-radius: 16px; padding: 32px; box-shadow: 0 20px 50px rgba(0,0,0,.4);
        }
        h1 { font-size: 1.25rem; margin: 0 0 4px; }
        .sub { color: #9aa1ad; font-size: .9rem; margin: 0 0 24px; }
        .client { font-weight: 700; }
        .scopes { background: #11131a; border: 1px solid #272b34; border-radius: 10px; padding: 14px 16px; margin: 0 0 24px; }
        .scopes p { margin: 0 0 8px; font-size: .8rem; color: #9aa1ad; text-transform: uppercase; letter-spacing: .04em; }
        .scopes ul { margin: 0; padding-left: 18px; }
        .scopes li { margin: 4px 0; font-size: .95rem; }
        .actions { display: flex; gap: 12px; }
        form { flex: 1; margin: 0; }
        button { width: 100%; padding: 12px; border-radius: 10px; border: 0; font-size: .95rem; font-weight: 600; cursor: pointer; }
        .approve { background: #6366f1; color: #fff; }
        .approve:hover { background: #4f52e0; }
        .deny { background: transparent; color: #e7e9ee; border: 1px solid #3a3f4b; }
        .deny:hover { background: #21252e; }
        .who { color: #9aa1ad; font-size: .8rem; margin-top: 20px; text-align: center; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Authorize access</h1>
        <p class="sub"><span class="client">{{ $client->name }}</span> wants to connect to your Family Timeline.</p>

        <div class="scopes">
            <p>This will allow it to</p>
            <ul>
                @forelse ($scopes as $scope)
                    <li>{{ $scope->description }}</li>
                @empty
                    <li>Access your Family Timeline</li>
                @endforelse
            </ul>
        </div>

        <div class="actions">
            <form method="post" action="{{ route('passport.authorizations.approve') }}">
                @csrf
                <input type="hidden" name="auth_token" value="{{ $authToken }}">
                <button type="submit" class="approve">Allow</button>
            </form>
            <form method="post" action="{{ route('passport.authorizations.deny') }}">
                @csrf
                @method('DELETE')
                <input type="hidden" name="auth_token" value="{{ $authToken }}">
                <button type="submit" class="deny">Deny</button>
            </form>
        </div>

        <p class="who">Signed in as {{ $user->name }} ({{ $user->email }})</p>
    </div>
</body>
</html>
