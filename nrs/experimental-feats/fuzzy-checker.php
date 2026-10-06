<?php

function stripDiacritics(string $s): string
{
    if (class_exists('Normalizer')) {
        $n = Normalizer::normalize($s, Normalizer::FORM_D);
        if ($n === false) {
            $n = $s;
        }
    } else {
        $n = $s;
    }

    return preg_replace('/\p{M}/u', '', $n) ?? $s;
}

function foldAnswer(string $s, bool $ignoreDiacritics): string
{
    $t = mb_strtolower(trim($s), 'UTF-8');
    return $ignoreDiacritics ? stripDiacritics($t) : $t;
}

/**
 * Damerau-Levenshtein distance; DO NOT CALL ALOT
 */
function damerau(string $a, string $b): int
{
    $aa = mb_str_split($a);
    $bb = mb_str_split($b);
    $m = count($aa);
    $n = count($bb);

    $d = [];
    for ($i = 0; $i <= $m; $i++) {
        $d[$i] = array_fill(0, $n + 1, 0);
        $d[$i][0] = $i;
    }
    for ($j = 0; $j <= $n; $j++) {
        $d[0][$j] = $j;
    }

    for ($i = 1; $i <= $m; $i++) {
        for ($j = 1; $j <= $n; $j++) {
            $cost = ($aa[$i - 1] === $bb[$j - 1]) ? 0 : 1;
            $d[$i][$j] = min(
                $d[$i - 1][$j] + 1,
                $d[$i][$j - 1] + 1,
                $d[$i - 1][$j - 1] + $cost
            );
            if (
                $i > 1 && $j > 1
                && $aa[$i - 1] === $bb[$j - 2]
                && $aa[$i - 2] === $bb[$j - 1]
            ) {
                $d[$i][$j] = min($d[$i][$j], $d[$i - 2][$j - 2] + 1);
            }
        }
    }

    return $d[$m][$n];
}

function maxTypoDistance(int $len): int
{
    if ($len <= 4) {
        return 0;
    }
    if ($len <= 7) {
        return 1;
    }
    return 2;
}

/**
 * @return array{verdict: string, distance: int}
 * verdict is one of: exact, diacritic, typo, wrong
 */
function checkAnswer(
    string $input,
    string $expected,
    bool $ignoreDiacritics,
    bool $allowTypos
): array {
    $rawIn = mb_strtolower(trim($input), 'UTF-8');
    $rawEx = mb_strtolower(trim($expected), 'UTF-8');

    if ($rawIn === $rawEx) {
        return ['verdict' => 'exact', 'distance' => 0];
    }

    $inFold = foldAnswer($input, $ignoreDiacritics);
    $exFold = foldAnswer($expected, $ignoreDiacritics);

    if ($ignoreDiacritics && $inFold === $exFold) {
        return ['verdict' => 'diacritic', 'distance' => 0];
    }

    $dist = damerau($inFold, $exFold);

    if ($allowTypos && $dist > 0 && $dist <= maxTypoDistance(mb_strlen($exFold))) {
        return ['verdict' => 'typo', 'distance' => $dist];
    }

    return ['verdict' => 'wrong', 'distance' => $dist];
}