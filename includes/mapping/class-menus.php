<?php
/**
 * Menu Class.
 *
 * Handles menu functionality on both Event Organiser and CiviCRM screens.
 *
 * @package CiviCRM_Event_Organiser
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Event Organiser Menu Class.
 *
 * A class that encapsulates menu functionality on both Event Organiser and CiviCRM screens.
 *
 * @since 0.8.2
 */
class CEO_Menus {

	/**
	 * Plugin object.
	 *
	 * @since 0.8.2
	 * @access public
	 * @var CiviCRM_Event_Organiser
	 */
	public $plugin;

	/**
	 * WordPress object.
	 *
	 * @since 0.8.2
	 * @access public
	 * @var CEO_WordPress
	 */
	public $wordpress;

	/**
	 * Event Organiser object.
	 *
	 * @since 0.8.2
	 * @access public
	 * @var CEO_WordPress_EO
	 */
	public $eo;

	/**
	 * Constructor.
	 *
	 * @since 0.8.2
	 *
	 * @param CiviCRM_Event_Organiser $plugin The plugin object.
	 */
	public function __construct( $plugin ) {

		// Store references.
		$this->plugin = $plugin;

		// Add hooks when plugin is loaded.
		add_action( 'ceo/loaded', [ $this, 'initialise' ] );

	}

	/**
	 * Perform initialisation tasks.
	 *
	 * @since 0.8.2
	 */
	public function initialise() {

		// Store references.
		$this->wordpress = $this->plugin->wordpress;
		$this->eo        = $this->plugin->wordpress->eo;

		// Register hooks.
		$this->register_hooks();

	}

	/**
	 * Register hooks if Event Organiser is present.
	 *
	 * @since 0.8.2
	 */
	public function register_hooks() {

		// Check for Event Organiser.
		if ( ! $this->eo->is_active() ) {
			return;
		}

		// Maybe add a link to action links on the Events list table.
		add_action( 'post_row_actions', [ $this, 'item_add_to_row_actions' ], 10, 2 );

		// Add Menu Item to "Edit Event" and "View Event" menu in WordPress admin bar.
		add_action( 'admin_bar_menu', [ $this, 'item_add_to_wp' ], 1000 );

		/*
		// Maybe add a Menu Item to CiviCRM Admin Utilities menu.
		add_action( 'civicrm_admin_utilities_menu_top', [ $this, 'item_add_to_cau' ], 10, 2 );
		*/

		// Maybe add a Menu Item to the CiviCRM Event's "Event Links" menu.
		add_action( 'civicrm_alterContent', [ $this, 'item_add_to_civicrm' ], 10, 4 );

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Adds a link to the action links on the Events list table.
	 *
	 * Currently this only adds a link when there is a one-to-one mapping
	 * between an Event Organiser Event and a CiviCRM Event.
	 *
	 * @since 0.4.5
	 * @since 0.8.2 Moved to this class and renamed.
	 *
	 * @param array   $actions The array of row action links.
	 * @param WP_Post $post The WordPress Post object.
	 */
	public function item_add_to_row_actions( $actions, $post ) {

		// Bail if there's no Post object.
		if ( empty( $post ) ) {
			return $actions;
		}

		// Kick out if not Event.
		if ( 'event' !== $post->post_type ) {
			return $actions;
		}

		// Check permission.
		if ( ! $this->plugin->civi->check_permission( 'access CiviEvent' ) ) {
			return $actions;
		}

		// Get linked CiviCRM Events.
		$civicrm_events = $this->plugin->mapping->get_civi_event_ids_by_eo_event_id( $post->ID );

		// Bail if we get more than one.
		if ( empty( $civicrm_events ) || count( $civicrm_events ) > 1 ) {
			return $actions;
		}

		// Show them.
		foreach ( $civicrm_events as $civicrm_event_id ) {

			// Get link.
			$settings_link = $this->plugin->civi->event->get_settings_link( $civicrm_event_id );

			// Add link to actions.
			$actions['civicrm'] = sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( $settings_link ),
				esc_html__( 'CiviCRM', 'civicrm-event-organiser' )
			);

		}

		// --<
		return $actions;

	}

