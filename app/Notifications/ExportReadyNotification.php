<?php

namespace App\Notifications;

use App\Models\Export;
use App\Models\Store;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * Notification when a manual (on-demand) export is ready.
 * Sends direct download link since manual exports are not shown in Reports.
 */
class ExportReadyNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Export $export,
        public Store $store,
        public string $label
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appUrl = rtrim(config('app.url', 'http://localhost:8080'), '/');
        $downloadUrl = $this->export->signed_url
            ? $this->export->signed_url
            : "{$appUrl}/stores/{$this->store->id}?section=reports";

        $mail = (new MailMessage)
            ->subject("Export ready – {$this->store->name}")
            ->line("Your invoice export is ready.")
            ->line("Store: {$this->store->name}")
            ->action('Download export', $downloadUrl)
            ->line('Thank you for using our service!');

        if ($this->export->file_path && Storage::disk('local')->exists($this->export->file_path)) {
            $fullPath = Storage::disk('local')->path($this->export->file_path);
            $ext = pathinfo($this->export->file_path, PATHINFO_EXTENSION);
            $filename = "export-{$this->store->name}-{$this->label}.{$ext}";
            $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
            $mail->attach($fullPath, ['as' => $filename]);
        }

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'export_id' => $this->export->id,
            'store_id' => $this->store->id,
        ];
    }
}
