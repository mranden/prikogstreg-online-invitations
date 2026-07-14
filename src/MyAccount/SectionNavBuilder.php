<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\MyAccount;

use PrikOgStreg\OnlineInvitations\Database\Repositories\AddressBookRepository;
use PrikOgStreg\OnlineInvitations\Database\Repositories\GuestRepository;
use PrikOgStreg\OnlineInvitations\Database\Repositories\PhotoRepository;
use PrikOgStreg\OnlineInvitations\Database\Repositories\WishlistItemRepository;
use PrikOgStreg\OnlineInvitations\Domain\Photo\PhotoModerationStatus;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectEntitlement;
use PrikOgStreg\OnlineInvitations\Domain\Project\PublicationStatus;

/**
 * Builds sidebar section navigation with completion status and summary meta.
 */
final class SectionNavBuilder {

	/**
	 * @var array<string, array{label:string,sections:list<string>}>
	 */
	private const GROUPS = [
		'setup'   => [
			'label'    => 'Setup',
			'sections' => [
				ProjectSections::OVERVIEW,
				ProjectSections::DESIGN,
				ProjectSections::EVENT,
				ProjectSections::GUESTS,
				ProjectSections::ADDRESS_BOOK,
			],
		],
		'launch'  => [
			'label'    => 'Launch',
			'sections' => [
				ProjectSections::PREVIEW,
				ProjectSections::PUBLISH,
			],
		],
		'manage'  => [
			'label'    => 'Manage',
			'sections' => [
				ProjectSections::RESPONSES,
				ProjectSections::WISHLIST,
				ProjectSections::PHOTOS,
			],
		],
		'settings' => [
			'label'    => '',
			'sections' => [
				ProjectSections::SETTINGS,
			],
		],
	];

	/**
	 * Sections counted toward the setup progress bar.
	 *
	 * @var list<string>
	 */
	private const PROGRESS_SECTIONS = [
		ProjectSections::DESIGN,
		ProjectSections::EVENT,
		ProjectSections::GUESTS,
		ProjectSections::PUBLISH,
	];

	public function __construct(
		private GuestRepository $guests,
		private WishlistItemRepository $wishlist_items,
		private PhotoRepository $photos,
		private AddressBookRepository $address_book
	) {}

	/**
	 * @param array<string, mixed> $project
	 * @return array{
	 *     progress:array{completed:int,total:int,percent:int},
	 *     groups:list<array{slug:string,label:string,items:list<array<string,mixed>>}>,
	 *     sections:array<string,string>,
	 *     section_urls:array<string,string>,
	 *     section:string
	 * }
	 */
	public function build( array $project, string $current_section, int $user_id ): array {
		$project_id   = (int) ( $project['project_id'] ?? 0 );
		$section_urls = Endpoints::section_urls( $project_id );
		$sections     = ProjectSections::labels();
		$stats        = $this->collect_stats( $project, $project_id, $user_id );
		$items        = [];

		foreach ( $sections as $slug => $label ) {
			$state         = $this->section_state( $slug, $project, $stats );
			$items[ $slug ] = [
				'slug'      => $slug,
				'label'     => $label,
				'url'       => $section_urls[ $slug ] ?? '#',
				'status'    => $state['status'],
				'meta'      => $state['meta'],
				'is_active' => $slug === $current_section,
			];
		}

		$groups = [];
		foreach ( self::GROUPS as $group_slug => $group ) {
			$group_items = [];
			foreach ( $group['sections'] as $section_slug ) {
				if ( isset( $items[ $section_slug ] ) ) {
					$group_items[] = $items[ $section_slug ];
				}
			}

			if ( [] === $group_items ) {
				continue;
			}

			$groups[] = [
				'slug'  => $group_slug,
				'label' => $this->group_label( (string) $group['label'] ),
				'items' => $group_items,
			];
		}

		return [
			'progress'     => $this->progress( $items ),
			'groups'       => $groups,
			'sections'     => $sections,
			'section_urls' => $section_urls,
			'section'      => $current_section,
		];
	}

