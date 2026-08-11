<?php

namespace App\Mail;

use App\Models\Mission;
use App\Models\ProfilRecherche;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MissionAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Mission $mission,
        public ProfilRecherche $profil,
        public int $score
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'MissionFinder - Nouvelle mission : '
                . $this->mission->titre
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.mission-alert'
        );
    }

    public function attachments(): array
    {
        return [];
    }
}