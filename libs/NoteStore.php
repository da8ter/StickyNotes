<?php

declare(strict_types=1);

/**
 * Bausteine der Pinnwand ohne Symcon: prüft Zettel, die von der Visu oder aus Skripten kommen, und pflegt die Liste.
 * Ein Zettel: id, text, color, x/y (Mitte, Anteil am Fenster), w/h (Anteil an der kürzeren Fensterseite),
 * rot (Grad), z (Reihenfolge), strokes (Handschrift als Linien: Stiftfarbe c, Punkte p = x0,y0,x1,y1,… in 0..1000
 * bezogen auf den Zettel), rev, ts.
 */
final class NoteStore
{
    public const MAX_NOTES = 30;
    public const MAX_TEXT = 600;
    public const MAX_STROKES = 400;
    public const MAX_POINTS = 2000;
    public const MAX_INK_BYTES = 48000;
    // Der Hook liefert höchstens 1 MiB aus: die ganze Liste muss darunter bleiben
    public const MAX_STORE_BYTES = 700000;
    public const COLORS = ['yellow', 'pink', 'green', 'blue'];
    public const PENS = 3;
    // Die zwei Teile eines Zettels: Kachel und Visu speichern nur den, den sie geändert haben
    private const PARTS = ['place' => ['x', 'y', 'w', 'h', 'rot', 'z'], 'content' => ['text', 'color', 'strokes']];

    public static function IsId(mixed $id): bool
    {
        return is_string($id) && preg_match('/^[a-f0-9]{8}$/', $id) === 1;
    }

    public static function NewId(): string
    {
        return bin2hex(random_bytes(4));
    }

    /** Geprüfter Zettel oder ein englischer Fehlerschlüssel. */
    public static function Normalize(mixed $in): array|string
    {
        if (!is_array($in) || !self::IsId($in['id'] ?? null)) {
            return 'Invalid note';
        }
        $text = $in['text'] ?? '';
        if (!is_string($text) || !mb_check_encoding($text, 'UTF-8')) {
            return 'Invalid text';
        }
        $text = mb_substr(str_replace("\r", '', $text), 0, self::MAX_TEXT);
        $color = $in['color'] ?? 'yellow';
        if (!in_array($color, self::COLORS, true)) {
            return 'Invalid color';
        }
        $strokes = [];
        $list = $in['strokes'] ?? [];
        if (!is_array($list) || !array_is_list($list) || count($list) > self::MAX_STROKES) {
            return 'Invalid handwriting';
        }
        foreach ($list as $stroke) {
            $points = is_array($stroke) ? ($stroke['p'] ?? null) : null;
            $pen = is_array($stroke) ? ($stroke['c'] ?? 0) : 0;
            if (!is_array($points) || !array_is_list($points) || count($points) < 2 || count($points) % 2 !== 0 || count($points) > self::MAX_POINTS
                || !is_int($pen) || $pen < 0 || $pen >= self::PENS) {
                return 'Invalid handwriting';
            }
            $clean = [];
            foreach ($points as $value) {
                if (!is_int($value) && !is_float($value)) {
                    return 'Invalid handwriting';
                }
                $clean[] = (int) min(1000, max(0, round($value)));
            }
            $strokes[] = ['c' => $pen, 'p' => $clean];
        }
        if (strlen((string) json_encode($strokes)) > self::MAX_INK_BYTES) {
            return 'Too much handwriting on this note';
        }
        $number = static fn (string $key, float $default, float $min, float $max): float
            => is_int($in[$key] ?? null) || is_float($in[$key] ?? null) ? round(min($max, max($min, (float) $in[$key])), 4) : $default;
        return [
            'id'      => $in['id'],
            'text'    => $text,
            'color'   => $color,
            'x'       => $number('x', 0.5, 0.0, 1.0),
            'y'       => $number('y', 0.5, 0.0, 1.0),
            'w'       => $number('w', 0.26, 0.08, 0.9),
            'h'       => $number('h', 0.26, 0.08, 0.9),
            'rot'     => $number('rot', 0.0, -6.0, 6.0),
            'z'       => (int) $number('z', 0.0, 0.0, 1e9),
            'strokes' => $strokes,
        ];
    }

    /**
     * Zettel einsetzen oder ersetzen; liefert [Liste, Fehler|null]. Mit `$part` (place|content) ändert sich an einem
     * vorhandenen Zettel nur dieser Teil: sonst überschriebe ein Bearbeiter mit seinem alten Stand, was der andere gerade tat.
     */
    public static function Put(array $list, array $note, int $now, string $part = ''): array
    {
        $found = false;
        foreach ($list as $i => $old) {
            if ($old['id'] === $note['id']) {
                foreach (self::PARTS[$part === 'place' ? 'content' : ($part === 'content' ? 'place' : '')] ?? [] as $key) {
                    $note[$key] = $old[$key];
                }
                $list[$i] = $note + ['rev' => (int) ($old['rev'] ?? 0) + 1, 'ts' => $now];
                $found = true;
            }
        }
        if (!$found) {
            if (count($list) >= self::MAX_NOTES) {
                return [$list, 'The board is full'];
            }
            $list[] = $note + ['rev' => 1, 'ts' => $now];
        }
        if (strlen((string) json_encode($list)) > self::MAX_STORE_BYTES) {
            return [$list, 'The board is full'];
        }
        return [$list, null];
    }

    public static function Remove(array $list, string $id): array
    {
        return array_values(array_filter($list, static fn (array $n): bool => $n['id'] !== $id));
    }

    /** Freier Platz für einen Zettel aus einem Skript: leicht versetzt, damit sie nicht deckungsgleich liegen. */
    public static function ScriptNote(array $list, string $text, string $color): array
    {
        $n = count($list);
        $z = 0;
        foreach ($list as $note) {
            $z = max($z, (int) $note['z']);
        }
        return ['id' => self::NewId(), 'text' => $text, 'color' => $color, 'x' => 0.3 + ($n % 5) * 0.1, 'y' => 0.3 + ($n % 4) * 0.12,
            'w' => 0.26, 'h' => 0.26, 'rot' => (($n * 37) % 7) - 3, 'z' => $z + 1, 'strokes' => []];
    }
}
