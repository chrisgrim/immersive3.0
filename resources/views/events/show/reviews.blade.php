<section class="border rounded-5xl border-neutral-300 py-12 px-8 md:py-16 md:px-0">
    @foreach ($event->eventreviews as $review)
        <div>
            <a 
                rel="noreferrer" 
                target="_blank" 
                href="{{ $review->url }}">
                <div class="px-8 flex items-center mb-4">
                    <h3> {{ $review->reviewer_name }}'s Review</h3>
                </div>
                <blockquote 
                    style="white-space: pre-line;"
                    class="px-8 md:px-0"> {{ substr($review->review, 0, 300) }}...<span class="show-text">Read More</span>
                </blockquote>
            </a>
        </div>
    @endforeach
</section>