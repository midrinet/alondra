<?php
/**
 * Set user roles task
 *
 * @package Alondra/Helper
 */

namespace Midrinet\Alondra\Helper\Task;

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

/**
 * Sets a WordPress user's roles, replacing their existing ones, so tests can
 * exercise role-based rule matching against a user with more than one role.
 */
class SetUserRolesTask extends Task {

	/**
	 * Execute the task
	 *
	 * @param array<string, mixed> $args Webhook request args. `user_id` is the user
	 *                                   ID; `roles` is a JSON array of role slugs.
	 * @throws \Exception If the arguments are missing/invalid or the user doesn't exist.
	 */
	public function execute( array $args = [] ): void {
		if ( ! isset( $args['user_id'] ) ) {
			throw new \Exception( 'Missing "user_id" argument', 400 );
		}

		$roles = $this->get_json_array_arg( $args, 'roles' );
		if ( empty( $roles ) ) {
			throw new \Exception( 'Invalid "roles" JSON', 400 );
		}

		$user = new \WP_User( (int) $args['user_id'] );
		if ( ! $user->exists() ) {
			throw new \Exception( 'User not found', 404 );
		}

		$user->set_role( $roles[0] );
		foreach ( \array_slice( $roles, 1 ) as $role ) {
			$user->add_role( $role );
		}
	}
}