	/**
	 * @param array<string, mixed> $project
	 * @return array{
	 *     guest_count:int,
	 *     rsvp_attending:int,
	 *     rsvp_declined:int,
	 *     address_book_count:int,
	 *     wishlist_count:int,
	 *     photo_count:int,
	 *     photo_pending:int,
	 *     has_design:bool,
	 *     has_event:bool,
	 *     is_published:bool,
	 *     has_import_error:bool
	 * }
	 */
	public function collect_stats( array $project, int $project_id, int $user_id ): array {
		$rsvp_summary = $this->guests->status_summary( $project_id );
		$photo_rows   = $this->photos->list_for_project( $project_id );
		$photo_count  = 0;
		$photo_pending = 0;

		foreach ( $photo_rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			++$photo_count;
			if ( PhotoModerationStatus::PENDING === (string) ( $row['moderation_status'] ?? '' ) ) {
				++$photo_pending;
			}
		}

		$has_design = (int) ( $project['state_version'] ?? 0 ) >= 1 && '' === (string) ( $project['last_error_code'] ?? '' );

		return [
			'guest_count'        => (int) ( $rsvp_summary['total'] ?? 0 ),
			'rsvp_attending'     => (int) ( $rsvp_summary['attending'] ?? 0 ),
			'rsvp_declined'      => (int) ( $rsvp_summary['declined'] ?? 0 ),
			'address_book_count' => $user_id > 0 ? $this->address_book->count_for_user( $user_id ) : 0,
			'wishlist_count'     => count( $this->wishlist_items->list_for_project( $project_id ) ),
			'photo_count'        => $photo_count,
			'photo_pending'      => $photo_pending,
			'has_design'         => $has_design,
			'has_event'          => ProjectEntitlement::has_required_event_data( $project ),
			'is_published'       => PublicationStatus::PUBLISHED === (string) ( $project['publication_status'] ?? '' ),
			'has_import_error'   => '' !== (string) ( $project['last_error_code'] ?? '' ),
		];
	}

	/**
	 * @param array<string, mixed> $stats
	 * @return array{status:string,meta:string}
	 */
	private function section_state( string $slug, array $project, array $stats ): array {
		switch ( $slug ) {
			case ProjectSections::OVERVIEW:
				return [
					'status' => 'neutral',
					'meta'   => $stats['is_published']
						? \__( 'Published', 'prikogstreg-online-invitations' )
						: \__( 'In progress', 'prikogstreg-online-invitations' ),
				];

			case ProjectSections::DESIGN:
				if ( $stats['has_import_error'] ) {
					return [
						'status' => 'attention',
						'meta'   => \__( 'Needs attention', 'prikogstreg-online-invitations' ),
					];
				}

				return [
					'status' => $stats['has_design'] ? 'complete' : 'pending',
					'meta'   => $stats['has_design']
						? \__( 'Ready to edit', 'prikogstreg-online-invitations' )
						: \__( 'Import pending', 'prikogstreg-online-invitations' ),
				];

			case ProjectSections::EVENT:
				return [
					'status' => $stats['has_event'] ? 'complete' : 'pending',
					'meta'   => $stats['has_event']
						? $this->event_meta( $project )
						: \__( 'Add title and date', 'prikogstreg-online-invitations' ),
				];

			case ProjectSections::GUESTS:
				$count = (int) $stats['guest_count'];

				return [
					'status' => $count > 0 ? 'complete' : 'pending',
					'meta'   => $count > 0
						? sprintf(
							/* translators: %d: guest count */
							\_n( '%d guest', '%d guests', $count, 'prikogstreg-online-invitations' ),
							$count
						)
						: \__( 'Add guests', 'prikogstreg-online-invitations' ),
				];

			case ProjectSections::ADDRESS_BOOK:
				$count = (int) $stats['address_book_count'];

				return [
					'status' => $count > 0 ? 'complete' : 'optional',
					'meta'   => $count > 0
						? sprintf(
							/* translators: %d: contact count */
							\_n( '%d contact', '%d contacts', $count, 'prikogstreg-online-invitations' ),
							$count
						)
						: \__( 'Optional', 'prikogstreg-online-invitations' ),
				];

			case ProjectSections::PREVIEW:
				return [
					'status' => $stats['has_design'] ? 'complete' : 'pending',
					'meta'   => $stats['has_design']
						? \__( 'Preview available', 'prikogstreg-online-invitations' )
						: \__( 'Complete design first', 'prikogstreg-online-invitations' ),
				];

			case ProjectSections::PUBLISH:
				return [
					'status' => $stats['is_published'] ? 'complete' : 'pending',
					'meta'   => $stats['is_published']
						? \__( 'Live', 'prikogstreg-online-invitations' )
						: \__( 'Not published', 'prikogstreg-online-invitations' ),
				];

			case ProjectSections::RESPONSES:
				$attending = (int) $stats['rsvp_attending'];
				$declined  = (int) $stats['rsvp_declined'];
				$answered  = $attending + $declined;

				if ( $answered <= 0 ) {
					return [
						'status' => 'optional',
						'meta'   => \__( 'No responses yet', 'prikogstreg-online-invitations' ),
					];
				}

				return [
					'status' => 'complete',
					'meta'   => sprintf(
						/* translators: 1: attending count, 2: declined count */
						\__( '%1$d yes · %2$d no', 'prikogstreg-online-invitations' ),
						$attending,
						$declined
					),
				];

			case ProjectSections::WISHLIST:
				$count = (int) $stats['wishlist_count'];

				return [
					'status' => $count > 0 ? 'complete' : 'optional',
					'meta'   => $count > 0
						? sprintf(
							/* translators: %d: wishlist item count */
							\_n( '%d gift', '%d gifts', $count, 'prikogstreg-online-invitations' ),
							$count
						)
						: \__( 'Optional', 'prikogstreg-online-invitations' ),
				];

			case ProjectSections::PHOTOS:
				$count   = (int) $stats['photo_count'];
				$pending = (int) $stats['photo_pending'];

				if ( $count <= 0 ) {
					return [
						'status' => 'optional',
						'meta'   => \__( 'Optional', 'prikogstreg-online-invitations' ),
					];
				}

				if ( $pending > 0 ) {
					return [
						'status' => 'attention',
						'meta'   => sprintf(
							/* translators: 1: photo count, 2: pending moderation count */
							\__( '%1$d photos · %2$d pending', 'prikogstreg-online-invitations' ),
							$count,
							$pending
						),
					];
				}

				return [
					'status' => 'complete',
					'meta'   => sprintf(
						/* translators: %d: photo count */
						\_n( '%d photo', '%d photos', $count, 'prikogstreg-online-invitations' ),
						$count
					),
				];

			case ProjectSections::SETTINGS:
				return [
					'status' => 'neutral',
					'meta'   => '',
				];
		}

		return [
			'status' => 'neutral',
			'meta'   => '',
		];
	}

