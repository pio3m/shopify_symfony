<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'webhook_logs')]
#[ORM\UniqueConstraint(name: 'uniq_webhook_id', columns: ['webhook_id'])]
class WebhookLog
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private string $topic;

    #[ORM\Column(length: 255)]
    private string $shopDomain;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $webhookId = null; // X-Shopify-Webhook-Id

    #[ORM\Column(type: 'text')]
    private string $payload;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $receivedAt;

    public function __construct(string $topic, string $shopDomain, ?string $webhookId, string $payload)
    {
        $this->topic      = $topic;
        $this->shopDomain = strtolower($shopDomain);
        $this->webhookId  = $webhookId;
        $this->payload    = $payload;
        $this->receivedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
}
