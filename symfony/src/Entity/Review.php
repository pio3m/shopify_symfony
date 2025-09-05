<?php

namespace App\Entity;

use App\Repository\ReviewRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReviewRepository::class)]
#[ORM\Table(name: 'review')]
#[ORM\UniqueConstraint(name: 'uniq_customer_product', columns: ['logged_in_customer_id', 'product_id'])]
class Review
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $loggedInCustomerId = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $pathPrefix = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $shop = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $signature = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $timestamp = null;

    #[ORM\Column(type: 'bigint', nullable: true)]
    private ?string $productId = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $rating = null; // 1..5; dla serduszka ustaw 1

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $text = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $createdAt = null;

    // --- gettery/settery (tylko nowe – reszta już masz) ---
    public function getProductId(): ?string { return $this->productId; }
    public function setProductId(?string $id): void { $this->productId = $id; }

    public function getRating(): ?int { return $this->rating; }
    public function setRating(?int $r): void { $this->rating = $r; }

    public function getText(): ?string { return $this->text; }
    public function setText(?string $t): void { $this->text = $t; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(?\DateTimeImmutable $d): void { $this->createdAt = $d; }
}
