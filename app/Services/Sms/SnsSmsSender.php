<?php

namespace App\Services\Sms;

use Aws\Sns\SnsClient;

/**
 * Sends text messages through Amazon SNS. The account must be out of the SMS
 * sandbox (or the number added to the sandbox) and have a spending limit high
 * enough; SNS refuses the message otherwise and the AWS error is thrown.
 */
class SnsSmsSender implements SmsSender
{
    public function __construct(
        private readonly SnsClient $client,
        private readonly ?string $senderId = null,
    ) {}

    /**
     * @param  array{key?: string|null, secret?: string|null, region?: string|null, sender_id?: string|null}  $config  config('services.sns')
     */
    public static function fromConfig(array $config): self
    {
        $options = [
            'version' => 'latest',
            'region' => $config['region'] ?? 'us-east-1',
            'http' => ['connect_timeout' => 5, 'timeout' => 10],
        ];

        // Without a key the SDK looks for the server's IAM role.
        if (filled($config['key'] ?? null) && filled($config['secret'] ?? null)) {
            $options['credentials'] = ['key' => $config['key'], 'secret' => $config['secret']];
        }

        return new self(new SnsClient($options), filled($config['sender_id'] ?? null) ? $config['sender_id'] : null);
    }

    public function send(string $phone, string $message): void
    {
        $attributes = [
            // Delivered first and around the clock: a code nobody receives is useless.
            'AWS.SNS.SMS.SMSType' => ['DataType' => 'String', 'StringValue' => 'Transactional'],
        ];

        if ($this->senderId !== null) {
            $attributes['AWS.SNS.SMS.SenderID'] = ['DataType' => 'String', 'StringValue' => $this->senderId];
        }

        $this->client->publish([
            'PhoneNumber' => $phone,
            'Message' => $message,
            'MessageAttributes' => $attributes,
        ]);
    }
}
