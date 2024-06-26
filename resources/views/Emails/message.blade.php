<link href="https://fonts.googleapis.com/css?family=Sen&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css?family=Secular+One|Sen&display=swap" rel="stylesheet">

<body style="margin:0;margin-top: 5%;">
    <div>
        <div style="max-width: 550px;margin: auto;padding: 0rem 1rem;">
            <div style="text-align: center;">
                <h2 style="font-family: 'Secular One', sans-serif;font-size: 3rem;">EI.</h2>
            </div>
            @if ($attributes['sender'])
                <div style="text-align: center;margin-bottom: 1.4rem;">
                    <span style="font-family: 'Sen', sans-serif;color: #6d6d6d;"><p>You have received a message from {{$attributes['sender']}}.</p></span>
                </div>
            @else
                <div style="text-align: center;margin-bottom: 1.4rem;">
                    <span style="font-family: 'Sen', sans-serif;color: #6d6d6d;"><p>You have received a message.</p></span>
                </div>
            @endif
            @if ($attributes['event'])
                <div style="text-align: center;margin-bottom: 1.4rem;">
                    <span style="font-family: 'Sen', sans-serif;color: #6d6d6d;"><p>This is in regards to {{$attributes['event']}}.</p></span>
                </div>
            @endif
        </div>
        <div style="
            background: #1abdb6;
            padding: 4rem 0rem;
            display: inline-block;
            width: 100%;
            ">
            <div style="text-align: center;max-width: 550px;margin: auto;padding: 0rem 1rem;">
                <span style="font-family: 'Sen', sans-serif;color: white;"><p style="white-space: pre-line;">{!! $attributes['body'] !!}</p></span>
            </div>
        </div>
        <div style="text-align: center;max-width: 550px;margin: auto;padding: 2rem 1rem;">
            <a href="{{ $attributes['app_url'] }}/inbox?event={{ $attributes['id'] }}">
                <button style="border: 1px solid #1abdb6;padding: .5rem 1rem;font-size: 1rem;color:#0c8a84;">Check it out</button>
            </a>
        </div>
            </div>
        </div>
    </div>
</body>