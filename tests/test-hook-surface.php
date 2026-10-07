<?php
/**
 * Hook surface Tests
 *
 * @package Midrinet\Alondra\Tests
 */

namespace Midrinet\Alondra\Tests;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use WP_UnitTestCase;

/**
 * Free's `alondra_*` hooks are the contract the add-on builds on, so the set free fires is pinned here:
 * a new hook, a dropped one or a changed `alondra_components` signature fails until this list changes on purpose.
 * The same scan keeps premium gating and textdomain loading out of free.
 */
class HookSurfaceTest extends WP_UnitTestCase {

	private const HOOKS = [
		'alondra_cache_key_context',
		'alondra_components',
		'alondra_controllers',
		'alondra_di_definitions',
		'alondra_loaded',
		'alondra_matching_args',
		'alondra_matching_order_columns',
		'alondra_migration_failed',
		'alondra_pricing_layout_views',
		'alondra_tier_price',
		'alondra_tiered_pricing_matches',
	];

	/**
	 * The retired seed filters, read once by the 2.0.0 import and nowhere else.
	 */
	private const LEGACY_IMPORT_HOOKS = [
		'alondra_default_prefs',
		'alondra_delete_data_on_uninstall',
	];

	// wp_org_gatekeeper is premium-build code: WordPress.org rejects an upload that contains it.
	private const BANNED = [ 'can_use_premium_code', 'is_free_plan', '__premium_only', 'load_plugin_textdomain', 'wp_org_gatekeeper' ];

	public function test_free_fires_exactly_the_documented_hooks(): void {
		$hooks = $this->fired_hooks();

		$names = array_keys( $hooks );
		sort( $names );
		$expected = array_merge( self::HOOKS, self::LEGACY_IMPORT_HOOKS );
		sort( $expected );
		$this->assertSame( $expected, $names );

		foreach ( self::LEGACY_IMPORT_HOOKS as $hook ) {
			$this->assertSame( [ 'src/Infrastructure/Migration/ImportSeedFiltersMigration.php' ], array_unique( array_column( $hooks[ $hook ], 'file' ) ), $hook );
		}
	}

	public function test_components_is_applied_with_two_arguments(): void {
		$calls = $this->fired_hooks()['alondra_components'];

		$this->assertNotEmpty( $calls );
		$this->assertSame( [ 2 ], array_values( array_unique( array_column( $calls, 'args' ) ) ) );
	}

