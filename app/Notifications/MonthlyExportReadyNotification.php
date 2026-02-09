<?php

namespace App\Notifications;

use App\Models\Export;
use App\Models\Store;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

class MonthlyExportReadyNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Export $export,
        public Store $store,
        public string $monthLabel
    ) {
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $appUrl = rtrim(config('app.url', 'http://localhost:8080'), '/');
        $exportsUrl = "{$appUrl}/stores/{$this->store->id}?section=reports";

        $mail = (new MailMessage)
            ->subject("Monthly export ready – {$this->store->name} ({$this->monthLabel})")
            ->line("Your automatic monthly export of settled invoices for {$this->monthLabel} is ready.")
            ->line("Store: {$this->store->name}")
            ->action('Download export', $exportsUrl)
            ->line('Thank you for using our service!');

        if ($this->export->file_path && Storage::disk('local')->exists($this->export->file_path)) {
            $fullPath = Storage::disk('local')->path($this->export->file_path);
            $ext = pathinfo($this->export->file_path, PATHINFO_EXTENSION);
            $filename = "export-{$this->store->name}-{$this->monthLabel}.{$ext}";
            $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
            $mail->attach($fullPath, ['as' => $filename]);
        }

        return $mail;
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'export_id' => $this->export->id,
            'store_id' => $this->store->id,
            'store_name' => $this->store->name,
            'month' => $this->monthLabel,
        ];
    }
}
