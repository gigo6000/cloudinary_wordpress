<?php
/**
 * Storage management options.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

namespace Cloudinary\Sync;

use Cloudinary\Component\Notice;
use Cloudinary\Sync;

/**
 * Class Filter.
 *
 * Handles filtering of HTML content.
 */
class Storage implements Notice {

	/**
	 * Holds the Plugin instance.
	 *
	 * @since   2.2.0
	 *
	 * @var     \Cloudinary\Plugin Instance of the plugin.
	 */
	protected $plugin;


	/**
	 * Holds the Plugin Media instance.
	 *
	 * @since   2.2.0
	 *
	 * @var     \Cloudinary\Media Instance of the media object.
	 */
	protected $media;

	/**
	 * Holds the Sync instance.
	 *
	 * @since   2.2.0
	 *
	 * @var     \Cloudinary\Sync Instance of the plugin.
	 */
	protected $sync;

	/**
	 * Holds the Download Sync instance.
	 *
	 * @since   2.2.0
	 *
	 * @var     \Cloudinary\Sync\Download_Sync Instance of the plugin.
	 */
	protected $download;

	/**
	 * Holds the Connect instance.
	 *
	 * @since   2.2.0
	 *
	 * @var     \Cloudinary\Connect Instance of the plugin.
	 */
	protected $connect;

	/**
	 * Holds an array of the storage settings.
	 *
	 * @var array
	 */
	protected $settings;

	/**
	 * Filter constructor.
	 *
	 * @param \Cloudinary\Plugin $plugin The plugin.
	 */
	public function __construct( \Cloudinary\Plugin $plugin ) {
		$this->plugin = $plugin;
		add_action( 'cloudinary_register_sync_types', array( $this, 'setup' ), 20 );
		// Add File validation sync.
		add_filter( 'cloudinary_sync_base_struct', array( $this, 'add_file_folder_validators' ) );
	}

	/**
	 * Add a validators for the file and folder sync type to allow skipping the upload if of-storage is on.
	 *
	 * @param array $sync_types The array of sync types.
	 *
	 * @return array
	 */
	public function add_file_folder_validators( $sync_types ) {

		if ( isset( $sync_types['file'] ) && ! isset( $sync_types['file']['validate'] ) ) {
			$sync_types['file']['validate'] = array( $this, 'validate_file_folder_sync' );
		}
		if ( isset( $sync_types['folder'] ) && ! isset( $sync_types['folder']['validate'] ) ) {
			$sync_types['folder']['required'] = array( $this, 'validate_file_folder_sync' );
		}

		return $sync_types;
	}

	/**
	 * Validator and Required check to skip file and folder required for of-storage.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return bool
	 */
	public function validate_file_folder_sync( $attachment_id ) {

		$return = true;
		// Check if this is not a Cloudinary URL.
		if ( 'cld' === $this->settings['offload'] ) {
			$file   = get_post_meta( $attachment_id, '_wp_attached_file', true );
			$return = ! $this->media->is_cloudinary_url( $file );
		}

		return $return;
	}

	/**
	 * Generate a signature for this sync type.
	 *
	 * @return string
	 */
	public function generate_signature( $attachment_id ) {
		return $this->settings['offload'] . $this->settings['low_res'] . $this->media->get_post_meta( $attachment_id, Sync::META_KEYS['public_id'], true );
	}

	/**
	 * Process the storage sync for an attachment.
	 *
	 * @param int $attachment_id The attachment ID.
	 */
	public function sync( $attachment_id ) {

		// Get the previous state of the attachment.
		$previous_state = $this->media->get_post_meta( $attachment_id, Sync::META_KEYS['storage'], true );
		switch ( $this->settings['offload'] ) {
			case 'cld':
				$this->remove_local_assets( $attachment_id );
				update_post_meta( $attachment_id, '_wp_attached_file', $this->media->cloudinary_url( $attachment_id ) );
				break;
			case 'dual_low':
				$url = $this->media->cloudinary_url( $attachment_id, 'full', array( array( 'effect' => 'blur:100', 'quality' => $this->settings['low_res'] . ':440' ) ), null, false, true );
				break;
			case 'dual_full':
				if ( ! empty( $previous_state ) && 'dual_full' !== $previous_state ) {
					// Only do this is it's changing a state.
					$url = $this->media->cloudinary_url( $attachment_id, '', array(), null, false, false );
				}
				break;
		}

		// If we have a URL, it means we have a new source to pull from.
		if ( ! empty( $url ) ) {
			// Ensure that we dont delete assets if the last state didn't have any.
			if ( 'cld' !== $previous_state ) {
				$this->remove_local_assets( $attachment_id );
			}
			$this->download->download_asset( $attachment_id, $url );
		}

		$this->sync->set_signature_item( $attachment_id, 'storage' );
		$this->sync->set_signature_item( $attachment_id, 'breakpoints' );
		$this->media->update_post_meta( $attachment_id, Sync::META_KEYS['storage'], $this->settings['offload'] ); // Save the state.
	}

