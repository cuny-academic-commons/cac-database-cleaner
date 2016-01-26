<?php
/*
Plugin Name: CAC Database Cleaner
Version: 0.1-alpha
Description: Clean a database of non-public data
Author: Boone B Gorges
Author URI: http://boone.gorg.es
Text Domain: cac-database-cleaner
Domain Path: /languages
Network: true
*/

class CAC_Database_Cleaner {
	private $status;

	/**
	 * Private singleton constructor.
	 *
	 * @since 0.1
	 */
	private function __construct() {
		if ( ! is_super_admin() ) {
			return;
		}

		add_action( 'wp_ajax_cac_database_cleaner', array( $this, 'ajax_handler' ) );

		if ( ! is_network_admin() ) {
			return;
		}

		$hook = is_multisite() ? 'network_admin_menu' : 'admin_menu';
		add_action( $hook, array( $this, 'add_menu_page' ) );
	}

	/**
	 * Static singleton init method.
	 *
	 * @since 0.1
	 */
	public static function init() {
		static $instance;

		if ( empty( $instance ) ) {
			$instance = new self();
		}

		return $instance;
	}

	public function add_menu_page() {
		$slug = add_menu_page(
			__( 'CAC Database Cleaner', 'cac-database-cleaner' ),
			__( 'CAC Database Cleaner', 'cac-database-cleaner' ),
			'delete_users',
			'cac-database-cleaner',
			array( $this, 'admin_page' )
		);

		add_action( 'admin_print_scripts-' . $slug, array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Admin page markup
	 *
	 * @since 0.1
	 */
	public function admin_page() {

		?>
		<div class="wrap">
			<h2><?php esc_html_e( 'CAC Database Cleaner', 'cac-database-cleaner' ) ?></h2>
		</div>

		<a id="clean-submit" class="button-primary"><?php esc_html_e( 'Clean', 'cac-database-cleaner' ) ?></a>
		<?php wp_nonce_field( 'cac-database-cleaner' ) ?>

		<div id="progress">
			<ul>
			</ul>
		</div>
		<?php
	}

	/**
	 * Enqueue CSS and JS assets.
	 *
	 * @since 0.1
	 */
	public function enqueue_assets() {
		wp_enqueue_style( 'cac-database-cleaner', plugins_url() . '/cac-database-cleaner/assets/css/screen.css' );
		wp_enqueue_script( 'cac-database-cleaner', plugins_url() . '/cac-database-cleaner/assets/js/cac-database-cleaner.js', array( 'jquery' ) );

		$steps = array(
//			'reset_passwords',
//			'reset_emails',
		);

		if ( function_exists( 'buddypress' ) ) {
//			$steps[] = 'bp_remove_non_public_xprofile_data';
//			$steps[] = 'bp_remove_non_public_groups';
//			$steps[] = 'bp_remove_private_messages';
		}

		if ( is_multisite() ) {
			$steps[] = 'ms_remove_firestats_tables';
//			$steps[] = 'ms_remove_non_public_blogs';
//			$steps[] = 'ms_remove_non_public_content';
		}

//		$steps = array( 'ms_remove_non_public_content' );

		wp_localize_script( 'cac-database-cleaner', 'cac_database_cleaner', array(
			'steps' => $steps,
		) );
	}

	public function ajax_handler() {
		if ( ! is_super_admin() ) {
			return;
		}

		$nonce = isset( $_POST['nonce'] ) ? stripslashes( $_POST['nonce'] ) : 0;

		if ( ! wp_verify_nonce( $nonce, 'cac-database-cleaner' ) ) {
			die( 0 );
		}

		$current_step = isset( $_POST['current_step'] ) ? $_POST['current_step'] : 0;

		if ( ! empty( $_POST['restart'] ) ) {
			delete_site_option( 'cac-database-cleaner-status' );
		}

		$response = $this->process_step( $current_step );

		die( json_encode( $response ) );
	}

	protected function process_step( $current_step ) {
		$this->status = get_site_option( 'cac-database-cleaner-status' );

		if ( empty( $this->status ) ) {
			$this->status['current_step'] = $current_step;
			$this->status['notified'] = false;
			$this->status['last_processed'] = 0;
		}

		if ( method_exists( 'CAC_Database_Cleaner', $current_step ) ) {
			return call_user_func( array( $this, $current_step ) );
		} else {
			die( $current_step );
		}
	}

	protected function reset_passwords() {
		$retval = array(
			'message' => '',
			'step_complete' => 0,
		);

		if ( ! $this->status['notified'] ) {
			$retval['message'] = __( 'Resetting all user passwords to <code>password</code>...', 'cac-database-cleaner' );
			$this->status['notified'] = true;
			update_site_option( 'cac-database-cleaner-status', $this->status );
			return $retval;
		}

		global $wpdb;

		$password = wp_hash_password( 'password' );
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->users} SET user_pass = %s WHERE ID != %d", $password, get_current_user_id() ) );

		$retval['step_complete'] = 1;
		$retval['message'] = ' ' . __( 'Done!', 'cac-database-cleaner' );

		delete_site_option( 'cac-database-cleaner-status' );

		return $retval;
	}

	protected function reset_emails() {
		$retval = array(
			'message' => '',
			'step_complete' => 0,
		);

		if ( ! $this->status['notified'] ) {
			$retval['message'] = __( 'Resetting all user emails to <code>[username]@example.com</code>...', 'cac-database-cleaner' );
			$this->status['notified'] = true;
			update_site_option( 'cac-database-cleaner-status', $this->status );
			return $retval;
		}

		global $wpdb;

		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->users} SET user_email = ( CONCAT (user_login, '@example.com') ) WHERE ID != %d", get_current_user_id() ) );

		$retval['step_complete'] = 1;
		$retval['message'] = ' ' . __( 'Done!', 'cac-database-cleaner' );

		delete_site_option( 'cac-database-cleaner-status' );

		return $retval;
	}

