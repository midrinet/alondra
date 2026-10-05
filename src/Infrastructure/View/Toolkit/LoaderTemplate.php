<?php
/**
 * The Template Loader
 *
 * @package    Midrinet/Alondra
 * @subpackage Midrinet/Alondra/Infrastructure/View/Toolkit
 */

namespace Midrinet\Alondra\Infrastructure\View\Toolkit;

/**
 * The Template Loader
 *
 * @since 1.0.0
 */
class LoaderTemplate {

	/**
	 * Path to the template directory.
	 *
	 * @since 1.0.0
	 */
	private string $path;

	/**
	 * @since 1.0.0
	 *
	 * @param string $path Path to the template directory, ends with a slash.
	 */
	public function __construct( $path = '' ) {
		$this->path = $path;
		if ( ! empty( $path ) && \DIRECTORY_SEPARATOR !== \substr( $path, -1 ) ) {
			$this->path .= \DIRECTORY_SEPARATOR;
		}
	}

	/**
	 * Load a template part similar as the get_template_part themes function.
	 * If the $filename parameter starts with slash (/),
	 * then the constructor path ($this->path) is ignored.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $filename Name of the template file without suffix .php.
	 * @param array<string, mixed> $args     Variables to be passed to the template.
	 */
	public function load( $filename, &$args = [] ): void {
		if ( ! empty( $args ) && \is_array( $args ) ) {
			\extract( $args ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		}
		unset( $args );
		$to_include = "{$filename}.php";
		if ( \DIRECTORY_SEPARATOR !== \substr( $filename, 0, 1 ) ) {
			$to_include = $this->path . $to_include;
		}
		include $to_include; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
	}
}