	/**
	 * Remove all local files from an asset/attachment.
	 *
	 * @param $attachment_id
	 *
	 * @return bool
	 */
	protected function remove_local_assets( $attachment_id ) {
		// Delete local versions of images.
		$meta = wp_get_attachment_metadata( $attachment_id );

		return wp_delete_attachment_files( $attachment_id, $meta, array(), get_attached_file( $attachment_id ) );
	}

	/**
	 * Get the current status of the sync.
	 *
	 * @return string
	 */
	public function status() {
		$note = __( 'Syncing', 'cloudinary' );
		switch ( $this->settings['offload'] ) {
			case 'cld':
				$note = __( 'Removing local copies.', 'cloudinary' );
				break;
			case 'dual_low':
				$note = __( 'Reducing local resolutions', 'cloudinary' );
				break;
			case 'dual_full':
				$note = __( 'Rebuilding local copies.', 'cloudinary' );
				break;
		}

		return $note;
	}

	/**
	 * Get notices to display in admin.
	 *
	 * @return array
	 */
	public function get_notices() {
		$notices = array();
		if ( 'cld' === $this->settings['offload'] ) {
			$storage         = $this->connect->get_usage_stat( 'storage', 'used_percent' );
			$transformations = $this->connect->get_usage_stat( 'transformations', 'used_percent' );
			$bandwidth       = $this->connect->get_usage_stat( 'bandwidth', 'used_percent' );
			if ( 100 <= $storage || 100 <= $transformations || 100 <= $bandwidth ) {

				$html = array(
					'<div>' . __( 'You have reached one or more of your quota limits. Soon media may not be delivered. Since all assets are on Cloudinary, this will result in broken images.' ) . '</div>',
					'<div>' . __( 'To rectify this you have some options:' ) . '</div>',
					'<ol>',
					'<li>' . sprintf(
						__(
							'<a href="%1$s" target="_blank">%2$s</a>',
							'cloudinary'
						),
						'https://cloudinary.com/console/lui/upgrade_options',
						__( 'Upgrade your account', 'cloudinary' )
					) . '</li>',
					'<li>' . __( 'Set storage to Cloudinary low resolution on WordPress.' ) . '</li>',
					'<li>' . __( 'Cloudinary and WordPress' ) . '</li>',
					'</ol>',
				);

				$notices[] = array(
					'message'     => implode( '', $html ),
					'type'        => 'error',
					'dismissible' => true,
				);
			}
		}

		return $notices;
	}

	/**
	 * Add a deactivate class to the deactivate link to trigger a warning if storage is only on Cloudinary.
	 *
	 * @param array $actions The actions for the plugin.
	 *
	 * @return array
	 */
	public function tag_deactivate_link( $actions ) {
		if ( 'cld' === $this->settings['offload'] ) {
			$actions['deactivate'] = str_replace( '<a ', '<a class="cld-deactivate" ', $actions['deactivate'] );
		}

		return $actions;
	}

	/**
	 * Check if component is ready to run.
	 *
	 * @return bool
	 */
	public function is_ready() {
		return $this->sync && $this->media && $this->connect && $this->download;
	}

	/**
	 * Setup hooks for the filters.
	 */
	public function setup() {

		$this->sync     = $this->plugin->get_component( 'sync' );
		$this->connect  = $this->plugin->get_component( 'connect' );
		$this->media    = $this->plugin->get_component( 'media' );
		$this->download = $this->sync->managers['download'] ? $this->sync->managers['download'] : new Download_Sync( $plugin );

		if ( $this->is_ready() ) {
			$defaults       = array(
				'offload' => 'dual_full',
				'low_res' => '20',
			);
			$settings       = isset( $this->plugin->config['settings']['sync_media'] ) ? $this->plugin->config['settings']['sync_media'] : array();
			$this->settings = wp_parse_args( $settings, $defaults );
			$structure      = array(
				'generate' => array( $this, 'generate_signature' ),
				'priority' => 5.2,
				'sync'     => array( $this, 'sync' ),
				'state'    => 'info syncing',
				'note'     => array( $this, 'status' ),
			);
			$this->sync->register_sync_type( 'storage', $structure );

			// Tag the deactivate button.
			$plugin_file = pathinfo( dirname( CLDN_CORE ), PATHINFO_BASENAME ) . '/' . basename( CLDN_CORE );
			add_filter( 'plugin_action_links_' . $plugin_file, array( $this, 'tag_deactivate_link' ) );


		}
	}
}