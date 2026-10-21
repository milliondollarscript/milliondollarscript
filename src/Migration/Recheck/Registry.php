<?php
/**
 * The standing rules a site may need brought up to date.
 *
 * A rule is registered here with the release that changed the answer and the import steps
 * that have to be read again for it to apply. Whether a site has had a rule applied is kept
 * per legacy source, because the Migration screen works one source at a time and a site can
 * in principle hold more than one older install.
 *
 * @see Recheck
 * @see \MillionDollarScript\V3\Migration\Recheck\Runner
 */

namespace MillionDollarScript\V3\Migration\Recheck;

use MillionDollarScript\V3\Migration\Importer;
use MillionDollarScript\V3\Support\Build;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class Registry {
    /**
     * Per-source record of which standing rules this site has already applied.
     */
    public const STATE_OPTION = 'mds3_migration_rechecks';

    /**
     * Every rule this version of the plugin knows about.
     *
     * @return list<Recheck>
     */
    public static function all(): array {
        $rechecks = [
            new ImageCopyRecheck(),
        ];

        return array_values((array) \MillionDollarScript\Core\Hooks::apply('million-dollar-script/migration/rechecks', $rechecks));
    }

    public static function find(string $id): ?Recheck {
        foreach (self::all() as $recheck) {
            if ($recheck->id() === $id) {
                return $recheck;
            }
        }

        return null;
    }

    /**
     * Which rules this source has already been given, and what each one did.
     *
     * @return array<string, array>
     */
    public static function appliedFor(string $source_prefix): array {
        $state = get_option(self::STATE_OPTION, []);
        $state = is_array($state) ? $state : [];
        $applied = is_array($state[$source_prefix] ?? null) ? $state[$source_prefix] : [];

        // A rule that no longer exists stops being mentioned; a rule that exists but was
        // never run is reported as pending, not as failed.
        $known = [];
        foreach (self::all() as $recheck) {
            if (isset($applied[$recheck->id()]) && is_array($applied[$recheck->id()])) {
                $known[$recheck->id()] = $applied[$recheck->id()];
            }
        }

        return $known;
    }

    /**
     * @return list<Recheck>
     */
    public static function pendingFor(string $source_prefix): array {
        $applied = self::appliedFor($source_prefix);
        $pending = [];
        foreach (self::all() as $recheck) {
            if (!isset($applied[$recheck->id()])) {
                $pending[] = $recheck;
            }
        }

        return $pending;
    }

    public static function record(string $source_prefix, string $id, array $result): void {
        $state = get_option(self::STATE_OPTION, []);
        if (!is_array($state)) {
            $state = [];
        }
        $for_source = is_array($state[$source_prefix] ?? null) ? $state[$source_prefix] : [];
        $for_source[$id] = [
            'at' => current_time('mysql', true),
            'plugin_version' => Build::version(),
            'result' => $result,
        ];
        $state[$source_prefix] = $for_source;
        update_option(self::STATE_OPTION, $state, false);
    }

    /**
     * A source is being removed, or re-imported from scratch: its applied record goes with it.
     */
    public static function forget(string $source_prefix): void {
        $state = get_option(self::STATE_OPTION, []);
        if (!is_array($state)) {
            return;
        }

        unset($state[$source_prefix]);
        update_option(self::STATE_OPTION, $state, false);
    }

    /**
     * The steps a rule needs re-read, checked against the steps this build actually has.
     *
     * A rule that names a step this version does not have would re-read the wrong things -
     * or nothing - so it is refused rather than guessed at.
     *
     * @return list<string>|WP_Error
     */
    public static function stagesFor(Recheck $recheck) {
        $available = Importer::import_stages();
        $stages = [];
        foreach ((array) $recheck->stages() as $stage) {
            $stage = sanitize_key((string) $stage);
            if (!in_array($stage, $available, true)) {
                return new WP_Error('mds3_recheck_unknown_stage', sprintf('Re-check %1$s names a step this version does not have: %2$s.', $recheck->id(), $stage));
            }
            if (!in_array($stage, $stages, true)) {
                $stages[] = $stage;
            }
        }

        if (!$stages) {
            return new WP_Error('mds3_recheck_without_stages', sprintf('Re-check %s names no steps to re-read.', $recheck->id()));
        }

        // In import order, so a walk starting at the earliest one still covers the rest.
        usort($stages, static function ($a, $b) use ($available) {
            return array_search($a, $available, true) <=> array_search($b, $available, true);
        });

        return $stages;
    }
}
