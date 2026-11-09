<?php

declare(strict_types=1);

namespace Vanda\Support;

/**
 * "Did you mean …?" for names a developer (or an agent) misspelt: a field path, an icon, a modal.
 *
 *     Suggest::closest('emial', ['name', 'email']); // 'email'
 */
final class Suggest
{
    /**
     * The candidate nearest to $name, when it is near enough to be a typo (a couple of letters, the
     * same word in another case) or one of the two holds the other ("trashcan" → "trash"); null otherwise.
     *
     * @param list<string> $candidates
     */
    public static function closest(string $name, array $candidates): ?string
    {
        $best = null;
        $bestDistance = PHP_INT_MAX;

        foreach ($candidates as $candidate) {
            if (strcasecmp($candidate, $name) === 0) {
                return $candidate;
            }
            $distance = levenshtein(strtolower($name), strtolower($candidate));
            if ($distance < $bestDistance) {
                $best = $candidate;
                $bestDistance = $distance;
            }
        }

        $allowed = max(2, intdiv(strlen($name), 3));
        if ($best !== null && $bestDistance <= $allowed) {
            return $best;
        }

        // One word inside the other: the one nearest in length is the likeliest.
        $within = array_filter($candidates, static fn (string $candidate) => strlen($candidate) >= 3 && strlen($name) >= 3
            && (str_contains(strtolower($name), strtolower($candidate)) || str_contains(strtolower($candidate), strtolower($name))));
        usort($within, static fn (string $a, string $b) => abs(strlen($a) - strlen($name)) <=> abs(strlen($b) - strlen($name)));

        return $within[0] ?? null;
    }
}