	/**
	 * Adds a Menu Item to the "View Event" or "Edit Event" menu.
	 *
	 * Currently this only adds a link when there is a one-to-one mapping
	 * between an Event Organiser Event and a CiviCRM Event.
	 *
	 * @since 0.8.2
	 *
	 * @param WP_Admin_Bar $wp_admin_bar The WP_Admin_Bar instance, passed by reference.
	 */
	public function item_add_to_wp( $wp_admin_bar ) {

		// Access WordPress admin bar.
		global $post;

		// Bail if there's no Post.
		if ( empty( $post ) ) {
			return;
		}

		// Bail if there's no Post and it's WordPress admin.
		if ( empty( $post ) && is_admin() ) {
			return;
		}

		// Kick out if not Event.
		if ( 'event' !== $post->post_type ) {
			return;
		}

		// Check permission.
		if ( ! $this->plugin->civi->check_permission( 'access CiviEvent' ) ) {
			return;
		}

		// Get linked CiviCRM Event IDs.
		$civicrm_events = $this->plugin->mapping->get_civi_event_ids_by_eo_event_id( $post->ID );

		// TODO: Consider how to display Repeating Events in menu.

		// Bail if we get more than one.
		if ( empty( $civicrm_events ) || count( $civicrm_events ) > 1 ) {
			return;
		}

		// Show them.
		foreach ( $civicrm_events as $civicrm_event_id ) {

			// Get link.
			$settings_link = $this->plugin->civi->event->get_settings_link( $civicrm_event_id );

			/*
			// Get CiviCRM Event.
			$civicrm_event = $this->plugin->civi->event->get_event_by_id( $civicrm_event_id );
			if ( $civicrm_event === false ) {
				continue;
			}

			// Get DateTime object.
			$start = new DateTime( $civicrm_event['start_date'], eo_get_blog_timezone() );

			// Construct date and time format.
			$format = get_option( 'date_format' );
			if ( ! eo_is_all_day( $event->ID ) ) {
				$format .= ' ' . get_option( 'time_format' );
			}

			// Get title as datetime string.
			$datetime_string = eo_format_datetime( $start, $format );

			// Construct list item content.
			$title = sprintf( __( 'Configure %s', 'civicrm-event-organiser' ), $datetime_string );
			*/

			// Add item to Preview menu.
			$args = [
				'id'     => 'cau-preview',
				'parent' => 'preview',
				'title'  => __( 'Configure in CiviCRM', 'civicrm-event-organiser' ),
				'href'   => $settings_link,
			];
			$wp_admin_bar->add_node( $args );

			// Add item to View menu.
			$args = [
				'id'     => 'cau-view',
				'parent' => 'view',
				'title'  => __( 'Configure in CiviCRM', 'civicrm-event-organiser' ),
				'href'   => $settings_link,
			];
			$wp_admin_bar->add_node( $args );

			// Add item to Edit menu.
			$args = [
				'id'     => 'cau-edit',
				'parent' => 'edit',
				'title'  => __( 'Configure in CiviCRM', 'civicrm-event-organiser' ),
				'href'   => $settings_link,
			];
			$wp_admin_bar->add_node( $args );

		}

	}

