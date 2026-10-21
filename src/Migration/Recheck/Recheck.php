<?php
/**
 * A standing rule about records an earlier import already wrote.
 *
 * When the answer to "what should this value be" changes between releases - which copy of an
 * ad's picture to use, or what text an ad should carry - the records a site imported under
 * the older answer keep their old values. Re-running the whole import would fix them, but a
 * site owner has no way to know that is worth doing, and reading every table again is not the
 * way to find out. A re-check says what changed, which steps have to be read again to apply
 * it, and whether this site has had it yet.
 *
 * Applying a re-check re-reads the older site for the steps named in stages(). It does not
 * invent values: each step derives a record from the older data exactly as an import would,
 * and the migration map recognises what it already created, so an existing record is updated
 * and nothing is created twice.
 *
 * @see \MillionDollarScript\V3\Migration\Recheck\Registry
 * @see tests/rewrite/migration-recheck-fixture.php
 */

namespace MillionDollarScript\V3\Migration\Recheck;

if (!defined('ABSPATH')) {
    exit;
}

interface Recheck {
    /**
     * Stable identity. Changing what a re-check does does not change its id; retiring one
     * and replacing it with a different rule gets a new id, so sites that already ran the
     * old version are not asked again.
     */
    public function id(): string;

    /**
     * The plugin release the rule landed in.
     */
    public function introducedIn(): string;

    /**
     * What this is, for the site owner.
     */
    public function title(): string;

    /**
     * What running it will do, in the site owner's terms rather than the code's.
     */
    public function summary(): string;

    /**
     * Why a site would want it, as the site owner would weigh it.
     */
    public function benefit(): string;

    /**
     * The import steps that must be read again for this rule to apply. A name that is not a
     * real step is a bug: Registry::stages_for() checks it before anything runs.
     *
     * @return list<string>
     */
    public function stages(): array;
}
