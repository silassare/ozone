<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

// `make benchmark-http`: the row the database route reads, in a table shaped like OZone's oz_countries.
#[ORM\Entity]
#[ORM\Table(name: 'countries')]
class Country
{
	#[ORM\Id]
	#[ORM\Column(length: 2)]
	public string $cc2;

	#[ORM\Column(length: 6)]
	public string $callingCode;

	#[ORM\Column(length: 255)]
	public string $name;

	#[ORM\Column(length: 255)]
	public string $nameReal;

	#[ORM\Column(type: 'json')]
	public array $data = [];

	#[ORM\Column]
	public bool $isValid = true;

	#[ORM\Column]
	public int $createdAt;

	#[ORM\Column]
	public int $updatedAt;

	#[ORM\Column]
	public bool $deleted = false;

	#[ORM\Column(nullable: true)]
	public ?int $deletedAt = null;
}
