<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\MyAccount;

use PrikOgStreg\OnlineInvitations\Database\Repositories\AddressBookRepository;
use PrikOgStreg\OnlineInvitations\Domain\AddressBook\AddressBookService;
use PrikOgStreg\OnlineInvitations\Security\Authorization;
use PrikOgStreg\OnlineInvitations\Support\TemplateLoader;

/**
 * Project-scoped address book UI backed by the owner's private contacts.
 */
final class AddressBookController {

	public function __construct(
		private AddressBookService $address_book,
		private AddressBookRepository $contacts,
		private Authorization $authorization,
		private TemplateLoader $templates
	) {}

	/**
	 * @param array<string, mixed> $project
	 * @param array<string, mixed> $context
	 */
	public function render( array $project, array $context ): void {
		$owner_user_id = (int) ( $project['user_id'] ?? 0 );
		$page          = isset( $_GET['pks_oi_ab_page'] ) ? max( 1, (int) $_GET['pks_oi_ab_page'] ) : 1;
		$search        = isset( $_GET['pks_oi_ab_search'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['pks_oi_ab_search'] ) ) : '';
		$edit_contact  = null;
		$edit_id       = isset( $_GET['pks_oi_edit_contact'] ) ? (int) $_GET['pks_oi_edit_contact'] : 0;

		if ( $this->authorization->is_support_view( $project ) ) {
			$this->address_book->log_support_access(
				(int) $project['project_id'],
				$this->authorization->current_user_id(),
				$owner_user_id
			);
		}

		if ( $edit_id > 0 ) {
			$edit_contact = $this->contacts->find_by_id_for_user( $edit_id, $owner_user_id );
		}

		$this->templates->render(
			'myaccount/project-address-book',
			array_merge(
				$context,
				[
					'contacts'     => $this->address_book->list_for_user( $owner_user_id, $page, $search ),
					'edit_contact' => $edit_contact,
					'search'       => $search,
					'owner_user_id'=> $owner_user_id,
				]
			)
		);
	}

	/**
	 * @param array<string, mixed> $project
	 */
	public function handle_post( array $project, string $section, string $redirect_url ): bool {
		if ( ProjectSections::ADDRESS_BOOK !== $section ) {
			return false;
		}

		$owner_user_id = (int) ( $project['user_id'] ?? 0 );
		$action        = sanitize_key( (string) ( $_POST['pks_oi_action'] ?? '' ) );

		if ( 'save_contact' === $action ) {
			$contact_id = (int) ( $_POST['address_book_id'] ?? 0 );
			if ( $contact_id > 0 ) {
				$contact = $this->contacts->find_by_id_for_user( $contact_id, $owner_user_id );
				$result  = is_array( $contact )
					? $this->address_book->update( $owner_user_id, $contact, wp_unslash( $_POST ) )
					: [ 'success' => false ];
			} else {
				$result = $this->address_book->create( $owner_user_id, wp_unslash( $_POST ) );
			}
			wp_safe_redirect( add_query_arg( 'pks_oi_saved', empty( $result['success'] ) ? '0' : '1', $redirect_url ) );
			exit;
		}

		if ( 'archive_contact' === $action ) {
			$this->address_book->archive( $owner_user_id, (int) ( $_POST['address_book_id'] ?? 0 ) );
			wp_safe_redirect( add_query_arg( 'pks_oi_archived', '1', $redirect_url ) );
			exit;
		}

		if ( 'delete_contact' === $action ) {
			$this->address_book->delete( $owner_user_id, (int) ( $_POST['address_book_id'] ?? 0 ) );
			wp_safe_redirect( add_query_arg( 'pks_oi_deleted', '1', $redirect_url ) );
			exit;
		}

		if ( 'add_contacts_to_project' === $action ) {
			$ids    = array_map( 'intval', (array) ( $_POST['address_book_ids'] ?? [] ) );
			$result = $this->address_book->add_contacts_to_project( $project, $owner_user_id, $ids );
			wp_safe_redirect(
				add_query_arg(
					[
						'pks_oi_added'   => (int) ( $result['added'] ?? 0 ),
						'pks_oi_skipped' => (int) ( $result['skipped'] ?? 0 ),
					],
					Endpoints::project_url( (int) $project['project_id'], ProjectSections::GUESTS )
				)
			);
			exit;
		}

		return false;
	}
}
