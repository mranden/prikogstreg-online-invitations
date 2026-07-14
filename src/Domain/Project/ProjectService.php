<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Project;

use PrikOgStreg\OnlineInvitations\Builder\BuilderService;
use PrikOgStreg\OnlineInvitations\Database\Repositories\EventRepository;
use PrikOgStreg\OnlineInvitations\Database\Repositories\ProjectRepository;
use PrikOgStreg\OnlineInvitations\Scheduling\WelcomeScheduler;
use PrikOgStreg\OnlineInvitations\Storage\Exception\StorageException;
use PrikOgStreg\OnlineInvitations\Storage\ProjectStorage;
use PrikOgStreg\OnlineInvitations\WooCommerce\Cart\CartPayload;
use PrikOgStreg\OnlineInvitations\WooCommerce\Orders\ProjectCreationLock;
use PrikOgStreg\OnlineInvitations\WooCommerce\ProductType\ProductMeta;

/**
 * Idempotent project creation and builder-state import from order items.
 */
final class ProjectService {

	public function __construct(
		private ProjectRepository $projects,
		private EventRepository $events,
		private ProjectFactory $factory,
		private BuilderService $builder,
		private ProjectStorage $storage,
		private WelcomeScheduler $welcome
	) {}

	/**
	 * @param object $order WooCommerce order.
	 */
	public function process_order( object $order ): void {
		if ( ! method_exists( $order, 'get_status' ) || ! ProjectEntitlement::is_qualifying_status( (string) $order->get_status() ) ) {
			return;
		}

		if ( ! method_exists( $order, 'get_items' ) ) {
			return;
		}

		foreach ( $order->get_items( 'line_item' ) as $item ) {
			if ( ! is_object( $item ) || ! method_exists( $item, 'get_product' ) ) {
				continue;
			}

			$this->create_from_order_item( $order, $item );
		}
	}

	/**
	 * @param object $order WooCommerce order.
	 * @param object $item  WooCommerce order item product.
	 * @return int Project ID when linked or created; 0 when skipped.
	 */
	public function create_from_order_item( object $order, object $item ): int {
		if ( ! $this->is_invitation_order_item( $item ) ) {
			return 0;
		}

		$order_item_id = (int) ( method_exists( $item, 'get_id' ) ? $item->get_id() : 0 );
		if ( $order_item_id <= 0 ) {
			return 0;
		}

		$existing = $this->resolve_existing_project_id( $item, $order_item_id );
		if ( $existing > 0 ) {
			$project = $this->projects->find_by_id( $existing );
			if ( is_array( $project ) && ! ProjectEntitlement::is_project_usable( $project ) ) {
				$this->import_for_project( $project, $order, $item );
			}

			return $existing;
		}

		if ( ! ProjectCreationLock::acquire( $order_item_id ) ) {
			return 0;
		}

		try {
			$existing = $this->resolve_existing_project_id( $item, $order_item_id );
			if ( $existing > 0 ) {
				return $existing;
			}

			return $this->create_new_project( $order, $item, $order_item_id );
		} finally {
			ProjectCreationLock::release( $order_item_id );
		}
	}

	public function retry_import( int $project_id ): bool {
		$project = $this->projects->find_by_id( $project_id );
		if ( ! is_array( $project ) ) {
			return false;
		}

		$order_item_id = (int) ( $project['order_item_id'] ?? 0 );
		if ( $order_item_id <= 0 || ! ProjectCreationLock::acquire( $order_item_id ) ) {
			return false;
		}

		try {
			if ( ProjectEntitlement::is_project_usable( $project ) ) {
				return true;
			}

			$order_id = (int) ( $project['order_id'] ?? 0 );
			$order    = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;
			if ( ! is_object( $order ) ) {
				return false;
			}

			$item = $this->find_order_item( $order, $order_item_id );
			if ( ! is_object( $item ) ) {
				return false;
			}

			return $this->import_for_project( $project, $order, $item );
		} finally {
			ProjectCreationLock::release( $order_item_id );
		}
	}

