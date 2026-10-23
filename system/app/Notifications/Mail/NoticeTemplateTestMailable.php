<?php

declare(strict_types=1);

namespace App\Notifications\Mail;

use Illuminate\Mail\Mailable;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

class NoticeTemplateTestMailable extends Mailable
{
    public function __construct(public readonly array $rendered, public readonly ?array $pdf = null) {}

    public function build(): static
    {
        return $this->subject('[Synthetic test] '.$this->rendered['subject'])
            ->html($this->rendered['production_html'])
            ->withSymfonyMessage(function (Email $message): void {
                $message->text($this->rendered['text']);
                foreach ($this->rendered['assets'] as $asset) {
                    $part = new DataPart($asset['bytes'], $asset['filename'], $asset['mime']);
                    $part->asInline()->setContentId($asset['cid']);
                    $message->addPart($part);
                }
                if ($this->pdf !== null) {
                    $message->addPart(new DataPart($this->pdf['bytes'], 'synthetic-notice.pdf', 'application/pdf'));
                }
            });
    }
}
