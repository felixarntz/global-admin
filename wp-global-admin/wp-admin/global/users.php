<?php
/**
 * Multisite users administration panel.
 *
 * @package WPGlobalAdmin
 * @since 1.0.0
 */

/** Load WordPress Administration Bootstrap */
require_once( dirname( __FILE__ ) . '/admin.php' );

if ( ! is_multinetwork() ) {
	wp_die( __( 'Multinetwork support is not enabled.', 'wp-global-admin' ) );
}

if ( ! current_user_can( 'manage_global_users' ) ) {
	wp_die( __( 'You do not have permission to access this page.' ), 403 );
}

if ( isset( $_GET['action'] ) ) {

	// The following switch statement is an almost exact copy from wp-admin/network/users.php.
	switch ( $_GET['action'] ) {
		case 'deleteuser':
			check_admin_referer( 'deleteuser' );

			$id = intval( $_GET['id'] );
			if ( $id != '0' && $id != '1' ) {
				$_POST['allusers'] = array( $id ); // confirm_delete_users() can only handle with arrays
				$title             = __( 'Users' );
				$parent_file       = 'users.php';
				require_once( ABSPATH . 'wp-admin/admin-header.php' );
				echo '<div class="wrap">';
				confirm_delete_users( $_POST['allusers'] );
				echo '</div>';
				require_once( ABSPATH . 'wp-admin/admin-footer.php' );
			} else {
				wp_redirect( global_admin_url( 'users.php' ) );
			}
			exit();

		case 'allusers':
			if ( ( isset( $_POST['action'] ) || isset( $_POST['action2'] ) ) && isset( $_POST['allusers'] ) ) {
				check_admin_referer( 'bulk-users-global' );

				$doaction     = $_POST['action'] != -1 ? $_POST['action'] : $_POST['action2'];
				$userfunction = '';

				foreach ( (array) $_POST['allusers'] as $user_id ) {
					if ( ! empty( $user_id ) ) {
						switch ( $doaction ) {
							case 'delete':
								if ( ! current_user_can( 'delete_users' ) ) {
									wp_die( __( 'Sorry, you are not allowed to access this page.' ), 403 );
								}
								$title       = __( 'Users' );
								$parent_file = 'users.php';
								require_once( ABSPATH . 'wp-admin/admin-header.php' );
								echo '<div class="wrap">';
								confirm_delete_users( $_POST['allusers'] );
								echo '</div>';
								require_once( ABSPATH . 'wp-admin/admin-footer.php' );
								exit();

							case 'spam':
								$user = get_userdata( $user_id );
								if ( is_global_administrator( $user->ID ) || is_super_admin( $user->ID ) ) {
									wp_die( sprintf( __( 'Warning! User cannot be modified. The user %s is a network administrator.' ), esc_html( $user->user_login ) ) );
								}

								$userfunction = 'all_spam';
								$blogs        = get_blogs_of_user( $user_id, true );
								foreach ( (array) $blogs as $details ) {
									if ( $details->userblog_id != get_network()->site_id ) { // main blog not a spam !
										update_blog_status( $details->userblog_id, 'spam', '1' );
									}
								}
								update_user_status( $user_id, 'spam', '1' );
								break;

							case 'notspam':
								$userfunction = 'all_notspam';
								$blogs        = get_blogs_of_user( $user_id, true );
								foreach ( (array) $blogs as $details ) {
									update_blog_status( $details->userblog_id, 'spam', '0' );
								}

								update_user_status( $user_id, 'spam', '0' );
								break;
						}
					}
				}

				if ( ! in_array( $doaction, array( 'delete', 'spam', 'notspam' ), true ) ) {
					$sendback = wp_get_referer();

					$user_ids = (array) $_POST['allusers'];

					/**
					 * Fires when a custom bulk action should be handled.
					 *
					 * The redirect link should be modified with success or failure feedback
					 * from the action to be used to display feedback to the user.
					 *
					 * The dynamic portion of the hook name, `$screen`, refers to the current screen ID.
					 *
					 * @since 1.0.0
					 *
					 * @param string $redirect_url The redirect URL.
					 * @param string $action       The action being taken.
					 * @param array  $items        The items to take the action on.
					 * @param int    $site_id      The site ID.
					 */
					$sendback = apply_filters( 'handle_global_bulk_actions-' . get_current_screen()->id, $sendback, $doaction, $user_ids );

					wp_safe_redirect( $sendback );
					exit();
				}

				wp_safe_redirect(
					add_query_arg(
						array(
							'updated' => 'true',
							'action'  => $userfunction,
						), wp_get_referer()
					)
				);
			} else {
				$location = global_admin_url( 'users.php' );

				if ( ! empty( $_REQUEST['paged'] ) ) {
					$location = add_query_arg( 'paged', (int) $_REQUEST['paged'], $location );
				}
				wp_redirect( $location );
			}
			exit();

		case 'dodelete':
			check_admin_referer( 'ms-users-delete' );

			if ( ! empty( $_POST['blog'] ) && is_array( $_POST['blog'] ) ) {
				foreach ( $_POST['blog'] as $id => $users ) {
					foreach ( $users as $blogid => $user_id ) {
						if ( ! current_user_can( 'delete_user', $id ) ) {
							continue;
						}

						if ( ! empty( $_POST['delete'] ) && 'reassign' == $_POST['delete'][ $blogid ][ $id ] ) {
							remove_user_from_blog( $id, $blogid, $user_id );
						} else {
							remove_user_from_blog( $id, $blogid );
						}
					}
				}
			}
			$i = 0;
			if ( is_array( $_POST['user'] ) && ! empty( $_POST['user'] ) ) {
				foreach ( $_POST['user'] as $id ) {
					if ( ! current_user_can( 'delete_user', $id ) ) {
						continue;
					}
					wpmu_delete_user( $id );
					$i++;
				}
			}

			if ( $i == 1 ) {
				$deletefunction = 'delete';
			} else {
				$deletefunction = 'all_delete';
			}

			wp_redirect(
				add_query_arg(
					array(
						'updated' => 'true',
						'action'  => $deletefunction,
					), global_admin_url( 'users.php' )
				)
			);
			exit();
	}
}

