{{-- Inside a class component's own view, public properties ($form) and methods ($success()) are plain variables. --}}
@if ($success())
    <div class="sunrice-form-success">{{ $form->setting('success_message', 'Thank you!') }}</div>
@else
    <form method="POST" action="{{ route('sunrice.frontend.forms.submit', $form->handle) }}" enctype="multipart/form-data" {{ $attributes }}>
        @csrf
        <x-honeypot />
        {{ $slot }}
        <button type="submit">Submit</button>
    </form>
@endif
