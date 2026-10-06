{{--
    Fieldset `form` — fields: `heading` (text), `form` (text: the handle of a
    form created under Forms, e.g. "contact").

    <x-sunrice::form> adds the action URL, CSRF token, spam honeypot, the
    submit button and the success message. You write the inputs; name them
    data[field_handle]. $component->form->fields lists the form's fields.
--}}
<section class="block" id="block-{{ $block->id }}">
    @if ($block->heading)
        <h2>{{ $block->heading }}</h2>
    @endif

    <x-sunrice::form :handle="$block->form">
        @foreach ($component->form->fields as $field)
            @php($name = 'data['.$field['handle'].']')
            @php($error = $errors->first('data.'.$field['handle']))
            <label for="f-{{ $field['handle'] }}">{{ $field['label'] ?? $field['handle'] }}</label>

            @switch($field['type'])
                @case('textarea')
                    <textarea id="f-{{ $field['handle'] }}" name="{{ $name }}" rows="5" @required($field['required'] ?? false)>{{ old('data.'.$field['handle']) }}</textarea>
                    @break
                @case('select')
                    <select id="f-{{ $field['handle'] }}" name="{{ $name }}" @required($field['required'] ?? false)>
                        {{-- Options are stored as [{value, label}] or as a plain list of values. --}}
                        @foreach ($field['config']['options'] ?? [] as $option)
                            @php([$value, $label] = is_array($option) ? [$option['value'] ?? '', $option['label'] ?? $option['value'] ?? ''] : [$option, $option])
                            <option value="{{ $value }}" @selected(old('data.'.$field['handle']) == $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @break
                @case('toggle')
                    <input type="hidden" name="{{ $name }}" value="0">
                    <input id="f-{{ $field['handle'] }}" type="checkbox" name="{{ $name }}" value="1" @checked(old('data.'.$field['handle']))>
                    @break
                @case('file')
                    <input id="f-{{ $field['handle'] }}" type="file" name="{{ $name }}" @required($field['required'] ?? false)>
                    @break
                @default
                    <input id="f-{{ $field['handle'] }}"
                           type="{{ ['number' => 'number', 'date' => 'date'][$field['type']] ?? (str_contains($field['handle'], 'email') ? 'email' : 'text') }}"
                           name="{{ $name }}" value="{{ old('data.'.$field['handle']) }}" @required($field['required'] ?? false)>
            @endswitch

            @if ($error)
                <p class="error">{{ $error }}</p>
            @endif
        @endforeach
    </x-sunrice::form>
</section>
