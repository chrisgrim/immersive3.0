@extends('Layouts.master-container')

@section('meta')
    

@endsection 

@section('nav')

    @if (Browser::isMobile())
        <vue-nav-bar-mobile></vue-nav-bar-mobile>
    @else
        @include('Nav.nav-limited')
    @endif
    
@endsection

@section('content')
    @if($organizer->status !== 'p')
        <div class="bg-amber-50 border-b border-amber-200">
            <div class="max-w-screen-xl mx-auto py-3 px-8">
                <div class="flex items-center justify-between gap-4">
                    <div class="flex items-center gap-2">
                        <svg style="width: 20px; height: 20px;" class="text-amber-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                        </svg>
                        <p class="text-lg font-medium text-amber-800">
                            This organization is pending review and is only visible to administrators and organization members
                        </p>
                    </div>
                </div>
            </div>
        </div>
    @endif
    
    <vue-hosting :organizer="{{$organizer}}" :user="user" />

@endsection

@section('footer')
    
@endsection 