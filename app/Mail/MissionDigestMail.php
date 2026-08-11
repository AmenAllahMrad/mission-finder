<?php

namespace App\Mail;

use App\Models\Alerte;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class MissionDigestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Alerte $alerte,
        public Collection $missions,
        public array $scores
    ) {
    }

    public function envelope(): Envelope
    {
        $frequence = $this->alerte->frequence === 'daily'
            ? 'Daily Digest'
            : 'Weekly Digest';

        return new Envelope(
            subject: 'MissionFinder - '
                . $frequence
                . ' - '
                . $this->missions->count()
                . ' mission(s)'
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.mission-digest'
        );
    }

    public function attachments(): array
    {
        return [];
    }
}