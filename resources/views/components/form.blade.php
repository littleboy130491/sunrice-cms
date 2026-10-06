{{-- Inside a class component's own view, public properties ($form) and methods ($success()) are plain variables. --}}
@if ($success())
    <div class="sunrice-form-success" id="{{ $anchor }}" role="status">{{ $form->setting('success_message') ?: __('Thank you!') }}</div>
@else
    <form method="POST" action="{{ route('sunrice.frontend.forms.submit', $form->handle) }}" enctype="multipart/form-data" id="{{ $anchor }}" {{ $attributes }}>
        @csrf
        <x-honeypot />
        <input type="hidden" name="_form" value="{{ $form->handle }}">
        <input type="hidden" name="_locale" value="{{ $locale }}">
        {{ $slot }}
        <button type="submit">{{ __('Submit') }}</button>
    </form>
@endif