	/**
	 * @param array<string, array<string, mixed>> $items
	 * @return array{completed:int,total:int,percent:int}
	 */
	private function progress( array $items ): array {
		$total     = count( self::PROGRESS_SECTIONS );
		$completed = 0;

		foreach ( self::PROGRESS_SECTIONS as $slug ) {
			if ( ! isset( $items[ $slug ] ) ) {
				continue;
			}

			if ( 'complete' === (string) ( $items[ $slug ]['status'] ?? '' ) ) {
				++$completed;
			}
		}

		$percent = $total > 0 ? (int) round( ( $completed / $total ) * 100 ) : 0;

		return [
			'completed' => $completed,
			'total'     => $total,
			'percent'   => $percent,
		];
	}

	/**
	 * @param array<string, mixed> $project
	 */
	private function event_meta( array $project ): string {
		$start = (string) ( $project['event_start_utc'] ?? '' );
		if ( '' === $start ) {
			return \__( 'Details saved', 'prikogstreg-online-invitations' );
		}

		$timestamp = strtotime( $start . ' UTC' );
		if ( false === $timestamp ) {
			return \__( 'Details saved', 'prikogstreg-online-invitations' );
		}

		if ( function_exists( 'wp_date' ) ) {
			return wp_date( get_option( 'date_format', 'Y-m-d' ), $timestamp );
		}

		return gmdate( 'Y-m-d', $timestamp );
	}

	private function group_label( string $label ): string {
		if ( '' === $label ) {
			return '';
		}

		return match ( $label ) {
			'Setup'   => \__( 'Setup', 'prikogstreg-online-invitations' ),
			'Launch'  => \__( 'Launch', 'prikogstreg-online-invitations' ),
			'Manage'  => \__( 'Manage', 'prikogstreg-online-invitations' ),
			default   => $label,
		};
	}
}
