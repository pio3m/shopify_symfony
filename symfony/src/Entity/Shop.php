<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'shop')]
class Shop
{
	#[ORM\Id]
	#[ORM\GeneratedValue]
	#[ORM\Column(type: 'integer')]
	private ?int $id = null;

	#[ORM\Column(type: 'string', length: 255, unique: true)]
	private string $shopDomain;

	#[ORM\Column(type: 'string', length: 255, nullable: true)]
	private ?string $accessToken = null;

	#[ORM\Column(type: 'datetime_immutable')]
	private \DateTimeImmutable $createdAt;

	public function __construct(string $shopDomain = null, string $accessToken = null)
	{
		$this->shopDomain = $shopDomain;
        $this->accessToken = $accessToken;
		$this->createdAt = new \DateTimeImmutable();
	}

	public function getId(): ?int
	{
		return $this->id;
	}

	public function getShopDomain(): string
	{
		return $this->shopDomain;
	}

	public function setShopDomain(string $shopDomain): void
	{
		$this->shopDomain = $shopDomain;
	}

	public function getAccessToken(): ?string
	{
		return $this->accessToken;
	}

	public function setAccessToken(?string $accessToken): void
	{
		$this->accessToken = $accessToken;
	}

	public function getCreatedAt(): \DateTimeImmutable
	{
		return $this->createdAt;
	}
}
