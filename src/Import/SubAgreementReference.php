<?php

namespace App\Import;

/**
 * Finds the sub-agreement (delaftale) of a route name.
 *
 * A route name such as "Ø4-VB-01" or "TEST_01" has the sub-agreement before the last "-" or "_",
 * and the number of the route after it. The rule does not split the sub-agreement further, because
 * not all names have the same format. See "Route names and sub-agreements" in docs/architecture.md.
 */
final class SubAgreementReference
{
    /**
     * Gives the route name without the last "-" or "_" and the digits after it. A name without such
     * an end is a sub-agreement as a whole.
     */
    public static function fromRouteName(string $routeName): string
    {
        $name = trim($routeName);

        // The match starts at the first character, and "s" lets "." also match a line break.
        return 1 === preg_match('/(.+)[-_]\d+$/s', $name, $matches) ? $matches[1] : $name;
    }
}
