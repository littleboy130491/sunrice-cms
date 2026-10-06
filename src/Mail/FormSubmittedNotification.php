<?php

declare(strict_types=1);

namespace Sunrice\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Sunrice\Models\Form;
use Sunrice\Models\FormSubmission;

/**
 * Notification sent to a form's notify_emails after each stored
 * submission (T13.1). Queued.
 */
class FormSubmittedNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Form $form,
        public FormSubmission $submission,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "New submission: {$this->form->title}",
        );
    }

    public function content(): Content
    {
        $lines = [];
        foreach ($this->submission->data as $key => $value) {
            $lines[] = "{$key}: ".(is_scalar($value) ? (string) $value : json_encode($value));
        }

        return new Content(
            text: 'sunrice::mail.form-submission',
            with: ['form' => $this->form, 'lines' => $lines],
        );
    }
}
