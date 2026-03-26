<?php

declare(strict_types=1);

namespace App\Application\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class SubscribeRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['journalist', 'tag', 'section'])]
        public string $entityType,

        #[Assert\NotBlank]
        public string $entityId,
    ) {}
}
