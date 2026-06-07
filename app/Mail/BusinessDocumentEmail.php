<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BusinessDocumentEmail extends Mailable
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
        public string $pdfBinary,
        public string $pdfFilename,
        public array $ccAddresses = [],
        public array $bccAddresses = [],
        public ?string $fromAddress = null,
        public ?string $fromName = null,
    ) {}

    public function build(): static
    {
        $m = $this->subject($this->subjectLine)
            ->html($this->htmlBody)
            ->attachData($this->pdfBinary, $this->pdfFilename, [
                'mime' => 'application/pdf',
            ]);

        if ($this->fromAddress) {
            $m->from($this->fromAddress, $this->fromName ?? '');
        }

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
