<?php

namespace App\Notifications;

use App\Models\Company;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionExpiring extends Notification
{
    public function __construct(public readonly Company $company) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Langganan Anda berakhir dalam 3 hari')
            ->greeting('Halo '.$notifiable->name.',')
            ->line('Langganan '.$this->company->name.' berakhir pada '.$this->company->paid_until->format('d F Y').'.')
            ->line('Perpanjang sebelum jatuh tempo agar Employee Anda tetap bisa mengakses sistem.')
            ->action('Perpanjang Langganan', route('subscription.index'))
            ->line('Terima kasih.');
    }
}
