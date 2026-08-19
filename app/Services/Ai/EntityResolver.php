<?php

namespace App\Services\Ai;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class EntityResolver
{
    public function closest(string $type, string $needle): ?Model
    {
        $modelClass = match ($type) {
            'product' => Product::class,
            'supplier' => Supplier::class,
            'staff' => User::class,
            'category' => Category::class,
            default => null,
        };

        if ($modelClass === null) {
            return null;
        }

        $needle = $this->normalize($needle);

        if ($needle === '') {
            return null;
        }

        $best = null;
        $bestScore = 0.0;
        $secondScore = 0.0;

        foreach ($modelClass::query()->select(['id', 'name'])->orderBy('id')->cursor() as $candidate) {
            $candidateName = $this->normalize((string) $candidate->name);

            if ($candidateName === '') {
                continue;
            }

            if ($candidateName === $needle) {
                return $candidate;
            }

            $score = $this->similarity($needle, $candidateName);

            if ($score > $bestScore) {
                $secondScore = $bestScore;
                $bestScore = $score;
                $best = $candidate;
            } elseif ($score > $secondScore) {
                $secondScore = $score;
            }
        }

        $minimumScore = mb_strlen($needle) <= 4 ? 0.84 : 0.72;

        if ($best === null || $bestScore < $minimumScore) {
            return null;
        }

        if ($secondScore > 0.0 && ($bestScore - $secondScore) < 0.06) {
            return null;
        }

        return $best;
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[\x{2018}\x{2019}\x{201C}\x{201D}"\']+/u', '', $value) ?? $value;
        $value = preg_replace('/[^\p{L}\p{N}\s\-_.]+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    private function similarity(string $left, string $right): float
    {
        $leftChars = preg_split('//u', $left, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $rightChars = preg_split('//u', $right, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $maxLength = max(count($leftChars), count($rightChars));

        if ($maxLength === 0) {
            return 1.0;
        }

        if (str_contains($left, $right) || str_contains($right, $left)) {
            $shorter = min(count($leftChars), count($rightChars));

            if ($shorter >= 4) {
                return max(0.86, $shorter / $maxLength);
            }
        }

        $distance = $this->unicodeLevenshtein($leftChars, $rightChars);

        return max(0.0, 1 - ($distance / $maxLength));
    }

    /**
     * @param array<int, string> $left
     * @param array<int, string> $right
     */
    private function unicodeLevenshtein(array $left, array $right): int
    {
        if ($left === []) {
            return count($right);
        }

        if ($right === []) {
            return count($left);
        }

        $previous = range(0, count($right));

        foreach ($left as $i => $leftChar) {
            $current = [$i + 1];

            foreach ($right as $j => $rightChar) {
                $current[] = min(
                    $current[$j] + 1,
                    $previous[$j + 1] + 1,
                    $previous[$j] + ($leftChar === $rightChar ? 0 : 1),
                );
            }

            $previous = $current;
        }

        return $previous[count($right)];
    }
}
