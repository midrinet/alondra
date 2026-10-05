<?php
/**
 * The Migration Interface
 *
 * @package    Midrinet/Alondra
 * @subpackage Midrinet/Alondra/Infrastructure/Migration
 */

namespace Midrinet\Alondra\Infrastructure\Migration;

/**
 * A single schema or data migration.
 *
 * Implementations are registered against an integer id, which is the only thing that orders them and the only
 * thing the stored cursor records. The id lives in the registry, never on the class.
 *
 * @since      1.0.3
 */
interface Migration {

	/**
	 * Apply this migration.
	 *
	 * Must tolerate failing part-way through itself. The cursor is advanced only once this method returns, so a
	 * migration that throws half-done is re-entered from the top on the next request, with everything it already
	 * did still in place. Guard each step on the state it produces rather than assuming it runs once: check for
	 * the table before creating it, for the column before adding it, and exclude already-migrated rows in the
	 * `WHERE` clause of a backfill.
	 *
	 * Throw to abort. The runner leaves the cursor untouched, so the next request retries this migration and the
	 * ones after it never run out of order.
	 *
	 * @return void
	 */
	public function execute(): void;
}
