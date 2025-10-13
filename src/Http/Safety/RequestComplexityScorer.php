<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Http\Safety;

use AlexFigures\Symfony\Query\Criteria;

final class RequestComplexityScorer
{
    public function score(Criteria $criteria): int
    {
        $score = 0;

        foreach ($criteria->include as $path) {
            $depth = substr_count($path, '.') + 1;
            $score += $depth * $depth;
        }

        foreach ($criteria->fields as $fields) {
            $score += count($fields);
        }

        $score += count($criteria->sort) * 2;
        $score += $criteria->pagination->size;

        return $score;
    }
}
