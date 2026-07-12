<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Admin alert for failed/recovered system health checks (P1 phase 8).
 * Text-only; check details are short non-sensitive strings.
 */
class SystemHealthAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, array{ok: bool, detail: string}>  $checks
     * @param  list<string>  $failed  check names currently failing
     * @param  list<string>  $recovered  check names that just recovered
     */
    public function __construct(
        public array $checks,
        public array $failed,
        public array $recovered,
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->failed === []
            ? '[Satflux] System health recovered'
            : '[Satflux] System health alert: '.implode(', ', $this->failed);

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(text: 'emails.system-health-alert');
    }
}
