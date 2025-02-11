@props([
    'text' => '',
    'limit' => 100,
    'bodyClass' => '',
    'whiteSpace' => 'pre-wrap',
    'blockquote' => false,
    'lessButton' => true,
    'input' => []
])

@php
    // Get values from input array if provided, otherwise use direct props
    $text = $input['text'] ?? $text;
    $limit = $input['limit'] ?? $limit;
    $bodyClass = $input['bodyClass'] ?? $bodyClass;
    $whiteSpace = $input['whiteSpace'] ?? $whiteSpace;
    $blockquote = $input['blockquote'] ?? $blockquote;
    $lessButton = $input['lessButton'] ?? $lessButton;

    // Simple word counting and truncation
    $stripped = strip_tags($text);
    $words = preg_split('/\s+/', $stripped, -1, PREG_SPLIT_NO_EMPTY);
    $wordCount = count($words);
    $needsShowMore = $wordCount > $limit;
    $id = str_replace('-', '_', 'show_more_' . uniqid());

    if ($needsShowMore) {
        $truncatedWords = array_slice($words, 0, $limit);
        $shortText = implode(' ', $truncatedWords) . '...';
    } else {
        $shortText = $text;
    }
@endphp

<div>
    @if($blockquote)
        <blockquote class="px-4 py-2">
            <span 
                class="{{ $bodyClass }}"
                style="white-space: {{ $whiteSpace }};"
            >
                {{-- Full Text (Hidden by default) --}}
                <span id="full_{{ $id }}" style="display: none;">{!! $text !!}</span>

                {{-- Short Text (Shown by default) --}}
                <span id="short_{{ $id }}" style="display: inline;">{!! $shortText !!}</span>
            </span>
            @if($needsShowMore)
                <br>
                <span 
                    id="more_btn_{{ $id }}"
                    class="text-xl text-[#008489] font-semibold cursor-pointer"
                    style="display: inline;"
                    onclick="toggleShowMore_{{ $id }}()"
                >Show More</span>
                <span 
                    id="less_btn_{{ $id }}"
                    class="text-xl text-[#008489] font-semibold cursor-pointer"
                    style="display: none;"
                    onclick="toggleShowMore_{{ $id }}()"
                >Show Less</span>
            @endif
        </blockquote>
    @else
        <p class="text text-2.5xl leading-9">
            <span 
                class="{{ $bodyClass }}"
                style="white-space: {{ $whiteSpace }};"
            >
                {{-- Full Text (Hidden by default) --}}
                <span id="full_{{ $id }}" style="display: none;">{!! $text !!}</span>

                {{-- Short Text (Shown by default) --}}
                <span id="short_{{ $id }}" style="display: inline;">{!! $shortText !!}</span>
            </span>
            @if($needsShowMore)
                <br>
                <span 
                    id="more_btn_{{ $id }}"
                    class="text-xl text-[#008489] font-semibold cursor-pointer"
                    style="display: inline;"
                    onclick="toggleShowMore_{{ $id }}()"
                >Show More</span>
                <span 
                    id="less_btn_{{ $id }}"
                    class="text-xl text-[#008489] font-semibold cursor-pointer"
                    style="display: none;"
                    onclick="toggleShowMore_{{ $id }}()"
                >Show Less</span>
            @endif
        </p>
    @endif

    <script>
        function toggleShowMore_{{ $id }}() {
            const fullText = document.getElementById('full_{{ $id }}');
            const shortText = document.getElementById('short_{{ $id }}');
            const moreBtn = document.getElementById('more_btn_{{ $id }}');
            const lessBtn = document.getElementById('less_btn_{{ $id }}');

            if (shortText.style.display === 'none') {
                // We're currently showing the full text, switch to short text
                shortText.style.display = 'inline';
                fullText.style.display = 'none';
                lessBtn.style.display = 'none';
                moreBtn.style.display = 'inline';
            } else {
                // We're currently showing the short text, switch to full text
                shortText.style.display = 'none';
                fullText.style.display = 'inline';
                moreBtn.style.display = 'none';
                lessBtn.style.display = 'inline';
            }
        }
    </script>
</div>