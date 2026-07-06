<?php

namespace SparrowhawkLabs\Jess\Exceptions;

use Illuminate\Database\QueryException;
use RuntimeException;
use Throwable;

/**
 * Wraps whatever a fragment closure throws while running against jess's
 * SQLite demo connection, so the failure reads as "fragment X broke, here's
 * why" instead of a bare PDO/QueryException trace with no jess context.
 *
 * Cannot *prevent* MySQL-specific SQL (FULLTEXT, JSON operators, stored
 * procedures, …) from being written into a fragment — that's arbitrary PHP,
 * not something static analysis can see through before running it. What this
 * *can* do is recognize the runtime signature SQLite leaves behind when such
 * a call fails (`no such function: …` is the reliable one) and say so
 * plainly, pointing at README § Requirements, rather than leaving the reader
 * to work out on their own that "no such function: match" means "this only
 * exists in MySQL."
 */
class FragmentExecutionException extends RuntimeException
{
    public static function forFragment(string $name, Throwable $previous): self
    {
        $hint = self::likelyEngineIncompatibilityHint($previous);

        $message = "jess fragment '{$name}' threw while running against the demo SQLite connection: "
            . $previous->getMessage();

        if ($hint !== null) {
            $message .= "\n\n{$hint}";
        }

        return new self($message, 0, $previous);
    }

    /**
     * SQLite's own "no such function: X" is a strong, specific signal that
     * the query called something SQLite doesn't implement — almost always a
     * MySQL/PostgreSQL-only function (MATCH/AGAINST, JSON_EXTRACT-style
     * operators, etc.) or a stored procedure CALL. Deliberately narrow: a
     * bare "syntax error" or "no such column" is just as often a genuine
     * typo in the fragment, so guessing "engine incompatibility" there would
     * mislead more often than it'd help.
     */
    private static function likelyEngineIncompatibilityHint(Throwable $previous): ?string
    {
        if (! $previous instanceof QueryException) {
            return null;
        }

        if (! preg_match('/no such function:\s*([A-Za-z0-9_]+)/i', $previous->getMessage(), $m)) {
            return null;
        }

        $function = $m[1];

        return "This looks like a MySQL/PostgreSQL-specific SQL feature (function \"{$function}\") that "
            . "SQLite doesn't implement — e.g. FULLTEXT MATCH/AGAINST, a JSON operator, or a stored "
            . 'procedure CALL. Jess runs the demo environment on SQLite regardless of your production '
            . 'database engine; see README § Requirements for what that does and doesn\'t support.';
    }
}
