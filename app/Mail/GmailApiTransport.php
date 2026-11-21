<?php

namespace App\Mail;

use App\Models\GmailConnection;
use App\Services\GmailApiService;
use RuntimeException;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

class GmailApiTransport extends AbstractTransport
{
    private ?GmailConnection $sendingConnection = null;

    public function __construct(private GmailApiService $gmail)
    {
        parent::__construct();
    }

    public function __toString(): string
    {
        return 'gmail_api';
    }

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        $connection = $this->gmail->connection();
        if (! $connection || ! $message instanceof Email) {
            throw new RuntimeException('Cần kết nối Gmail trước khi gửi email vé.');
        }
        $message = clone $message;
        $sender = new Address($connection->email, (string) config('mail.from.name', 'CinemaStar'));
        $message->from($sender)->sender($sender)->returnPath($connection->email);
        $envelope = new Envelope($sender, ($envelope ?? Envelope::create($message))->getRecipients());
        $this->sendingConnection = $connection;
        try {
            return parent::send($message, $envelope);
        } finally {
            $this->sendingConnection = null;
        }
    }

    protected function doSend(SentMessage $message): void
    {
        $connection = $this->sendingConnection;
        if (! $connection) {
            throw new RuntimeException('Kết nối Gmail không còn khả dụng.');
        }
        $mime = $message->toString();
        $original = $message->getOriginalMessage();
        if ($original instanceof Email && $original->getBcc() !== []) {
            // Gmail derives all recipients from MIME headers, unlike SMTP envelopes.
            $mime = $original->getHeaders()->get('Bcc')->toString()."\r\n".$mime;
        }
        $message->setMessageId($this->gmail->send($mime, $connection));
    }
}
