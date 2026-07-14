<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\MyAccount;

/**
 * Parses My Account online-invitations routes.
 */
final class Router {

	/**
	 * @return array{
	 *     mode:'list'|'project',
	 *     project_id:int,
	 *     section:string
	 * }
	 */
	public function parse_request( ?string $raw_path = null ): array {
		if ( null === $raw_path ) {
			$raw_path = function_exists( 'get_query_var' )
				? (string) get_query_var( Endpoints::SLUG, '' )
				: '';
		}

		$parts = array_values(
			array_filter(
				explode( '/', trim( (string) $raw_path, '/' ) ),
				static fn( string $part ): bool => '' !== $part
			)
		);

		if ( [] === $parts ) {
			return [
				'mode'       => 'list',
				'project_id' => 0,
				'section'    => ProjectSections::OVERVIEW,
			];
		}

		$project_id = ctype_digit( (string) $parts[0] ) ? (int) $parts[0] : 0;
		$section    = isset( $parts[1] ) ? sanitize_key( (string) $parts[1] ) : ProjectSections::default_section();

		if ( ! ProjectSections::is_valid( $section ) ) {
			$section = ProjectSections::default_section();
		}

		return [
			'mode'       => $project_id > 0 ? 'project' : 'list',
			'project_id' => $project_id,
			'section'    => $section,
		];
	}
}