	/**
	 * @param object $order WooCommerce order.
	 * @param object $item  WooCommerce order item product.
	 */
	private function create_new_project( object $order, object $item, int $order_item_id ): int {
		$order_id   = (int) ( method_exists( $order, 'get_id' ) ? $order->get_id() : 0 );
		$user_id    = (int) ( method_exists( $order, 'get_customer_id' ) ? $order->get_customer_id() : 0 );
		$product    = $item->get_product();
		$product_id = (int) ( method_exists( $item, 'get_product_id' ) ? $item->get_product_id() : 0 );

		if ( $user_id <= 0 || ! is_object( $product ) || $product_id <= 0 ) {
			$this->record_event(
				0,
				'project_import_failed',
				[
					'order_id'      => $order_id,
					'order_item_id' => $order_item_id,
					'error_code'    => 'missing_customer_or_product',
				]
			);

			return 0;
		}

		$adapter = $this->builder->get_adapter();
		if ( null === $adapter ) {
			$this->record_event(
				0,
				'project_import_failed',
				[
					'order_id'      => $order_id,
					'order_item_id' => $order_item_id,
					'error_code'    => 'adapter_unavailable',
				]
			);

			do_action( 'pks_oi_project_import_failed', 0, 'adapter_unavailable', $order_item_id );

			return 0;
		}

		do_action( 'pks_oi_project_creation_started', $order_id, $order_item_id );

		$storage_uuid = $this->factory->generate_storage_uuid();
		$token_pair   = $this->factory->generate_generic_token_pair();
		$token_hash   = $token_pair['hash'];
		$project_id   = 0;

		try {
			$project_id = $this->factory->create_cpt_shell(
				[
					'order_id'      => $order_id,
					'order_item_id' => $order_item_id,
					'product_id'    => $product_id,
					'user_id'       => $user_id,
					'product_name'  => (string) $product->get_name(),
					'customer_name' => $this->resolve_customer_name( $order ),
				]
			);

			$schema_version = '1';
			if ( method_exists( $adapter, 'get_template_id_for_product' ) ) {
				$template_id = (int) $adapter->get_template_id_for_product( $product_id );
			} else {
				$template_id = $product_id;
			}

			$row = $this->factory->build_initial_row(
				[
					'project_id'             => $project_id,
					'storage_uuid'           => $storage_uuid,
					'user_id'                => $user_id,
					'order_id'               => $order_id,
					'order_item_id'          => $order_item_id,
					'product_id'             => $product_id,
					'template_id'            => (string) $template_id,
					'generic_token_hash'     => $token_hash,
					'builder_schema_version' => $schema_version,
					'product'                => $product,
					'expires_at_utc'       => null,
				]
			);

			if ( ! $this->projects->insert( $row ) ) {
				if ( function_exists( 'wp_delete_post' ) ) {
					wp_delete_post( $project_id, true );
				}

				throw new \RuntimeException( 'Failed to insert project row.' );
			}

			$project = $this->projects->find_by_id( $project_id );
			if ( ! is_array( $project ) ) {
				throw new \RuntimeException( 'Project row missing after insert.' );
			}

			update_post_meta( $project_id, ProjectPublicUrlService::META_KEY, $token_pair['raw'] );

			if ( ! $this->import_for_project( $project, $order, $item ) ) {
				return $project_id;
			}

			$this->link_order_item_to_project( $item, $project_id );
			do_action( 'pks_oi_project_created', $project_id, $order_id, $order_item_id );

			return $project_id;
		} catch ( \Throwable $exception ) {
			if ( $project_id > 0 ) {
				$this->projects->update(
					$project_id,
					[
						'last_error_code' => 'creation_failed',
						'status'          => ProjectStatus::DRAFT,
					]
				);
				$this->record_event(
					$project_id,
					'project_import_failed',
					[
						'order_id'      => $order_id,
						'order_item_id' => $order_item_id,
						'error_code'    => 'creation_failed',
						'message'       => $exception->getMessage(),
					]
				);
				do_action( 'pks_oi_project_import_failed', $project_id, 'creation_failed', $order_item_id );

				return $project_id;
			}

			if ( isset( $storage_uuid ) ) {
				$this->storage->delete_project_storage( $storage_uuid );
			}

			$this->record_event(
				0,
				'project_import_failed',
				[
					'order_id'      => $order_id,
					'order_item_id' => $order_item_id,
					'error_code'    => 'creation_failed',
				]
			);
			do_action( 'pks_oi_project_import_failed', 0, 'creation_failed', $order_item_id );

			return 0;
		}
	}

