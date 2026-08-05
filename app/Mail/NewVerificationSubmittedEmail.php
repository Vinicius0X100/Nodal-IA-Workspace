<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewVerificationSubmittedEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $organizationName;
    public $documentType;
    public $responsibleName;

    /**
     * Create a new message instance.
     */
    public function __construct(string $organizationName, string $documentType, string $responsibleName)
    {
        $this->organizationName = $organizationName;
        $this->documentType = $documentType;
        $this->responsibleName = $responsibleName;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Nova solicitação de KYC: {$this->organizationName}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.verifications.submitted',
            with: [
                'organizationName' => $this->organizationName,
                'documentType' => $this->documentType,
                'responsibleName' => $this->responsibleName,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
