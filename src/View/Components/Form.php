<?php

declare(strict_types=1);

namespace Sunrice\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Sunrice\Models\Form as FormModel;

/**
 * <x-sunrice::form handle="contact"> — renders a form posting to the
 * public submission endpoint. The slot receives the Form model (and
 * its fields schema), the shared $errors bag and $success.
 */
class Form extends Component
{
    public FormModel $form;

    public function __construct(string $handle)
    {
        $this->form = FormModel::query()->where('handle', $handle)->firstOrFail();
    }

    public function success(): bool
    {
        return (bool) session()->pull("sunrice_form_success.{$this->form->handle}");
    }

    public function render(): View
    {
        return view('sunrice::components.form');
    }
}