	public function test_free_carries_no_premium_gating_or_textdomain_loading(): void {
		$hits = [];
		foreach ( $this->files( [ 'php', 'js' ], [ 'src', 'assets/js/src', 'alondra.php', 'uninstall.php' ] ) as $file ) {
			$source = (string) file_get_contents( $this->root() . '/' . $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown
			foreach ( self::BANNED as $token ) {
				if ( false !== strpos( $source, $token ) ) {
					$hits[] = "$file: $token";
				}
			}
		}

		$this->assertSame( [], $hits );
	}

	/**
	 * Every literal `alondra_*` hook fired by free, with the file of each call and the arguments it passes after the name.
	 *
	 * @return array<string, list<array{file: string, args: int}>>
	 */
	private function fired_hooks(): array {
		$hooks = [];
		foreach ( $this->files( [ 'php' ], [ 'src', 'alondra.php', 'uninstall.php' ] ) as $file ) {
			$source = (string) file_get_contents( $this->root() . '/' . $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown
			foreach ( self::hooks_in( $source ) as $call ) {
				$hooks[ $call['name'] ][] = [
					'file' => $file,
					'args' => $call['args'],
				];
			}
		}

		return $hooks;
	}

	/**
	 * Every literal `alondra_*` hook fired in $source, in order, with the arguments passed after the name.
	 *
	 * @param string $source PHP source.
	 * @return list<array{name: string, args: int}>
	 */
	private static function hooks_in( string $source ): array {
		$tokens = array_values(
			array_filter(
				token_get_all( $source ),
				static fn( $token ): bool => ! \is_array( $token ) || ! \in_array( $token[0], [ T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ], true )
			)
		);
		// PHP 8 reads `\apply_filters` as one token; PHP 7.4 as a separator and a T_STRING.
		$names  = [ T_STRING, \defined( 'T_NAME_FULLY_QUALIFIED' ) ? T_NAME_FULLY_QUALIFIED : T_STRING ];
		$firing = [ 'apply_filters', 'do_action', 'apply_filters_ref_array', 'do_action_ref_array' ];
		$calls  = [];
		foreach ( $tokens as $i => $token ) {
			if ( ! \is_array( $token ) || ! \in_array( $token[0], $names, true ) || ! \in_array( ltrim( $token[1], '\\' ), $firing, true ) ) {
				continue;
			}
			$name = $tokens[ $i + 2 ] ?? null;
			if ( '(' !== ( $tokens[ $i + 1 ] ?? null ) || ! \is_array( $name ) || T_CONSTANT_ENCAPSED_STRING !== $name[0] || 0 !== strpos( trim( $name[1], '\'"' ), 'alondra_' ) ) {
				continue;
			}
			$args = self::argument_count( $tokens, $i + 1 ) - 1;
			if ( '_ref_array' === substr( $token[1], -10 ) ) {
				// The arguments are the elements of the array literal after the name.
				$array = $tokens[ $i + 4 ] ?? null;
				$open  = \is_array( $array ) && T_ARRAY === $array[0] ? $i + 5 : $i + 4;
				$args  = '(' === ( $tokens[ $open ] ?? null ) || '[' === ( $tokens[ $open ] ?? null ) ? self::argument_count( $tokens, $open ) : $args;
			}
			$calls[] = [
				'name' => trim( $name[1], '\'"' ),
				'args' => $args,
			];
		}

		return $calls;
	}

	/**
	 * @dataProvider provide_firing_forms
	 *
	 * @param string $call  A hook call.
	 * @param int    $args  Arguments it passes after the name.
	 */
	public function test_the_extractor_reads_every_firing_form( string $call, int $args ): void {
		$this->assertSame(
			[
				[
					'name' => 'alondra_x',
					'args' => $args,
				],
			],
			self::hooks_in( "<?php namespace A;\n$call;" ) 
		);
	}

	public function provide_firing_forms(): array {
		return [
			'filter'                   => [ "apply_filters( 'alondra_x', \$a, \$b )", 2 ],
			'qualified filter'         => [ "\\apply_filters( 'alondra_x', \$a )", 1 ],
			'action'                   => [ "do_action( 'alondra_x' )", 0 ],
			'qualified action'         => [ "\\do_action( 'alondra_x', \$a )", 1 ],
			'filter by reference'      => [ "apply_filters_ref_array( 'alondra_x', array( \$a, \$b ) )", 2 ],
			'qualified action by ref.' => [ "\\do_action_ref_array( 'alondra_x', [ \$a ] )", 1 ],
		];
	}

	/**
	 * Count the top-level arguments of the call whose opening parenthesis is at $open.
	 *
	 * @param array<int, mixed> $tokens Tokens without whitespace or comments.
	 * @param int               $open   Index of the opening parenthesis.
	 */
	private static function argument_count( array $tokens, int $open ): int {
		$depth = 0;
		$args  = 1;
		for ( $i = $open, $count = \count( $tokens ); $i < $count; $i++ ) {
			$text = \is_array( $tokens[ $i ] ) ? $tokens[ $i ][1] : $tokens[ $i ];
			if ( \in_array( $text, [ '(', '[', '{' ], true ) || ( \is_array( $tokens[ $i ] ) && \in_array( $tokens[ $i ][0], [ T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES ], true ) ) ) {
				++$depth;
			} elseif ( \in_array( $text, [ ')', ']', '}' ], true ) && 0 === --$depth ) {
				return ',' === $tokens[ $i - 1 ] ? $args - 1 : $args;
			} elseif ( ',' === $text && 1 === $depth ) {
				++$args;
			}
		}

		return $args;
	}

	/**
	 * Paths relative to the plugin root, of the given extensions, under the given files and directories.
	 *
	 * @param string[] $extensions File extensions.
	 * @param string[] $paths      Files or directories relative to the plugin root.
	 * @return string[]
	 */
	private function files( array $extensions, array $paths ): array {
		$files = [];
		foreach ( $paths as $path ) {
			if ( is_file( $this->root() . '/' . $path ) ) {
				$files[] = $path;
				continue;
			}
			foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $this->root() . '/' . $path ) ) as $file ) {
				if ( $file->isFile() && \in_array( $file->getExtension(), $extensions, true ) ) {
					$files[] = substr( $file->getPathname(), \strlen( $this->root() ) + 1 );
				}
			}
		}

		return $files;
	}

	private function root(): string {
		return \dirname( __DIR__ );
	}
}
