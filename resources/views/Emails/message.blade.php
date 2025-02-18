<link href="https://fonts.googleapis.com/css?family=Sen&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css?family=Secular+One|Sen&display=swap" rel="stylesheet">

<body style="margin:0;margin-top: 5%;margin-bottom: 5%;">
    <div style="width: 100%;">
        <div style="max-width: 640px;margin: auto;padding: 3rem;border: 1px solid #e5e7eb;border-radius: 1rem;">
            <div style="margin: auto;">
                <div style="text-align: center;">
                    <h2 style="font-family: 'Secular One', sans-serif;font-size: 2.2rem;margin-bottom: 1rem;">EI</h2>
                </div>
            </div>
            <div style="text-align: center;margin: auto;">
                <h2 style="font-family: 'Sen', sans-serif;color: #ff385c;font-size: 1.5rem;margin-bottom: 2rem;">
                    {{ $attributes['title'] }}
                </h2>
            </div>
            
            <div style="
                background: #ff385c;
                padding: 2rem;
                margin: 2rem 0;
                border-radius: 0.5rem;
                ">
                <div style="text-align: center;margin: auto;">
                    <span style="font-family: 'Sen', sans-serif;color: white;">
                        <p style="white-space: pre-line;margin: 0;">{!! $attributes['body'] !!}</p>
                    </span>
                </div>
            </div>

            <div style="text-align: center;margin: auto;">
                <a href="{{ $attributes['app_url'] }}/inbox">
                    <button style="
                        border: 1px solid #ff385c;
                        padding: .8rem 1.5rem;
                        font-size: 1rem;
                        color: #ff385c;
                        background: white;
                        border-radius: 8px;
                        font-family: 'Sen', sans-serif;
                        cursor: pointer;
                        transition: all 0.2s;
                        margin-top: 1.5rem;
                        ">View Message</button>
                </a>
                <p style="font-family: 'Sen', sans-serif;color: #6d6d6d;margin-top: 2rem;">
                    Thanks,<br>
                    The EI team
                </p>
            </div>
        </div>
    </div>
</body>