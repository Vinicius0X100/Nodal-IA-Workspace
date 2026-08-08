<?php

namespace App\Mail;

use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserAddedToOrganizationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public Organization $organization,
        public ?string $temporaryPassword = null,
        public array $groupNames = []
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Você foi adicionado à organização {$this->organization->name} no Nodal",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.users.added_to_organization',
            with: [
                'userName' => $this->user->name,
                'organizationName' => $this->organization->name,
                'email' => $this->user->email,
                'temporaryPassword' => $this->temporaryPassword,
                'groupNames' => $this->groupNames,
                'loginUrl' => route('login'),
            ],
        );
    }
}
