<?php

namespace App\Mail;

use App\Auth\AuthEmailCodePurpose;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class AuthEmailCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly AuthEmailCodePurpose $purpose,
        public readonly string $code,
        public readonly CarbonInterface $expiresAt
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->purpose->subject(),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.auth-email-code',
            with: [
                'headline' => $this->purpose->headline(),
                'description' => $this->purpose->description(),
                'code' => $this->code,
                'expiresAt' => $this->expiresAt,
            ],
        );
    }
}
