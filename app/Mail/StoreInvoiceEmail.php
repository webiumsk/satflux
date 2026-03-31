<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class StoreInvoiceEmail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  list<string>  $toAddresses
     * @param  list<string>  $ccAddresses
     * @param  list<string>  $bccAddresses
     */
    public function __construct(
        public string $subjectLine,
        public string $htmlBody,
        public array $toAddresses,
        public array $ccAddresses = [],
        public array $bccAddresses = []
    ) {}

    public function build(): static
    {
        $m = $this->subject($this->subjectLine)->html($this->htmlBody);
        foreach ($this->toAddresses as $addr) {
            if ($addr !== '') {
                $m->to($addr);
            }
        }
        foreach ($this->ccAddresses as $addr) {
            if ($addr !== '') {
                $m->cc($addr);
            }
        }
        foreach ($this->bccAddresses as $addr) {
            if ($addr !== '') {
                $m->bcc($addr);
            }
        }

        return $m;
    }
}
