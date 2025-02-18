<link href="https://fonts.googleapis.com/css?family=Sen&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css?family=Secular+One|Sen&display=swap" rel="stylesheet">

<body style="margin:0;margin-top: 5%;">
    <div style="width: 100%;">
        <div style="max-width: 640px;margin: auto;padding: 3rem;border: 1px solid #e5e7eb;border-radius: 1rem;">
            <div style="margin: auto;">
                <div style="text-align: center;">
                    <h2 style="font-family: 'Secular One', sans-serif;font-size: 2.2rem;margin-bottom: 1rem;">EI</h2>
                </div>
            </div>
            <div>
                <h2 style="font-family: 'Sen', sans-serif;color: #ff385c;font-size: 1.5rem;margin-bottom: 2rem;">
                    Your Login Code
                </h2>
            </div>
            <div>
                <div>
                    <span style="font-family: 'Sen', sans-serif;color: #374151;">
                        <p style="margin-bottom: 0rem;">Click below to automatically sign in:</p>
                    </span>
                    <a 
                        href="{{ url("/login/auto/{$code}?email=" . urlencode($email)) }}"
                        style="
                            display: inline-block;
                            font-family: 'Sen', sans-serif;
                            font-size: 1.2rem;
                            margin: 1rem 0;
                            margin-bottom: 1rem;
                            color: white;
                            font-weight: bold;
                            background: linear-gradient(to right, #E41E53, #FF4E85);
                            border: none;
                            padding: 1rem 2rem;
                            border-radius: 9999px;
                            text-decoration: none;
                        "
                    >Sign in automatically</a>
                    <span style="font-family: 'Sen', sans-serif;color: #374151;">
                        <p style="margin-bottom: 0rem;">Or use this code to sign in:</p>
                    </span>
                    <div 
                        style="
                            font-family: monospace;
                            font-size: 2rem;
                            letter-spacing: 0.5rem;
                            margin: 2rem 0;
                            color: #ff385c;
                            font-weight: bold;
                            background: none;
                            padding: 1rem 2rem;
                            border-radius: 0.5rem;
                            user-select: all;
                        "
                    >{{ $code }}</div>
                    <span style="font-family: 'Sen', sans-serif;color: #374151;">
                        <p>This code will expire in 15 minutes.</p>
                    </span>
                </div>
            </div>
            <div style="text-align: center;margin: auto;">
                <p style="font-family: 'Sen', sans-serif;color: #6d6d6d;margin-top: 2rem;">
                    Thanks,<br>
                    The EI team
                </p>
            </div>
        </div>
    </div>
</body>