	/**
	 * @param array<string, mixed> $project
	 * @param object               $order WooCommerce order.
	 * @param object               $item  WooCommerce order item product.
	 */
	private function import_for_project( array $project, object $order, object $item ): bool {
		if ( ProjectImportGuard::is_already_imported( $project ) ) {
			return true;
		}

		$project_id    = (int) ( $project['project_id'] ?? 0 );
		$order_id      = (int) ( $project['order_id'] ?? 0 );
		$order_item_id = (int) ( $project['order_item_id'] ?? 0 );
		$product_id    = (int) ( $project['product_id'] ?? 0 );
		$storage_uuid  = (string) ( $project['storage_uuid'] ?? '' );

		$context_error = ProjectImportGuard::validate_import_context( $project, $order, $item );
		if ( null !== $context_error ) {
			$this->mark_import_failed( $project_id, $storage_uuid, $context_error, $order_id, $order_item_id );

			return false;
		}

		$adapter = $this->builder->get_adapter();
		if ( null === $adapter || ! method_exists( $adapter, 'load_state' ) ) {
			$this->mark_import_failed( $project_id, $storage_uuid, 'adapter_unavailable', $order_id, $order_item_id );

			return false;
		}

		$context = [
			'source'        => 'online_invitation',
			'mode'          => 'import',
			'order_id'      => $order_id,
			'order_item_id' => $order_item_id,
			'product_id'    => $product_id,
			'project_id'    => $project_id,
			'template_id'   => (int) ( $project['template_id'] ?? $product_id ),
		];

		$state = $adapter->load_state( $context );
		if ( is_wp_error( $state ) ) {
			$this->mark_import_failed(
				$project_id,
				$storage_uuid,
				(string) ( $state->get_error_code() ?: 'invalid_builder_state' ),
				$order_id,
				$order_item_id
			);

			return false;
		}

		if ( ! is_array( $state ) ) {
			$this->mark_import_failed( $project_id, $storage_uuid, 'malformed_payload', $order_id, $order_item_id );

			return false;
		}

		if ( method_exists( $adapter, 'validate_state' ) ) {
			$validated = $adapter->validate_state(
				$state,
				array_merge( $context, [ 'mode' => 'import' ] )
			);

			if ( is_wp_error( $validated ) ) {
				$this->mark_import_failed(
					$project_id,
					$storage_uuid,
					(string) ( $validated->get_error_code() ?: 'invalid_builder_state' ),
					$order_id,
					$order_item_id
				);

				return false;
			}

			if ( is_array( $validated ) ) {
				$state = $validated;
			}
		}

		$page_error = ProjectImportGuard::validate_builder_pages( $state );
		if ( null !== $page_error ) {
			$this->mark_import_failed( $project_id, $storage_uuid, $page_error, $order_id, $order_item_id );

			return false;
		}

		$checksum_error = ProjectImportGuard::validate_payload_checksum( $state, $item );
		if ( null !== $checksum_error ) {
			$this->mark_import_failed( $project_id, $storage_uuid, $checksum_error, $order_id, $order_item_id );

			return false;
		}

		$schema_version = '1';
		if ( method_exists( $adapter, 'get_schema_version' ) ) {
			$schema_version = (string) $adapter->get_schema_version( $state );
		}

		if ( method_exists( $adapter, 'migrate_state' ) ) {
			$migrated = $adapter->migrate_state( $state, (string) ( $state['schema_version'] ?? '0' ), $schema_version );
			if ( is_wp_error( $migrated ) ) {
				$this->mark_import_failed(
					$project_id,
					$storage_uuid,
					(string) ( $migrated->get_error_code() ?: 'migration_failed' ),
					$order_id,
					$order_item_id
				);

				return false;
			}

			if ( is_array( $migrated ) ) {
				$state = $migrated;
			}
		}

		try {
			$envelope_snapshot = EnvelopeSnapshot::from_project_row( $project );

			$import = $this->storage->import_complete_snapshot(
				[
					'project_id'             => $project_id,
					'storage_uuid'           => $storage_uuid,
					'builder_schema_version' => $schema_version,
					'product_id'             => $product_id,
					'template_id'            => (string) ( $project['template_id'] ?? $product_id ),
				],
				$state,
				$envelope_snapshot
			);

			$expires_at = ProjectExpiration::calculate_initial_expiry(
				isset( $state['event_end_utc'] ) ? (string) $state['event_end_utc'] : null,
				isset( $state['event_start_utc'] ) ? (string) $state['event_start_utc'] : null
			);

			$this->projects->update(
				$project_id,
				[
					'status'                 => ProjectEntitlement::initial_project_status(),
					'builder_schema_version' => $schema_version,
					'state_version'          => (int) $import['state_version'],
					'state_manifest_path'    => (string) $import['state_manifest_path'],
					'last_error_code'        => null,
					'expires_at_utc'         => $expires_at,
				]
			);

			$this->record_event(
				$project_id,
				'project_import_succeeded',
				[
					'order_id'      => $order_id,
					'order_item_id' => $order_item_id,
					'state_version' => (int) $import['state_version'],
				]
			);
			do_action( 'pks_oi_project_import_succeeded', $project_id );

			$this->link_order_item_to_project( $item, $project_id );
			$this->welcome->queue_once( $project_id );

			return true;
		} catch ( StorageException $exception ) {
			$this->storage->delete_project_storage( $storage_uuid );
			$this->mark_import_failed(
				$project_id,
				$storage_uuid,
				method_exists( $exception, 'getErrorCode' ) ? (string) $exception->getErrorCode() : ( $exception->code_key ?? 'storage_failed' ),
				$order_id,
				$order_item_id
			);

			return false;
		} catch ( \Throwable $exception ) {
			$this->storage->delete_project_storage( $storage_uuid );
			$this->mark_import_failed( $project_id, $storage_uuid, 'import_failed', $order_id, $order_item_id );

			return false;
		}
	}

