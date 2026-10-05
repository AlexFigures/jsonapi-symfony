<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Bridge\Doctrine\Identifier;

use AlexFigures\Symfony\Resource\Metadata\ResourceMetadata;
use AlexFigures\Symfony\Resource\Metadata\ResourceMetadataValidatorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

final readonly class DoctrineIdentifierMetadataValidator implements ResourceMetadataValidatorInterface
{
    public function __construct(private ManagerRegistry $managers)
    {
    }

    public function validate(ResourceMetadata $resource): void
    {
        $manager = $this->managers->getManagerForClass($resource->dataClass);
        if (!$manager instanceof EntityManagerInterface) {
            return;
        }
        if (count($manager->getClassMetadata($resource->dataClass)->getIdentifierFieldNames()) > 1) {
            throw new \LogicException(sprintf(
                'JSON:API resource "%s" maps to entity %s, which uses a composite Doctrine identifier. Composite identifiers are not currently supported by the built-in Doctrine provider. Expose a single API identifier or use a custom data provider.',
                $resource->type,
                $resource->dataClass,
            ));
        }
    }
}
