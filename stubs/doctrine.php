<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms {
    if (!class_exists(AbstractPlatform::class)) {
        abstract class AbstractPlatform
        {
            public function getName(): string
            {
                return 'doctrine-platform-stub';
            }
        }
    }
}

namespace Doctrine\ORM {
    if (!class_exists(QueryBuilder::class)) {
        class QueryBuilder
        {
            /**
             * @return list<string>
             */
            public function getRootAliases(): array
            {
                return [];
            }
        }
    }
}