	/**
	 * Adds a Menu Item to the CiviCRM Admin Utilities menu.
	 *
	 * Currently this only adds a link when there is a one-to-one mapping
	 * between an Event Organiser Event and a CiviCRM Event.
	 *
	 * @since 0.4.5
	 * @since 0.8.2 Moved to this class and renamed.
	 *
	 * @param str   $id The menu parent ID.
	 * @param array $components The active CiviCRM Conponents.
	 */
	public function item_add_to_cau( $id, $components ) {

		// Access WordPress admin bar.
		global $wp_admin_bar, $post;

		// Bail if there's no Post.
		if ( empty( $post ) ) {
			return;
		}

		// Bail if there's no Post and it's WordPress admin.
		if ( empty( $post ) && is_admin() ) {
			return;
		}

		// Kick out if not Event.
		if ( 'event' !== $post->post_type ) {
			return;
		}

		// Check permission.
		if ( ! $this->plugin->civi->check_permission( 'access CiviEvent' ) ) {
			return;
		}

		// Get linked CiviCRM Events.
		$civicrm_events = $this->plugin->mapping->get_civi_event_ids_by_eo_event_id( $post->ID );

		// TODO: Consider how to display Repeating Events in menu.

		// Bail if we get more than one.
		if ( empty( $civicrm_events ) || count( $civicrm_events ) > 1 ) {
			return;
		}

		// Show them.
		foreach ( $civicrm_events as $civicrm_event_id ) {

			// Get link.
			$settings_link = $this->plugin->civi->event->get_settings_link( $civicrm_event_id );

			/*
			// Get CiviCRM Event.
			$civicrm_event = $this->plugin->civi->event->get_event_by_id( $civicrm_event_id );
			if ( $civicrm_event === false ) {
				continue;
			}

			// Get DateTime object.
			$start = new DateTime( $civicrm_event['start_date'], eo_get_blog_timezone() );

			// Construct date and time format.
			$format = get_option( 'date_format' );
			if ( ! eo_is_all_day( $event->ID ) ) {
				$format .= ' ' . get_option( 'time_format' );
			}

			// Get title as datetime string.
			$datetime_string = eo_format_datetime( $start, $format );

			// Construct list item content.
			$title = sprintf( __( 'Configure %s', 'civicrm-event-organiser' ), $datetime_string );
			*/

			// Define item.
			$node = [
				'id'     => 'cau-0',
				'parent' => $id,
				// 'parent' => 'edit',
				'title'  => __( 'Configure in CiviCRM', 'civicrm-event-organiser' ),
				'href'   => $settings_link,
			];

			// Add item to menu.
			$wp_admin_bar->add_node( $node );

		}

	}

	/**
	 * Adds a Menu Item to the CiviCRM Event's "Event Links" menu.
	 *
	 * @since 0.4.5
	 * @since 0.8.2 Moved to this class and renamed.
	 *
	 * @param str    $content The previously generated content.
	 * @param string $context The context of the content - 'page' or 'form'.
	 * @param string $tpl_name The name of the ".tpl" template file.
	 * @param object $object A reference to the page or form object.
	 */
	public function item_add_to_civicrm( &$content, $context, $tpl_name, &$object ) {

		// Bail if not a form.
		if ( 'form' !== $context ) {
			return;
		}

		// Bail if not our target template.
		if ( 'CRM/Event/Form/ManageEvent/Tab.tpl' !== $tpl_name ) {
			return;
		}

		/*
		 * We do this to Contact View = "CRM/Contact/Page/View/Summary.tpl" as
		 * well, though the actions hook may work.
		 */

		// Get the ID of the displayed Event.
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		if ( ! isset( $object->_defaultValues['id'] ) ) {
			return;
		}
		if ( ! is_numeric( $object->_defaultValues['id'] ) ) {
			return;
		}
		$event_id = (int) $object->_defaultValues['id'];
		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		// Get the Post ID that this Event is mapped to.
		$post_id = $this->plugin->mapping->get_eo_event_id_by_civi_event_id( $event_id );
		if ( false === $post_id ) {
			return;
		}

		// Build view link.
		$link_view = '<li><a class="crm-event-wordpress-view" href="' . get_permalink( $post_id ) . '">' .
			__( 'View Event in WordPress', 'civicrm-event-organiser' ) .
		'</a><li>' . "\n";

		// Add edit link if permissions allow.
		$link_edit = '';
		if ( current_user_can( 'edit_post', $post_id ) ) {
			$link_edit = '<li><a class="crm-event-wordpress-edit" href="' . get_edit_post_link( $post_id ) . '">' .
				__( 'Edit Event in WordPress', 'civicrm-event-organiser' ) .
			'</a><li>' . "\n";
		}

		// Build final link.
		$link = $link_view . $link_edit . '<li><a class="crm-event-info"';

		// Gulp, do the replace.
		$content = str_replace( '<li><a class="crm-event-info"', $link, $content );

	}

}
