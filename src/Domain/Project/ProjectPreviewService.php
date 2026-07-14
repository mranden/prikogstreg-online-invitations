<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Project;

use PrikOgStreg\OnlineInvitations\Builder\BuilderService;
use PrikOgStreg\OnlineInvitations\Storage\Exception\StorageException;
use PrikOgStreg\OnlineInvitations\Support\PosterPreviewHtml;

/**
 * Authenticated draft preview without open tracking.
 */
final class ProjectPreviewService {

	public function __construct(
		private BuilderService $builder,
		private ProjectStateService $state_service
	) {}

	/**
	 * @param array<string, mixed> $project
	 * @return array{html:string,envelope_preset:string,track_opens:bool}
	 */
	public function render_preview( array $project ): array {
		$empty = [
			'html'             => '',
			'envelope_preset'  => (string) ( $project['envelope_preset'] ?? '' ),
			'track_opens'      => false,
		];

		if ( ! ProjectEntitlement::is_project_usable( $project ) ) {
			return $empty;
		}

		try {
			$state = $this->state_service->load_canonical_state( $project );
		} catch ( StorageException $exception ) {
			return $empty;
		}

		$context = $this->state_service->adapter_context( $project, 'preview' );
		$context['track_opens'] = false;

		$adapter = $this->builder->get_adapter();
		$html    = '';

		if ( null !== $adapter && method_exists( $adapter, 'render_preview_html' ) ) {
			$rendered = $adapter->render_preview_html( $state, $context );
			if ( ! is_wp_error( $rendered ) ) {
				$html = (string) $rendered;
			}
		} elseif ( null !== $adapter && method_exists( $adapter, 'render_public_html' ) ) {
			$rendered = $adapter->render_public_html( $state, $context );
			if ( ! is_wp_error( $rendered ) ) {
				$html = (string) $rendered;
			}
		} else {
			$pages = is_array( $state['page'] ?? null ) ? $state['page'] : [];
			$html  = implode( "\n", array_map( 'strval', $pages ) );
		}

		$html = PosterPreviewHtml::prepare_for_viewport( $html );

		do_action( 'pks_oi_project_preview_rendered', (int) $project['project_id'], false );

		return [
			'html'             => $html,
			'envelope_preset'  => (string) ( $project['envelope_preset'] ?? '' ),
			'track_opens'      => false,
		];
	}
}
