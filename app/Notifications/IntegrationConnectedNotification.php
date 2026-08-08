<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Integrations\Models\Integration;
use Illuminate\Database\Eloquent\Collection;

class IntegrationConnectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public Organization $organization;
    public Integration $integration;
    public Collection $tools;

    /**
     * Create a new notification instance.
     */
    public function __construct(Organization $organization, Integration $integration, Collection $tools)
    {
        $this->organization = $organization;
        $this->integration = $integration;
        $this->tools = $tools;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
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
        $providerName = $this->integration->display_name;
        
        // Define logo URL baseada no provider
        $logoFilename = null;
        if ($this->integration->provider === 'google_workspace') {
            $logoFilename = 'google-logo.svg';
        } elseif (in_array($this->integration->provider, ['microsoft_365', 'microsoft'])) {
            $logoFilename = 'microsoft-logo.svg';
        }
        
        $logoUrl = $logoFilename ? config('app.url') . "/images/{$logoFilename}" : null;

        return (new MailMessage)
            ->subject("Integração conectada com sucesso: {$providerName}")
            ->view('mail.integration-connected', [
                'organization' => $this->organization,
                'integration' => $this->integration,
                'tools' => $this->tools,
                'providerName' => $providerName,
                'logoUrl' => $logoUrl,
                'notifiable' => $notifiable,
            ]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