	private function mark_import_failed(
		int $project_id,
		string $storage_uuid,
		string $error_code,
		int $order_id,
		int $order_item_id
	): void {
		if ( $project_id > 0 ) {
			$this->projects->update(
				$project_id,
				[
					'last_error_code' => $error_code,
					'status'          => ProjectStatus::DRAFT,
				]
			);
		}

		$this->record_event(
			$project_id,
			'project_import_failed',
			[
				'order_id'      => $order_id,
				'order_item_id' => $order_item_id,
				'error_code'    => $error_code,
			]
		);
		do_action( 'pks_oi_project_import_failed', $project_id, $error_code, $order_item_id );
	}

	/**
	 * @param object $item WooCommerce order item product.
	 */
	private function resolve_existing_project_id( object $item, int $order_item_id ): int {
		if ( method_exists( $item, 'get_meta' ) ) {
			$meta_id = (int) $item->get_meta( ProjectMeta::ORDER_ITEM_PROJECT_ID, true );
			if ( $meta_id > 0 ) {
				return $meta_id;
			}
		}

		$row = $this->projects->find_by_order_item_id( $order_item_id );
		if ( is_array( $row ) ) {
			$project_id = (int) ( $row['project_id'] ?? 0 );
			if ( $project_id > 0 ) {
				$this->link_order_item_to_project( $item, $project_id );

				return $project_id;
			}
		}

		return 0;
	}

	/**
	 * @param object $item WooCommerce order item product.
	 */
	private function link_order_item_to_project( object $item, int $project_id ): void {
		if ( ! method_exists( $item, 'update_meta_data' ) || ! method_exists( $item, 'save' ) ) {
			return;
		}

		$item->update_meta_data( ProjectMeta::ORDER_ITEM_PROJECT_ID, $project_id );
		$item->save();
	}

	/**
	 * @param object $item WooCommerce order item product.
	 */
	private function is_invitation_order_item( object $item ): bool {
		if ( method_exists( $item, 'get_meta' ) ) {
			$type = (string) $item->get_meta( CartPayload::ORDER_META_TYPE, true );
			if ( ProductMeta::TYPE === $type ) {
				return true;
			}
		}

		if ( method_exists( $item, 'get_product' ) ) {
			$product = $item->get_product();
			if ( is_object( $product ) && ProductMeta::is_online_invitation( $product ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param object $order WooCommerce order.
	 */
	private function find_order_item( object $order, int $order_item_id ): ?object {
		foreach ( $order->get_items( 'line_item' ) as $item ) {
			if ( is_object( $item ) && method_exists( $item, 'get_id' ) && (int) $item->get_id() === $order_item_id ) {
				return $item;
			}
		}

		return null;
	}

	/**
	 * @param object $order WooCommerce order.
	 */
	private function resolve_customer_name( object $order ): string {
		if ( method_exists( $order, 'get_formatted_billing_full_name' ) ) {
			$name = trim( (string) $order->get_formatted_billing_full_name() );
			if ( '' !== $name ) {
				return $name;
			}
		}

		return __( 'Customer', 'prikogstreg-online-invitations' );
	}

	/**
	 * @param array<string, mixed> $metadata
	 */
	private function record_event( int $project_id, string $event_type, array $metadata = [] ): void {
		if ( $project_id <= 0 ) {
			return;
		}

		$encoded = json_encode( $metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		$this->events->insert(
			[
				'project_id'    => $project_id,
				'actor_type'    => 'system',
				'event_type'    => $event_type,
				'metadata_json' => is_string( $encoded ) ? $encoded : '{}',
			]
		);
	}
}
