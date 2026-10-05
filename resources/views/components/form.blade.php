@if ($component->success())
    <div class="sunrice-form-success">{{ $component->form->setting('success_message', 'Thank you!') }}</div>
@else
    <form method="POST" action="{{ route('sunrice.frontend.forms.submit', $component->form->handle) }}" enctype="multipart/form-data">
        @csrf
        <x-honeypot />
        {{ $slot }}
        <button type="submit">Submit</button>
    </form>
@endif
