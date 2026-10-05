<?php
/**
 * Wrapper for wp upgrade script functions
 *
 * @package    Midrinet/Alondra
 * @subpackage Midrinet/Alondra/Infrastructure/Wp
 */

namespace Midrinet\Alondra\Infrastructure\Wp;

/**
 * Wrapper for wp upgrade script functions
 */
class UpgradeWrapper {
	/**
	 * Wrapper for dbDelta function inside wp-admin/includes/upgrade.php
	 *
	 * @param string $sql SQL query.
	 * @param bool   $execute Optional. Execute query. Default true.
	 * @return array<string, string>
	 */
	public function db_delta( $sql, $execute = true ) {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		return dbDelta( $sql, $execute );
	}
}