require_once ABSPATH . 'wp-admin/includes/class-wp-ms-users-list-table.php';
require_once dirname( dirname( __FILE__ ) ) . '/includes/class-wp-ga-users-list-table.php';

$wp_list_table = new WP_GA_Users_List_Table( array( 'screen' => get_current_screen() ) );
$pagenum = $wp_list_table->get_pagenum();
$wp_list_table->prepare_items();
$total_pages = $wp_list_table->get_pagination_arg( 'total_pages' );

if ( $pagenum > $total_pages && $total_pages > 0 ) {
	wp_redirect( add_query_arg( 'paged', $total_pages ) );
	exit;
}
$title = __( 'Users' );
$parent_file = 'users.php';

add_screen_option( 'per_page' );

get_current_screen()->add_help_tab( array(
	'id'      => 'overview',
	'title'   => __( 'Overview', 'wp-global-admin' ),
	'content' =>
		'<p>' . __( 'This table shows all users across all networks and all sites.', 'wp-global-admin' ) . '</p>' .
		'<p>' . __( 'Hover over any user on the list to make the edit links appear. The Edit link on the left will take you to their Edit User profile page; the Edit link on the right by any site name goes to an Edit Site screen for that site.', 'wp-global-admin' ) . '</p>' .
		'<p>' . __( 'You can also go to the user&#8217;s profile page by clicking on the individual username.', 'wp-global-admin' ) . '</p>' .
		'<p>' . __( 'You can sort the table by clicking on any of the table headings and switch between list and excerpt views by using the icons above the users list.', 'wp-global-admin' ) . '</p>' .
		'<p>' . __( 'The bulk action will permanently delete selected users, or mark/unmark those selected as spam. Spam users will have posts removed and will be unable to sign up again with the same email addresses.', 'wp-global-admin' ) . '</p>' .
		'<p>' . __( 'You can make an existing user an additional global admin by going to the Edit User profile page and checking the box to grant that privilege.', 'wp-global-admin' ) . '</p>'
) );

get_current_screen()->set_help_sidebar(
	'<p><strong>' . __( 'For more information:', 'wp-global-admin' ) . '</strong></p>' .
	'<p>' . __( '<a href="https://github.com/felixarntz/wp-global-admin/wiki/Global-Admin-Users-Screen" target="_blank">Documentation on Global Users</a>', 'wp-global-admin' ) . '</p>'
);

get_current_screen()->set_screen_reader_content( array(
	'heading_views'      => __( 'Filter users list' ),
	'heading_pagination' => __( 'Users list navigation' ),
	'heading_list'       => __( 'Users list' ),
) );

require_once( ABSPATH . 'wp-admin/admin-header.php' );

if ( isset( $_REQUEST['updated'] ) && $_REQUEST['updated'] == 'true' && ! empty( $_REQUEST['action'] ) ) {
	?>
	<div id="message" class="updated notice is-dismissible"><p>
		<?php
		switch ( $_REQUEST['action'] ) {
			case 'delete':
				_e( 'User deleted.' );
			break;
			case 'all_spam':
				_e( 'Users marked as spam.' );
			break;
			case 'all_notspam':
				_e( 'Users removed from spam.' );
			break;
			case 'all_delete':
				_e( 'Users deleted.' );
			break;
			case 'add':
				_e( 'User added.' );
			break;
		}
		?>
	</p></div>
	<?php
}
	?>
<div class="wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Users' ); ?></h1>

	<?php if ( current_user_can( 'create_users' ) ) : ?>
		<a href="<?php echo global_admin_url( 'user-new.php' ); ?>" class="page-title-action"><?php echo esc_html_x( 'Add New', 'user' ); ?></a>
	<?php endif; ?>

	<?php
	if ( strlen( $usersearch ) ) {
		/* translators: %s: search keywords */
		printf( '<span class="subtitle">' . __( 'Search results for &#8220;%s&#8221;' ) . '</span>', esc_html( $usersearch ) );
	}
	?>

	<hr class="wp-header-end">

	<?php $wp_list_table->views(); ?>

	<form method="get" class="search-form">
		<?php $wp_list_table->search_box( __( 'Search Users' ), 'all-user' ); ?>
	</form>

	<form id="form-user-list" action="users.php?action=allusers" method="post">
		<?php $wp_list_table->display(); ?>
	</form>
</div>

<?php require_once( ABSPATH . 'wp-admin/admin-footer.php' ); ?>
