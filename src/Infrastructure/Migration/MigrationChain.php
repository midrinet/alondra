<?php
/**
 * A migration chain
 *
 * @package    Midrinet/Alondra
 * @subpackage Midrinet/Alondra/Infrastructure/Migration
 */

namespace Midrinet\Alondra\Infrastructure\Migration;

/**
 * One registry of migrations and the option its cursor is stored in
 *
 * Each chain advances its own cursor, so an add-on migrates its own storage without touching free's.
 *
 * @since      2.0.0
 */
class MigrationChain {

	/**
	 * Name the failure record, the log and the failure action identify the chain by.
	 */
	public string $name;

	/**
	 * Option holding the highest id applied from this chain.
	 */
	public string $cursor_option;

	/**
	 * Migrations keyed by permanent id, declared in ascending order. No id may be 0, the "nothing applied" cursor.
	 *
	 * @var non-empty-array<int, class-string<Migration>>
	 */
	public array $migrations;

	/**
	 * @param string                                        $name          Chain name.
	 * @param string                                        $cursor_option Cursor option name.
	 * @param non-empty-array<int, class-string<Migration>> $migrations    Registry, keyed by id.
	 */
	public function __construct( string $name, string $cursor_option, array $migrations ) {
		$this->name          = $name;
		$this->cursor_option = $cursor_option;
		$this->migrations    = $migrations;
	}
}