	protected function bp_remove_non_public_xprofile_data() {
		$retval = array(
			'message' => '',
			'step_complete' => 0,
		);

		if ( ! function_exists( 'bp_is_active' ) || ! bp_is_active( 'xprofile' ) ) {
			$retval['message'] = __( 'BuddyPress XProfile is not active.', 'cac-database-cleaner' );
			$retval['step_complete'] = 1;
			delete_site_option( 'cac-database-cleaner-status' );
			return $retval;
		}

		if ( ! $this->status['notified'] ) {
			$retval['message'] = __( 'Deleting all non-public BuddyPress XProfile data...', 'cac-database-cleaner' );
			$this->status['notified'] = true;
			update_site_option( 'cac-database-cleaner-status', $this->status );
			return $retval;
		}

		global $wpdb, $bp;
		$last = intval( $this->status['last_processed'] );
		$user_ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->users} WHERE ID >= %d AND ID < %d", $last, $last + 500 ) );

		if ( empty( $user_ids ) ) {
			$retval['step_complete'] = 1;
			$retval['message'] = ' ' . __( 'Done!', 'cac-database-cleaner' );
			delete_site_option( 'cac-database-cleaner-status' );
			return $retval;
		}

		foreach ( $user_ids as $user_id ) {
			$vis_levels = bp_get_user_meta( $user_id, 'bp_xprofile_visibility_levels', true );
			$to_delete = array();
			foreach ( (array) $vis_levels as $field_id => $vis_level ) {
				if ( 'public' !== $vis_level ) {
					$to_delete[] = $field_id;
				}
			}

			if ( ! empty( $to_delete ) ) {
				$to_delete_sql = implode( ',', wp_parse_id_list( $to_delete ) );
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$bp->profile->table_name_data} WHERE user_id = %d AND field_id IN ({$to_delete_sql})", $user_id ) );
			}
		}

		$this->status['last_processed'] = $last + 499;
		update_site_option( 'cac-database-cleaner-status', $this->status );

		$retval['message'] = '. ';
		return $retval;
	}

	protected function bp_remove_non_public_groups() {
		$retval = array(
			'message' => '',
			'step_complete' => 0,
		);

		if ( ! function_exists( 'bp_is_active' ) || ! bp_is_active( 'groups' ) ) {
			$retval['message'] = __( 'BuddyPress Groups is not active.', 'cac-database-cleaner' );
			$retval['step_complete'] = 1;
			delete_site_option( 'cac-database-cleaner-status' );
			return $retval;
		}

		if ( ! $this->status['notified'] ) {
			$retval['message'] = __( 'Deleting all non-public BuddyPress groups and their data', 'cac-database-cleaner' );
			$this->status['notified'] = true;
			update_site_option( 'cac-database-cleaner-status', $this->status );
			return $retval;
		}

		$no = 1;

		global $wpdb, $bp;
		$last = intval( $this->status['last_processed'] );
		$group_ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$bp->groups->table_name} WHERE status != 'public' LIMIT %d, %d", $last, $last + $no ) );

		if ( empty( $group_ids ) ) {
			$retval['step_complete'] = 1;
			$retval['message'] = ' ' . __( 'Done!', 'cac-database-cleaner' );
			delete_site_option( 'cac-database-cleaner-status' );
			return $retval;
		}

		foreach ( $group_ids as $group_id ) {
			// Files (bp-group-documents)
			if ( ! empty( $bp->group_documents->table_name ) ) {
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$bp->group_documents->table_name} WHERE group_id = %d", $group_id ) );
			}

			// Docs (buddypress-docs)
			if ( defined( 'BP_DOCS_VERSION' ) && version_compare( BP_DOCS_VERSION, '1.2', '<' ) ) {
				$query_args = array(
					'tax_query' => array(
						array(
							'taxonomy' => $bp->bp_docs->associated_item_tax_name,
							'terms' => array( $group_id ),
							'field' => 'name',
							'operator' => 'IN',
							'include_children' => false
						),
					),
					'post_type' => $bp->bp_docs->post_type_name,
					'showposts' => '-1',
				);

				$group_docs = get_posts( $query_args );
				if ( ! empty( $group_docs ) ) {
					foreach ( $group_docs as $group_doc ) {
						wp_delete_post( $group_doc->ID, true );
					}
				}
			}

			// Activity
			if ( bp_is_active( 'groups' ) ) {
				bp_activity_delete( array(
					'component' => buddypress()->groups->id,
					'item_id' => $group_id,
				) );
			}

			// bbPress legacy - we run our own delete because of a
			// bug in the return values
			if ( function_exists( 'bp_forums_delete_group_forum' ) ) {
				$forum_id = groups_get_groupmeta( $group_id, 'forum_id' );

				$forum_id = intval( $forum_id );

				if ( ! empty( $forum_id ) ) {
					do_action( 'bbpress_init' );
					bb_delete_forum( $forum_id );
				}
			}

			// Group
			groups_delete_group( $group_id );
		}

		$this->status['last_processed'] = $last + $no - 1;
		update_site_option( 'cac-database-cleaner-status', $this->status );

		$retval['message'] = '. ';
		return $retval;
	}

	protected function bp_remove_private_messages() {
		$retval = array(
			'message' => '',
			'step_complete' => 0,
		);

		if ( ! function_exists( 'bp_is_active' ) || ! bp_is_active( 'messages' ) ) {
			$retval['message'] = __( 'BuddyPress Messages is not active.', 'cac-database-cleaner' );
			$retval['step_complete'] = 1;
			delete_site_option( 'cac-database-cleaner-status' );
			return $retval;
		}

		if ( ! $this->status['notified'] ) {
			$retval['message'] = __( 'Deleting all private messages...', 'cac-database-cleaner' );
			$this->status['notified'] = true;
			update_site_option( 'cac-database-cleaner-status', $this->status );
			return $retval;
		}

		global $wpdb, $bp;

		$wpdb->query( "DELETE FROM {$bp->messages->table_name_messages}" );
		$wpdb->query( "DELETE FROM {$bp->messages->table_name_recipients}" );

		$retval['step_complete'] = 1;
		$retval['message'] = ' ' . __( 'Done!', 'cac-database-cleaner' );

		delete_site_option( 'cac-database-cleaner-status' );

		return $retval;
	}

	protected function ms_remove_firestats_tables() {
		global $wpdb;

		$retval = array(
			'message' => '',
			'step_complete' => 0,
		);

		$tables = $wpdb->get_col( "SHOW TABLES LIKE '%_firestats_%'" );

		if ( ! empty( $tables ) ) {
			$tables_sql = implode( ',', array_map( 'esc_sql', $tables ) );
			$wpdb->query( "DROP TABLE {$tables_sql}" );
		}

		$retval['message'] = __( 'Dropped firestats tables.', 'cac-database-cleaner' );
		$retval['step_complete'] = 1;
		return $retval;
	}

	protected function ms_remove_non_public_blogs() {
		$retval = array(
			'message' => '',
			'step_complete' => 0,
		);

		if ( ! is_multisite() ) {
			$retval['message'] = __( 'This is not a Multisite installation, so there are no blogs to remove.', 'cac-database-cleaner' );
			$retval['step_complete'] = 1;
			delete_site_option( 'cac-database-cleaner-status' );
			return $retval;
		}

		if ( ! $this->status['notified'] ) {
			$retval['message'] = __( 'Deleting non-public blogs...', 'cac-database-cleaner' );
			$this->status['notified'] = true;
			update_site_option( 'cac-database-cleaner-status', $this->status );
			return $retval;
		}

		$no = 5;

		global $wpdb, $bp;
		$last = intval( $this->status['last_processed'] );
		$blogs = $wpdb->get_results( $wpdb->prepare( "SELECT blog_id, spam, deleted FROM {$wpdb->blogs} LIMIT %d, %d", $last, $last + $no ) );

		if ( empty( $blogs ) ) {
			$retval['step_complete'] = 1;
			$retval['message'] = ' ' . __( 'Done!', 'cac-database-cleaner' );
			delete_site_option( 'cac-database-cleaner-status' );
			return $retval;
		}

		foreach ( $blogs as $blog ) {
			if ( 1 == $blog->spam || 1 == $blog->deleted ) {
				wpmu_delete_blog( $blog->blog_id, true );
				break;
			}

			// Support for more-privacy-options
			$public = get_blog_option( $blog->blog_id, 'blog_public' );
			if ( (float) $public < 0 ) {
				wpmu_delete_blog( $blog->blog_id, true );
				break;
			}
		}

		$this->status['last_processed'] = $last + $no - 1;
		update_site_option( 'cac-database-cleaner-status', $this->status );

		$retval['message'] = '. ';
		return $retval;
	}

	protected function ms_remove_non_public_content() {
		$retval = array(
			'message' => '',
			'step_complete' => 0,
		);

		if ( ! is_multisite() ) {
			$retval['message'] = __( 'This is not a Multisite installation, so there are no blogs to clean.', 'cac-database-cleaner' );
			$retval['step_complete'] = 1;
			delete_site_option( 'cac-database-cleaner-status' );
			return $retval;
		}

		if ( ! $this->status['notified'] ) {
			$retval['message'] = __( 'Deleting non-public blog content...', 'cac-database-cleaner' );
			$this->status['notified'] = true;
			update_site_option( 'cac-database-cleaner-status', $this->status );
			return $retval;
		}

		$no = 3;

		global $wpdb, $bp;
		$last = intval( $this->status['last_processed'] );
		$blog_ids = $wpdb->get_col( $wpdb->prepare( "SELECT blog_id FROM {$wpdb->blogs} LIMIT %d, %d", $last, $last + $no ) );

		if ( empty( $blog_ids ) ) {
			$retval['step_complete'] = 1;
			$retval['message'] = ' ' . __( 'Done!', 'cac-database-cleaner' );
			delete_site_option( 'cac-database-cleaner-status' );
			return $retval;
		}

		foreach ( $blog_ids as $blog_id ) {
			switch_to_blog( $blog_id );

			// Non 'publish' posts
			$non_publish_posts = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_status != 'publish' AND post_type IN ( 'post', 'page' )" );
			foreach ( $non_publish_posts as $non_publish_post ) {
				wp_delete_post( $non_publish_post, true );
			}

			// Posts with passwords
			$password_posts = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_password != ''" );
			foreach ( $password_posts as $password_post ) {
				wp_delete_post( $password_post, true );
			}

			// Unpublished comments
			$unpublished_comments = $wpdb->get_col( "SELECT comment_ID FROM {$wpdb->comments} WHERE comment_approved != 1" );
			foreach ( $unpublished_comments as $unpublished_comment ) {
				wp_delete_comment( $unpublished_comment, true );
			}

			restore_current_blog();
		}

		$this->status['last_processed'] = $last + $no - 1;
		update_site_option( 'cac-database-cleaner-status', $this->status );

		$retval['message'] = '. ';
		return $retval;
	}
}
add_action( 'init', array( 'CAC_Database_Cleaner', 'init' ) );

