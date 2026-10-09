<?php

declare(strict_types=1);

namespace Sunrice\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\Support\ViewErrorBag;
use Illuminate\View\Component;
use Sunrice\Models\Form as FormModel;
use Sunrice\Support\Captcha;
use Sunrice\Support\Locales;

/**
 * <x-sunrice::form handle="contact"> — renders a form posting to the
 * public submission endpoint. Inside the slot, $component is this
 * component: $component->form (the Form model and its fields),
 * $component->error('handle') and $component->old('handle') give this
 * form's validation message and previous input, so two forms on one
 * page don't show each other's errors. When the form requires a captcha,
 * the component adds the widget above the submit button.
 */
class Form extends Component
{
    public FormModel $form;

    public string $anchor;

    public string $locale;

    protected ?bool $success = null;

    public function __construct(string $handle)
    {
        $this->form = FormModel::query()->where('handle', $handle)->firstOrFail();
        $this->anchor = 'sunrice-form-'.$this->form->handle;
        $this->locale = Locales::current();
    }

    public function success(): bool
    {
        return $this->success ??= (bool) session()->pull("sunrice_form_success.{$this->form->handle}");
    }

    /** Whether the previous request was a submission of this form. */
    public function submitted(): bool
    {
        return session()->getOldInput('_form') === $this->form->handle;
    }

    /** This form's validation message for a field, if any. */
    public function error(string $field): ?string
    {
        if (! $this->submitted()) {
            return null;
        }

        /** @var ViewErrorBag|null $errors */
        $errors = session('errors');

        return $errors?->first('data.'.$field) ?: null;
    }

    /**
     * The captcha widget to show, or null when this form doesn't use one.
     *
     * @return array{provider: string, class: string, site_key: string, script: string, field: string}|null
     */
    public function captcha(): ?array
    {
        return Captcha::requiredFor($this->form) ? Captcha::widget($this->locale) : null;
    }

    /** The captcha's message after a failed submission of this form. */
    public function captchaError(): ?string
    {
        if (! $this->submitted()) {
            return null;
        }

        /** @var ViewErrorBag|null $errors */
        $errors = session('errors');

        return $errors?->first('_captcha') ?: null;
    }

    /** The value this form's field was last submitted with. */
    public function old(string $field, mixed $default = null): mixed
    {
        return $this->submitted() ? session()->getOldInput('data.'.$field, $default) : $default;
    }

    public function render(): View
    {
        return view('sunrice::components.form');
    }
}
