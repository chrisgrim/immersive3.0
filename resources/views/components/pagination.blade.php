@props(['paginator'])

@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination Navigation" class="pagination">
        <ul class="text-center flex justify-center items-center list-none m-0 p-0">
            {{-- Previous Page Link --}}
            <li class="inline p-2 mt-2">
                @if ($paginator->onFirstPage())
                    <button 
                        class="border-none rounded-full p-2 inline-flex items-center justify-center font-medium shadow-custom-3 fill-gray-300"
                        disabled
                        aria-disabled="true"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="w-10 h-10">
                            <path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/>
                        </svg>
                    </button>
                @else
                    <a
                        href="{{ $paginator->previousPageUrl() }}"
                        class="border-none rounded-full p-2 inline-flex items-center justify-center font-medium shadow-custom-1 hover:bg-black hover:fill-white"
                        rel="prev"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="w-10 h-10">
                            <path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/>
                        </svg>
                    </a>
                @endif
            </li>

            {{-- Page Numbers --}}
            @foreach ($paginator->getUrlRange(1, $paginator->lastPage()) as $page => $url)
                <li class="inline p-2 mt-2">
                    @if ($page == $paginator->currentPage())
                        <button
                            class="w-12 h-12 rounded-full items-center justify-center inline-flex bg-black border-none font-medium text-white"
                            aria-current="page"
                        >
                            {{ $page }}
                        </button>
                    @else
                        <a
                            href="{{ $url }}"
                            class="w-12 h-12 rounded-full items-center justify-center inline-flex bg-white border-none font-medium hover:bg-black hover:text-white"
                        >
                            {{ $page }}
                        </a>
                    @endif
                </li>
            @endforeach

            {{-- Next Page Link --}}
            <li class="inline p-2 mt-2">
                @if ($paginator->hasMorePages())
                    <a
                        href="{{ $paginator->nextPageUrl() }}"
                        class="border-none rounded-full p-2 inline-flex items-center justify-center font-medium shadow-custom-1 hover:bg-black hover:fill-white"
                        rel="next"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="w-10 h-10">
                            <path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/>
                        </svg>
                    </a>
                @else
                    <button
                        class="border-none rounded-full p-2 inline-flex items-center justify-center font-medium shadow-custom-3 fill-gray-300"
                        disabled
                        aria-disabled="true"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="w-10 h-10">
                            <path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/>
                        </svg>
                    </button>
                @endif
            </li>
        </ul>
    </nav>
@endif