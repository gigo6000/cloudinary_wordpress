<?php
/**
 * Cloudinary Delivery for delivery of cloudinary assets.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

use Cloudinary\Component\Setup;
use Cloudinary\Connect\Api;
use Cloudinary\Media\Filter;
use Cloudinary\Media\Global_Transformations;
use Cloudinary\Sync;
use Cloudinary\String_Replace;
use Cloudinary\UI\Component\HTML;
use Cloudinary\Delivery\Bypass;
use WP_Post;

/**
 * Plugin Delivery class.
 */
class Delivery implements Setup {

	/**
	 * Holds the core plugin.
	 *
	 * @var Plugin
	 */
	protected $plugin;

	/**
	 * Holds the Media component.
	 *
	 * @var Media
	 */
	protected $media;

	/**
	 * Holds the Media\Filter component.
	 *
	 * @var Filter
	 */
	protected $filter;

	/**
	 * Holds the Sync component.
	 *
	 * @var Sync
	 */
	protected $sync;

	/**
	 * Hold the Post ID.
	 *
	 * @var null|int
	 */
	protected $current_post_id = null;

	/**
	 * Holds the Bypass instance.
	 *
	 * @var Bypass
	 */
	protected $bypass;

	/**
	 * Holds a list of found and valid urls.
	 *
	 * @var array
	 */
	public $found_urls = array();

	/**
	 * Holds a list of known urls.
	 *
	 * @var array
	 */
	public $known = array();

	/**
	 * Holds the list of unknown URLS.
	 *
	 * @var array
	 */
	public $unknown = array();

	/**
	 * Holds a list of known urls with public_ids.
	 *
	 * @var array
	 */
	public $usable = array();

	/**
	 * Holds a list of known urls without public_ids.
	 *
	 * @var array
	 */
	public $unusable = array();

	/**
	 * The meta data cache key to store URLS.
	 *
	 * @var string
	 */
	const META_CACHE_KEY = '_cld_replacements';

	/**
	 * Holds the captured post contexts
	 *
	 * @var array
	 */
	protected $post_contexts = array();

	/**
	 * Component constructor.
	 *
	 * @param Plugin $plugin Global instance of the main plugin.
	 */
	public function __construct( Plugin $plugin ) {

		$this->plugin = $plugin;
		add_action( 'cloudinary_connected', array( $this, 'init' ) );
	}

	/**
	 * Init the class when cloudinary is connected.
	 */
	public function init() {
		$this->plugin->components['replace'] = new String_Replace( $this->plugin );
		$this->media                         = $this->plugin->get_component( 'media' );
		add_filter( 'cloudinary_filter_out_local', '__return_false' );
		add_action( 'update_option_cloudinary_media_display', array( $this, 'clear_cache' ) );
		add_action( 'cloudinary_flush_cache', array( $this, 'do_clear_cache' ) );
		add_action( 'cloudinary_unsync_asset', array( $this, 'unsync_size_relationship' ) );
		add_action( 'before_delete_post', array( $this, 'delete_size_relationship' ) );
		add_action( 'delete_attachment', array( $this, 'delete_size_relationship' ) );
		add_action( 'cloudinary_register_sync_types', array( $this, 'register_sync_type' ), 30 );
		add_action(
			'the_post',
			function ( $post ) {
				$this->post_contexts[] = $post->ID;
			}
		);

		// Add Bypass options.
		$this->bypass = new Bypass( $this->plugin );
	}

	/**
	 * Generate the delivery signature.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return string
	 */
	public function generate_signature( $attachment_id ) {
		static $sql;
		if ( ! $sql ) {
			$sql = Utils::get_table_sql();
		}
		$sizes              = $this->get_sized( $attachment_id );
		$public_id          = $this->media->has_public_id( $attachment_id ) ? $this->media->get_public_id( $attachment_id ) : null;
		$settings_signature = self::get_settings_signature();

		return wp_json_encode( $sizes ) . $public_id . $sql . $settings_signature;
	}

	/**
	 * Is attachment deliverable in the FE.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return bool
	 */
	public function is_deliverable( $attachment_id ) {
		$is = wp_attachment_is_image( $attachment_id ) || wp_attachment_is( 'video', $attachment_id );

		// Ensure that the attachment has dimensions to be delivered.
		if ( $is ) {
			$meta = wp_get_attachment_metadata( $attachment_id, true );
			$is   = ! empty( $meta['width'] ) && ! empty( $meta['height'] );
		}

		/**
		 * Filter deliverable attachments.
		 *
		 * @hook   cloudinary_is_deliverable
		 *
		 * @param $is            {bool} The default value.
		 * @param $attachment_id {int}  The attachment ID.
		 *
		 * @return {bool}
		 */
		return apply_filters( 'cloudinary_is_deliverable', $is, $attachment_id );
	}

	/**
	 * Create delivery entries.
	 *
	 * @param int $attachment_id The attachment ID.
	 */
	public function create_delivery( $attachment_id ) {
		$this->delete_size_relationship( $attachment_id );
		$size      = $this->get_sized( $attachment_id );
		$public_id = $this->media->has_public_id( $attachment_id ) ? $this->media->get_public_id( $attachment_id ) : null;
		$base      = $this->get_content_path();
		$sized_url = '';
		$wh        = '0x0';
		// Some attachments do not have Sizes.
		if ( ! empty( $size ) ) {
			$sized_url = $size['sized_url'];
			$wh        = $size['size'];
		}

		if ( empty( $sized_url ) ) {
			$sized_url = self::clean_url( wp_get_attachment_url( $attachment_id ), true );
		}
		self::create_size_relation( $attachment_id, $sized_url, $wh, $base );
		// Update public ID and type.
		self::update_size_relations_public_id( $attachment_id, $public_id );
		self::update_size_relations_state( $attachment_id, 'inherit' );
		$this->sync->set_signature_item( $attachment_id, 'delivery' );
	}

	/**
	 * Sync method for the relation type.
	 *
	 * @param int $attachment_id The attachment ID.
	 */
	public function update_relation( $attachment_id ) {
		$public_id = $this->media->has_public_id( $attachment_id ) ? $this->media->get_public_id( $attachment_id ) : null;
		// Update public ID and type.
		self::update_size_relations_public_id( $attachment_id, $public_id );
		$this->sync->set_signature_item( $attachment_id, 'relation' );
	}

	/**
	 * Add our delivery sync type.
	 */
	public function register_sync_type() {
		$structure = array(
			'asset_state' => 0,
			'generate'    => '__return_false',
			'priority'    => 0.5,
			'sync'        => array( $this, 'create_delivery' ),
			'validate'    => array( $this, 'is_deliverable' ),
			'state'       => '',
			'note'        => '',
			'realtime'    => true,
		);
		$this->sync->register_sync_type( 'delivery', $structure );

		$structure = array(
			'asset_state' => 1,
			'generate'    => array( $this, 'generate_signature' ), // Method to generate a signature.
			'priority'    => 50,
			'sync'        => array( $this, 'update_relation' ),
			'validate'    => array( $this, 'is_deliverable' ),
			'state'       => '',
			'note'        => '',
			'realtime'    => true,
		);
		$this->sync->register_sync_type( 'relation', $structure );

	}

	/**
	 * Get the base content path.
	 *
	 * @return string
	 */
	protected function get_content_path() {
		$dirs = wp_get_upload_dir();

		return ltrim( wp_parse_url( trailingslashit( $dirs['baseurl'] ), PHP_URL_PATH ), '/' );
	}

	/**
	 * Remove a delivery relationship on delete of a post.
	 *
	 * @param int $attachment_id The attachment ID.
	 */
	public function delete_size_relationship( $attachment_id ) {
		global $wpdb;

		$wpdb->delete( Utils::get_relationship_table(), array( 'post_id' => $attachment_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB

		do_action( 'cloudinary_flush_cache' );
	}

	/**
	 * Disable a delivery relationship on unsync.
	 *
	 * @param int $attachment_id The attachment ID.
	 */
	public function unsync_size_relationship( $attachment_id ) {
		self::update_size_relations_public_id( $attachment_id, null );
		self::update_size_relations_state( $attachment_id, 'disable' );
		self::update_size_relations_transformations( $attachment_id, null );

		do_action( 'cloudinary_flush_cache' );
	}

	/**
	 * Get the different sizes for an attachment.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return array
	 */
	public function get_sized( $attachment_id ) {
		static $sizes = array(), $registered_sizes;
		if ( ! $registered_sizes ) {
			$registered_sizes = wp_get_registered_image_subsizes();
		}
		if ( empty( $sizes[ $attachment_id ] ) ) {
			$sizes[ $attachment_id ] = array();
			$meta                    = wp_get_attachment_metadata( $attachment_id, true );
			if ( ! empty( $meta['width'] ) && ! empty( $meta['height'] ) ) {
				$local_url               = self::clean_url( $this->media->local_url( $attachment_id ), true );
				$sizes[ $attachment_id ] = array(
					'sized_url' => $local_url,
					'size'      => $meta['width'] . 'x' . $meta['height'],
				);

				return $sizes[ $attachment_id ];
			}
		}

		return $sizes[ $attachment_id ];
	}

	/**
	 * Update relationship public ID.
	 *
	 * @param int    $attachment_id The attachment ID.
	 * @param string $public_id     The public ID.
	 */
	public static function update_size_relations_public_id( $attachment_id, $public_id ) {
		global $wpdb;
		$data = array(
			'public_id' => $public_id,
		);
		$wpdb->update( Utils::get_relationship_table(), $data, array( 'post_id' => $attachment_id ), array( '%s' ), array( '%d' ) );// phpcs:ignore WordPress.DB

		do_action( 'cloudinary_flush_cache' );
	}

	/**
	 * Update relationship status.
	 *
	 * @param int    $attachment_id The attachment ID.
	 * @param string $state         The state to set.
	 */
	public static function update_size_relations_state( $attachment_id, $state ) {
		global $wpdb;
		$data = array(
			'post_state' => $state,
		);
		$wpdb->update( Utils::get_relationship_table(), $data, array( 'post_id' => $attachment_id ), array( '%s' ), array( '%d' ) );// phpcs:ignore WordPress.DB

		do_action( 'cloudinary_flush_cache' );
	}

	/**
	 * Update relationship transformations.
	 *
	 * @param int    $attachment_id   The attachment ID.
	 * @param string $transformations The transformations to set.
	 */
	public static function update_size_relations_transformations( $attachment_id, $transformations ) {
		global $wpdb;
		$data = array(
			'transformations' => $transformations,
		);
		$wpdb->update( Utils::get_relationship_table(), $data, array( 'post_id' => $attachment_id ), array( '%s' ), array( '%d' ) );// phpcs:ignore WordPress.DB

		do_action( 'cloudinary_flush_cache' );
	}

	/**
	 * Delete unneeded sizes in bulk by ID.
	 *
	 * @param array $ids The IDs to delete.
	 */
	public static function delete_bulk_size_relations( $ids ) {
		global $wpdb;
		$ids       = (array) $ids;
		$list      = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
		$tablename = Utils::get_relationship_table();
		$sql       = "DELETE from {$tablename} WHERE id IN( {$list} )";
		$prepared  = $wpdb->prepare( $sql, $ids ); // phpcs:ignore WordPress.DB

		$wpdb->query( $prepared );// phpcs:ignore WordPress.DB

		do_action( 'cloudinary_flush_cache' );
	}

	/**
	 * Create a size relationship.
	 *
	 * @param int    $attachment_id The attachment ID.
	 * @param string $sized_url     The sized url.
	 * @param string $size          The size in (width)x(height) format.
	 * @param string $parent_path   The path of the parent if external.
	 *
	 * @return false|int
	 */
	public static function create_size_relation( $attachment_id, $sized_url, $size = '0x0', $parent_path = '' ) {
		global $wpdb;
		static $media;
		if ( ! $media ) {
			$media = get_plugin_instance()->get_component( 'media' );
		}
		$type            = 'attachment' === get_post_type( $attachment_id ) ? 'media' : 'asset';
		$resource        = $media->get_resource_type( $attachment_id );
		$width_height    = explode( 'x', $size );
		$transformations = $media->get_transformation_from_meta( $attachment_id );
		$data            = array(
			'post_id'         => $attachment_id,
			'parent_path'     => $parent_path,
			'sized_url'       => $sized_url,
			'width'           => $width_height[0] ? $width_height[0] : 0,
			'height'          => $width_height[1] ? $width_height[1] : 0,
			'format'          => pathinfo( $sized_url, PATHINFO_EXTENSION ),
			'sync_type'       => $type,
			'post_state'      => 'inherit',
			'transformations' => ! empty( $transformations ) ? Api::generate_transformation_string( $transformations, $resource ) : null,
			'signature'       => self::get_settings_signature(),
		);

		$insert_id = false;
		$created   = $wpdb->replace( Utils::get_relationship_table(), $data ); // phpcs:ignore WordPress.DB
		if ( 0 < $created ) {
			$insert_id = $wpdb->insert_id;
		}

		return $insert_id;
	}

	/**
	 * Get a signature of the current settings that result in a sync check.
	 *
	 * @return string string
	 */
	public static function get_settings_signature() {
		static $signature;
		if ( ! $signature ) {
			$settings  = get_plugin_instance()->settings->get_value( 'cloudinary_url', 'sync_media' );
			$signature = md5( wp_json_encode( $settings ) );
		}

		return $signature;
	}

	/**
	 * Setup early needed hooks.
	 */
	protected function setup_hooks() {
		// Add filters.
		add_action( 'save_post', array( $this, 'remove_replace_cache' ) );
		add_action( 'cloudinary_string_replace', array( $this, 'catch_urls' ) );
		add_filter( 'post_thumbnail_html', array( $this, 'process_featured_image' ), 100, 3 );

		add_filter( 'cloudinary_current_post_id', array( $this, 'get_current_post_id' ) );
		add_filter( 'the_content', array( $this, 'add_post_id' ) );
		add_action( 'wp_resource_hints', array( $this, 'dns_prefetch' ), 10, 2 );

		// Clear cache on taxonomy update.
		$taxonomies = get_taxonomies( array( 'show_ui' => true ) );
		foreach ( $taxonomies as $taxonomy ) {
			add_action( "saved_{$taxonomy}", array( $this, 'clear_cache' ) );
		}
	}

	/**
	 * Add DNS prefetch link tag for assets.
	 *
	 * @param array  $urls          URLs to print for resource hints.
	 * @param string $relation_type The relation type the URLs are printed for, e.g. 'preconnect' or 'prerender'.
	 *
	 * @return array
	 */
	public function dns_prefetch( $urls, $relation_type ) {

		if ( 'dns-prefetch' === $relation_type || 'preconnect' === $relation_type ) {
			$urls[] = $this->media->base_url;
		}

		return $urls;
	}

	/**
	 * Clear cached meta.
	 */
	public function clear_cache() {

		/**
		 * Action to flush delivery caches.
		 *
		 * @hook   cloudinary_flush_cache
		 * @since  3.0.0
		 */
		do_action( 'cloudinary_flush_cache' );
	}

	/**
	 * Delete cached metadata.
	 *
	 * @hook cloudinary_flush_cache
	 */
	public function do_clear_cache() {
		delete_post_meta_by_key( self::META_CACHE_KEY );
	}

	/**
	 * Add the Post ID to images and videos.
	 *
	 * @param string $content The content.
	 *
	 * @return string
	 */
	public function add_post_id( $content ) {

		return str_replace(
			array(
				'wp-image-',
				'wp-video-',
			),
			array(
				'wp-post-' . get_the_ID() . ' wp-image-',
				'wp-post-' . get_the_ID() . ' wp-video-',
			),
			$content
		);
	}

	/**
	 * Get the current post ID.
	 *
	 * @return int|null
	 */
	public function get_current_post_id() {

		return $this->current_post_id ? $this->current_post_id : null;
	}

	/**
	 * Setup component.
	 */
	public function setup() {

		$this->filter = $this->media->filter;
		$this->sync   = $this->media->sync;

		$this->setup_hooks();
	}

	/**
	 * Init delivery.
	 */
	protected function init_delivery() {

		add_filter( 'wp_calculate_image_srcset', array( $this->media, 'image_srcset' ), 10, 5 );

		/**
		 * Action indicating that the delivery is starting.
		 *
		 * @hook  cloudinary_init_delivery
		 * @since 2.7.5
		 *
		 * @param $delivery {Delivery} The delivery object.
		 */
		do_action( 'cloudinary_init_delivery', $this );
	}

	/**
	 * Add classes to the featured image tag.
	 *
	 * @param string $html          The image tah HTML to add to.
	 * @param int    $post_id       Ignored.
	 * @param int    $attachment_id The attachment_id.
	 *
	 * @return string
	 */
	public function process_featured_image( $html, $post_id, $attachment_id ) {

		if ( empty( $html ) ) {
			return $html; // Ignore empty tags.
		}
		// Get tag element.
		$tag_element                    = $this->parse_element( $html );
		$tag_element['id']              = $attachment_id;
		$tag_element['context']         = $post_id;
		$tag_element['atts']['class'][] = 'wp-image-' . $attachment_id;
		$tag_element['atts']['class'][] = 'wp-post-' . $post_id;

		if ( true === (bool) get_post_meta( $post_id, Global_Transformations::META_FEATURED_IMAGE_KEY, true ) ) {
			$tag_element['atts']['class'][] = 'cld-overwrite';
		}

		return HTML::build_tag( $tag_element['tag'], $tag_element['atts'] );
	}

	/**
	 * Delete the content replacement cache data.
	 *
	 * @param int $post_id The post ID to remove cache from.
	 */
	public function remove_replace_cache( $post_id ) {

		delete_post_meta( $post_id, self::META_CACHE_KEY );
	}

	/**
	 * Find the attachment sizes from a list of URLS.
	 */
	public function find_attachment_size_urls() {

		global $wpdb;
		$dirs    = wp_get_upload_dir();
		$search  = array();
		$baseurl = self::clean_url( $dirs['baseurl'] );
		foreach ( $this->unknown as $url ) {
			$url      = ltrim( str_replace( $baseurl, '', $url ), '/' );
			$search[] = $url;
		}

		$search = array_unique( $search );
		$in     = implode( ',', array_fill( 0, count( $search ), '%s' ) );

		// Prepare a query to find all in a single request.
		$sql = $wpdb->prepare(
			"SELECT post_id, meta_value FROM $wpdb->postmeta WHERE meta_key = '_wp_attached_file' AND meta_value IN ({$in}) limit 1000", // phpcs:ignore WordPress.DB
			$search
		);

		$key       = md5( $sql );
		$cached    = wp_cache_get( $key );
		$auto_sync = $this->sync->is_auto_sync_enabled();
		if ( false === $cached ) {
			$cached  = array();
			$results = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared

			if ( $results ) {
				foreach ( $results as $result ) {
					// If we are here, it means that an attachment in the media library doesn't have a delivery for the url.
					// Reset the signature for delivery and add to sync, to update it.
					$this->create_delivery( $result->post_id );
					if ( true === $auto_sync ) {
						$this->sync->add_to_sync( $result->post_id );
					}
					$size                         = $this->get_sized( $result->post_id );
					$cached[ $size['sized_url'] ] = (int) $result->post_id;
				}
			}
			wp_cache_add( $key, $cached );
		}

		$this->known   = array_merge( $this->known, $cached );
		$this->unknown = array_diff_key( $this->unknown, $this->known );
	}

	/**
	 * Get all the caches from found contexts.
	 *
	 * @return array
	 */
	protected function get_context_cache() {
		$cached = array();
		foreach ( $this->post_contexts as $id ) {
			$has_cache = get_post_meta( $id, self::META_CACHE_KEY, true );
			if ( ! empty( $has_cache ) ) {
				foreach ( $has_cache as $type => $cache ) {
					if ( ! isset( $cached[ $type ] ) ) {
						$cached[ $type ] = array();
					}
					$cached[ $type ] = array_merge( $cached[ $type ], $cache );
				}
			}
		}

		return $cached;
	}

	/**
	 * Get all image and video tags that match our found urls.
	 *
	 * @param string $content HTML content.
	 * @param string $tags    List of tags to get.
	 *
	 * @return array The media tags found.
	 */
	public function get_media_tags( $content, $tags = 'img|video' ) {
		$images = array();
		$urls   = '';
		if ( preg_match_all( '#(?P<tags><(' . $tags . ')[^>]*\>){1}#is', $content, $found ) ) {
			$count = count( $found[0] );
			for ( $i = 0; $i < $count; $i ++ ) {
				$images[ $i ] = $found['tags'][ $i ];
			}
		}

		return $images;
	}

	/**
	 * Convert media tags from Local to Cloudinary, and register with String_Replace.
	 *
	 * @param string $content The HTML to find tags and prep replacement in.
	 *
	 * @return array
	 */
	public function convert_tags( $content ) {
		$has_cache = $this->get_context_cache();
		$type      = is_ssl() ? 'https' : 'http';
		if ( Utils::is_amp() ) {
			$type = 'amp';
		}
		if ( ! empty( $has_cache[ $type ] ) ) {
			$cached = $has_cache[ $type ];
		}

		$tags = $this->get_media_tags( $content, 'img|video|article' );
		$tags = array_map( array( $this, 'parse_element' ), $tags );
		$tags = array_filter( $tags );

		$replacements = array();
		foreach ( $tags as $set ) {

			// Check cache and skip if needed.
			if ( isset( $replacements[ $set['original'] ] ) ) {
				continue;
			}
			/**
			 * Filter id from the tag.
			 *
			 * @hook   cloudinary_delivery_get_id
			 * @since  2.7.6
			 *
			 * @param $attachment_id {int}    The attachment ID.
			 * @param $tag_element   {array}  The tag element.
			 *
			 * @return {int|false}
			 */
			$set['id'] = apply_filters( 'cloudinary_delivery_get_id', $set['id'], $set );
			if ( empty( $set['id'] ) ) {
				continue;
			}
			$this->current_post_id = $set['context'];
			// Use cached item if found.
			if ( isset( $cached[ $set['original'] ] ) ) {
				$replacements[ $set['original'] ] = $cached[ $set['original'] ];
			} else {
				// Register replacement.
				$replacements[ $set['original'] ] = $this->rebuild_tag( $set );
			}
			$this->current_post_id = null;
		}

		// Update the post meta cache.
		if ( is_singular() ) {
			$has_cache          = array();
			$has_cache[ $type ] = $replacements;
			update_post_meta( get_the_ID(), self::META_CACHE_KEY, $has_cache );
		}

		return $replacements;
	}

	/**
	 * Cleanup and standardize the tag element structure.
	 *
	 * @param array $tag_element The tag element.
	 *
	 * @return array
	 */
	protected function standardize_tag( $tag_element ) {

		$default = array(
			'width'  => $tag_element['width'],
			'height' => $tag_element['height'],
		);
		if ( 'video' === $tag_element['tag'] ) {
			// Video is handled different with sizes, so we dont set default widths and heights.
			$default = array();
		}
		// Add default.
		$tag_element['atts'] = wp_parse_args( $tag_element['atts'], $default );
		// Add format if it's set.
		if ( isset( $tag_element['format'] ) ) {
			$tag_element['atts']['data-format'] = $tag_element['format'];
		}
		// Add wp-{media-type}-{id} class name.
		if ( empty( $tag_element['atts']['class'] ) || ! in_array( 'wp-' . $tag_element['type'] . '-' . $tag_element['id'], $tag_element['atts']['class'] ) ) {
			$tag_element['atts']['class'][] = 'wp-' . $tag_element['type'] . '-' . $tag_element['id'];
		}

		// Get size.
		$size = array(
			$tag_element['atts']['width'],
			$tag_element['atts']['height'],
		);
		if ( ! empty( $tag_element['atts']['src'] ) ) {
			$has_wp_size = $this->media->get_crop( $tag_element['atts']['src'], $tag_element['id'] );
			if ( ! empty( $has_wp_size ) ) {
				$size = $has_wp_size;
			}
		}
		// Unset srcset and sizes.
		unset( $tag_element['atts']['srcset'], $tag_element['atts']['sizes'] );

		$public_id = $tag_element['atts']['data-public-id'];

		// Get cloudinary URL.
		$url = $this->media->cloudinary_url(
			$tag_element['id'],
			$size,
			$tag_element['transformations'],
			$public_id,
			$tag_element['overwrite_transformations']
		);

		// Set the src.
		$tag_element['atts']['src'] = $url;
		// Convert any URLS that exist in attributes ( 3rd party lazyload etc..).
		foreach ( $tag_element['atts'] as &$att ) {
			if ( is_array( $att ) ) {
				continue;
			}
			$parts = array_filter( explode( ' ', $att ) );
			foreach ( $parts as &$part ) {
				if ( $this->validate_url( $part ) ) {
					$has_wp_size = $this->media->get_crop( $part, $tag_element['id'] );
					if ( ! empty( $has_wp_size ) ) {
						$size = $has_wp_size;
					}
					$part = $this->media->cloudinary_url(
						$tag_element['id'],
						$size,
						$tag_element['transformations'],
						$public_id,
						$tag_element['overwrite_transformations']
					);
				}
			}
			$att = implode( ' ', $parts );
		}

		// Add transformations attribute.
		$transformations = $this->media->get_transformations_from_string( $tag_element['atts']['src'] );
		if ( false !== $this->media->get_crop_from_transformation( $transformations ) ) {
			array_shift( $transformations );
		}
		$tag_element['atts']['data-transformations'] = API::generate_transformation_string( $transformations, $tag_element['type'] );

		if ( current_user_can( 'manage_options' ) && 'on' === $this->plugin->settings->image_settings->_overlay ) {
			$local_size = get_post_meta( $tag_element['id'], Sync::META_KEYS['local_size'], true );
			if ( empty( $local_size ) && file_exists( get_attached_file( $tag_element['id'] ) ) ) {
				$local_size = filesize( get_attached_file( $tag_element['id'] ) );
			}
			$remote_size                           = get_post_meta( $tag_element['id'], Sync::META_KEYS['remote_size'], true );
			$tag_element['atts']['data-filesize']  = size_format( $local_size );
			$tag_element['atts']['data-optsize']   = size_format( $remote_size );
			$tag_element['atts']['data-optformat'] = get_post_meta( $tag_element['id'], Sync::META_KEYS['remote_format'], true );
			if ( ! empty( $local_size ) && ! empty( $remote_size ) ) {
				$diff                                = $local_size - $remote_size;
				$tag_element['atts']['data-percent'] = round( $diff / $local_size * 100, 1 );
			}

			$base_url                              = $this->plugin->settings->get_url( 'edit_asset' );
			$tag_element['atts']['data-permalink'] = add_query_arg( 'asset', $tag_element['id'], $base_url );
		}

		$tag_element['atts']['data-version'] = $this->media->get_cloudinary_version( $tag_element['id'] );

		return $tag_element;
	}

	/**
	 * Rebuild a tag with cloudinary urls.
	 *
	 * @param array $tag_element The original HTML tag.
	 *
	 * @return string
	 */
	public function rebuild_tag( $tag_element ) {

		$tag_element = $this->standardize_tag( $tag_element );

		/**
		 * Filter to allow stopping default srcset generation.
		 *
		 * @hook   cloudinary_apply_breakpoints
		 * @since  3.0.0
		 * @default {true}
		 *
		 * @param $apply {bool}  True to apply, false to skip.
		 *
		 * @return {bool}
		 */
		if ( apply_filters( 'cloudinary_apply_breakpoints', true ) ) {
			$meta = wp_get_attachment_metadata( $tag_element['id'] );
			if ( ! empty( $meta['width'] ) && ! empty( $meta['height'] ) ) {
				// Check overwrite.
				$meta['overwrite_transformations'] = $tag_element['overwrite_transformations'];
				$meta['cloudinary_id']             = $tag_element['atts']['data-public-id'];
				// Add new srcset.
				$element = wp_image_add_srcset_and_sizes( $tag_element['original'], $meta, $tag_element['id'] );

				$atts = Utils::get_tag_attributes( $element );
				if ( ! empty( $atts['srcset'] ) ) {
					$tag_element['atts']['srcset'] = $atts['srcset'];
				}
				if ( ! empty( $atts['sizes'] ) ) {
					$tag_element['atts']['sizes'] = $atts['sizes'];
				}
			}
		}

		/**
		 * Filter the tag element.
		 *
		 * @hook   cloudinary_pre_image_tag | cloudinary_pre_video_tag
		 * @since  2.7.5
		 *
		 * @param $tag_element {array}  The tag_element (tag + attributes array).
		 *
		 * @return {array}
		 */
		$tag_element = apply_filters( "cloudinary_pre_{$tag_element['type']}_tag", $tag_element );

		// Setup new tag.
		$replace = HTML::build_tag( $tag_element['tag'], $tag_element['atts'] );

		/**
		 * Filter the new built tag element.
		 *
		 * @hook   cloudinary_image_tag | cloudinary_video_tag
		 * @since  3.0.0
		 *
		 * @param $replace     {string} The new HTML tag.
		 * @param $tag_element {array}  The tag_element (tag + attributes array).
		 *
		 * @return {array}
		 */
		return apply_filters( "cloudinary_{$tag_element['type']}_tag", $replace, $tag_element );
	}

	/**
	 * Parse an html element into tag, and attributes.
	 *
	 * @param string $element The HTML element.
	 *
	 * @return array
	 */
	public function parse_element( $element ) {
		static $post_context = 0;

		$tag_element = array(
			'tag'                       => '',
			'atts'                      => array(),
			'original'                  => $element,
			'overwrite_transformations' => false,
			'context'                   => 0,
			'id'                        => 0,
			'type'                      => '',
			'delivery'                  => 'wp',
			'breakpoints'               => true,
			'transformations'           => array(),
			'width'                     => 0,
			'height'                    => 0,
		);
		// Cleanup element.
		$element = trim( $element, '</>' );

		// Break element up.
		$attributes         = shortcode_parse_atts( $element );
		$tag_element['tag'] = array_shift( $attributes );
		// Context Switch Check.
		if ( 'article' === $tag_element['tag'] ) {
			if ( ! empty( $attributes['id'] ) && false !== strpos( $attributes['id'], 'post-' ) ) {
				$post_context = intval( substr( $attributes['id'], 5 ) );
			}

			return null;
		}
		$tag_element['type'] = 'img' === $tag_element['tag'] ? 'image' : $tag_element['tag'];
		$raw_url             = isset( $attributes['src'] ) ? $this->sanitize_url( $attributes['src'] ) : '';
		if ( empty( $raw_url ) ) {
			foreach ( $attributes as $attribute ) {
				// Attempt to find a src.
				if ( $this->validate_url( $attribute ) ) {
					$raw_url = $this->sanitize_url( $attribute );
					break;
				}
			}
		}
		$url = self::maybe_unsize_url( self::clean_url( $raw_url ) );

		// Track back the found URL.
		if ( $this->media->is_cloudinary_url( $raw_url ) ) {
			$public_id = $this->media->get_public_id_from_url( $raw_url );
			if ( isset( $this->known[ $public_id ] ) ) {

				$url               = $this->known[ $public_id ]['sized_url'];
				$attributes['src'] = $url;
			}
		}
		$tag_element['context'] = $post_context;
		if ( ! empty( $this->known[ $url ] ) && ! empty( $this->known[ $url ]['public_id'] ) ) {
			$item = $this->known[ $url ];
			if ( ! empty( $item['transformations'] ) ) {
				$tag_element['transformations'] = $this->media->get_transformations_from_string( $item['transformations'], $tag_element['type'] );
			}
			// Get the public ID and append the extension if it's missing.
			$public_id = $item['public_id'];
			if ( strrchr( $public_id, '.' ) !== '.' . $item['format'] ) {
				$public_id .= '.' . $item['format'];
			}
			$tag_element['id']            = (int) $item['post_id'];
			$tag_element['width']         = $item['width'];
			$tag_element['height']        = $item['height'];
			$attributes['data-public-id'] = $public_id;
			$tag_element['format']        = $item['format'];
		}

		if ( ! empty( $attributes['class'] ) ) {
			if ( preg_match( '/wp-post-(\d+)+/', $attributes['class'], $match ) ) {
				$tag_element['context'] = (int) $match[1];
				$post_context           = $tag_element['context'];
			}

			$attributes['class'] = explode( ' ', $attributes['class'] );
			if ( in_array( 'cld-overwrite', $attributes['class'], true ) ) {
				$tag_element['overwrite_transformations'] = true;
			}
		} else {
			$attributes['class'] = array(
				'wp-post-' . $tag_element['context'],
			);
		}

		$inline_transformations = $this->get_transformations_maybe( $url );
		if ( $inline_transformations ) {
			$tag_element['transformations'] = array_merge( $tag_element['transformations'], $inline_transformations );
		}

		// Check if ID was found, and upgrade if needed.
		if ( empty( $tag_element['id'] ) ) {
			// Old method to aid in upgrade from 2.7.7.
			$maybe_id = $this->media->filter->get_id_from_tag( $tag_element['original'] );
			if ( ! empty( $maybe_id ) ) {
				$cloudinary_id_maybe = $this->media->cloudinary_id( $maybe_id );
				if ( ! empty( $cloudinary_id_maybe ) ) {
					$tag_element['id']                     = $maybe_id;
					$meta                                  = wp_get_attachment_metadata( $maybe_id );
					$tag_element['width']                  = ! empty( $meta['width'] ) ? $meta['width'] : 0;
					$tag_element['height']                 = ! empty( $meta['height'] ) ? $meta['height'] : 0;
					$tag_element['atts']['data-public-id'] = $cloudinary_id_maybe;
					$tag_element['format']                 = pathinfo( $cloudinary_id_maybe, PATHINFO_EXTENSION );
				}
			}
		}
		// Set atts.
		$tag_element['atts'] = wp_parse_args( $attributes, $tag_element['atts'] );

		return $tag_element;
	}

	/**
	 * Maybe get the inline transformations from an image url.
	 *
	 * @param string $url The image src url.
	 *
	 * @return array|null
	 */
	protected function get_transformations_maybe( $url ) {

		$transformations = null;
		$query           = wp_parse_url( $url, PHP_URL_QUERY );
		if ( ! empty( $query ) && false !== strpos( $query, 'cld_params' ) ) {
			// Has params in src.
			$args = array();
			wp_parse_str( $query, $args );
			$transformations = $this->media->get_transformations_from_string( $args['cld_params'] );
		}

		return $transformations;
	}

	/**
	 * Checks if a url path is for a local content directory.
	 *
	 * @param string $url The url to check.
	 *
	 * @return bool
	 */
	protected function is_content_dir( $url ) {
		static $base = '';
		if ( empty( $base ) ) {
			$dirs = wp_upload_dir();
			$base = wp_parse_url( $dirs['baseurl'], PHP_URL_PATH );
		}
		$path     = wp_parse_url( $url, PHP_URL_PATH );
		$is_local = substr( $path, 0, strlen( $base ) ) === $base;

		/**
		 * Filter if the url is a local asset.
		 *
		 * @hook   cloudinary_pre_image_tag
		 * @since  2.7.6
		 *
		 * @param $is_local {bool}   If the url is a local asset.
		 * @param $url      {string} The url.
		 *
		 * @return {bool}
		 */
		return apply_filters( 'cloudinary_is_content_dir', $is_local, $url );
	}

	/**
	 * Clean a url: adds scheme if missing, removes query and fragments.
	 *
	 * @param string $url         The URL to clean.
	 * @param bool   $scheme_less Flag to clean out scheme.
	 *
	 * @return string
	 */
	public static function clean_url( $url, $scheme_less = true ) {

		$default = array(
			'scheme' => '',
			'host'   => '',
			'path'   => '',
		);
		$parts   = wp_parse_args( wp_parse_url( $url ), $default );

		$url = '//' . $parts['host'] . $parts['path'];
		if ( false === $scheme_less ) {
			$url = $parts['scheme'] . ':' . $url;
		}

		return $url;
	}

	/**
	 * Check if the file type is allowed to be uploaded.
	 *
	 * @param string $ext The filetype extension.
	 *
	 * @return bool
	 */
	protected function is_allowed_type( $ext ) {
		static $allowed_types = array();
		if ( empty( $allowed_types ) ) {
			$compatible_types = $this->media->get_compatible_media_types();
			// Check with paths.
			$types = wp_get_ext_types();
			foreach ( $compatible_types as $type ) {
				if ( isset( $types[ $type ] ) ) {
					$allowed_types = array_merge( $allowed_types, $types[ $type ] );
				}
			}
			/**
			 * Filter the allowed file extensions to be delivered.
			 *
			 * @hook   cloudinary_allowed_extensions
			 * @since  3.0.0
			 *
			 * @param $allowed_types {array}  Array of allowed file extensions.
			 *
			 * @return {array}
			 */
			$allowed_types = apply_filters( 'cloudinary_allowed_extensions', $allowed_types );
		}

		return in_array( $ext, $allowed_types, true );
	}

	/**
	 * Filter out excluded urls.
	 *
	 * @param string $url The url to filter out.
	 *
	 * @return bool
	 */
	public function validate_url( $url ) {
		static $home;
		if ( ! $home ) {
			$home = wp_parse_url( home_url( '/' ) );
		}
		$parts = wp_parse_url( $url );
		if ( empty( $parts['host'] ) ) {
			return false; // If host is empty, it's a false positive url.
		}
		if ( empty( $parts['path'] ) || '/' === $parts['path'] ) {
			return false; // exclude base domains.
		}
		$ext = pathinfo( $parts['path'], PATHINFO_EXTENSION );
		if ( empty( $ext ) || ! $this->is_allowed_type( $ext ) ) {
			return false;
		}

		return $parts['host'] === $home['host'] ? $this->is_content_dir( $url ) : $this->media->can_upload_from_host( $parts['host'] );
	}

	/**
	 * Set url usability.
	 *
	 * @param object    $item      The item object result.
	 * @param null|bool $auto_sync If auto_sync is on.
	 */
	protected function set_usability( $item, $auto_sync = null ) {

		$this->known[ $item['public_id'] ] = $item;
		$scaled                            = self::make_scaled_url( $item['sized_url'] );
		$descaled                          = self::descaled_url( $item['sized_url'] );
		$this->known[ $scaled ]            = $item;
		$this->known[ $descaled ]          = $item;

		if ( 'disable' === $item['post_state'] ) {
			return;
		}

		$is_media = 'media' === $item['sync_type'];
		$is_asset = 'inherit' !== $item['post_state'];

		if ( true === $auto_sync && true === $is_media && true === $is_asset ) {
			// Auto sync on - synced as asset - take over.
			$this->sync->delete_cloudinary_meta( $item['post_id'] );
			$this->sync->add_to_sync( $item['post_id'] );
		} elseif ( true === $auto_sync && true === $is_media && empty( $item['public_id'] ) ) {
			// Un-synced media item with auto sync on. Add to sync.
			$this->sync->add_to_sync( $item['post_id'] );
		} elseif ( ! empty( $item['public_id'] ) ) {
			// Most likely an asset with a public ID.
			$this->usable[ $item['sized_url'] ] = $item['sized_url'];
			if ( self::get_settings_signature() !== $item['signature'] ) {
				$this->sync->add_to_sync( $item['post_id'] );
			}
		} else {
			// This is an asset or media without a public id.
			$this->unusable[ $item['sized_url'] ] = $item;
		}
	}

	/**
	 * Sanitize a url.
	 *
	 * @param string $url URL to sanitize.
	 *
	 * @return string|null
	 */
	protected function sanitize_url( $url ) {

		// Catch mixed URLs.
		if ( 5 < strlen( $url ) && false !== strpos( $url, 'https://', 5 ) ) {
			$url = substr( $url, strpos( $url, 'https://', 5 ) );
		}

		// Remove Query string.
		$url = strtok( $url, '?' );

		// check the url is more than a domain.
		if ( ! filter_var( utf8_uri_encode( $url ), FILTER_VALIDATE_URL ) || 3 > substr_count( rtrim( $url, '/' ), '/' ) ) {
			$url = null;
		}

		return $url;
	}

	/**
	 * Maybe remove a size from a URL.
	 *
	 * @param string $url The url to remove size from.
	 *
	 * @return string
	 */
	public static function maybe_unsize_url( $url ) {
		$file = pathinfo( $url, PATHINFO_FILENAME );
		$dash = strrchr( $file, '-' );
		if ( false !== $dash && 1 === substr_count( $dash, 'x' ) ) {
			if ( is_numeric( str_replace( 'x', '', ltrim( $dash, '-' ) ) ) ) {
				$url = str_replace( $dash, '', $url );
			}
		}

		return $url;
	}

	/**
	 * Make a scaled version.
	 *
	 * @param string $url The url to make scaled.
	 *
	 * @return string
	 */
	public static function make_scaled_url( $url ) {
		$file = pathinfo( $url );
		$dash = strrchr( $file['filename'], '-' );
		if ( '-scaled' === $dash ) {
			return $url;
		}

		return $file['dirname'] . '/' . $file['filename'] . '-scaled.' . $file['extension'];
	}

	/**
	 * Make a descaled version.
	 *
	 * @param string $url The url to descaled.
	 *
	 * @return string
	 */
	public static function descaled_url( $url ) {
		$file = pathinfo( $url );
		$dash = strrchr( $file['filename'], '-' );
		if ( '-scaled' === $dash ) {
			$file['basename'] = str_replace( '-scaled.', '.', $file['basename'] );
			$url              = $file['dirname'] . '/' . $file['basename'];
		}

		return $url;
	}

	/**
	 * Get urls from HTML.
	 *
	 * @param string $content The content html.
	 */
	protected function get_urls( $content ) {
		global $wpdb;
		$all_urls   = array_unique( wp_extract_urls( $content ) );
		$base_urls  = array_filter( array_map( array( $this, 'sanitize_url' ), $all_urls ) );
		$clean_urls = array_map( array( $this, 'clean_url' ), $base_urls );
		$urls       = array_filter( $clean_urls, array( $this, 'validate_url' ) );

		// De-size.
		$desized = array_unique( array_map( array( $this, 'maybe_unsize_url' ), $urls ) );
		$scaled  = array_unique( array_map( array( $this, 'make_scaled_url' ), $desized ) );
		$urls    = array_merge( $desized, $scaled );
		$urls    = array_values( $urls ); // resets the index.

		// clean out empty urls.
		$cloudinary_urls = array_filter( $base_urls, array( $this->media, 'is_cloudinary_url' ) ); // clean out empty urls.
		if ( empty( $urls ) && empty( $cloudinary_urls ) ) {
			return; // Bail since theres nothing.
		}
		// Clean URLS for search.
		$public_ids = array_filter( array_map( array( $this->media, 'get_public_id_from_url' ), $cloudinary_urls ) );

		$wheres = array();
		if ( ! empty( $urls ) ) {
			// Do the URLS.
			$list     = implode( ', ', array_fill( 0, count( $urls ), '%s' ) );
			$wheres[] = "sized_url IN( {$list} )";
		}
		if ( ! empty( $public_ids ) ) {
			// Do the public_ids.
			$list     = implode( ', ', array_fill( 0, count( $public_ids ), '%s' ) );
			$wheres[] = "public_id IN( {$list} )";
			$urls     = array_merge( $urls, $public_ids );
		}

		$tablename = Utils::get_relationship_table();
		$sql       = "SELECT * from {$tablename} WHERE " . implode( ' OR ', $wheres );
		$prepared  = $wpdb->prepare( $sql, $urls ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$cache_key = md5( $prepared );
		$results   = wp_cache_get( $cache_key, 'cld_delivery' );

		if ( empty( $results ) ) {
			$results = $wpdb->get_results( $prepared, ARRAY_A );// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery
			wp_cache_add( $cache_key, $results, 'cld_delivery' );
		}

		$auto_sync = $this->sync->is_auto_sync_enabled();
		foreach ( $results as $result ) {
			$this->set_usability( $result, $auto_sync );
		}
		// Set unknowns.
		$this->unknown = array_diff( $urls, array_keys( $this->known ) );
	}

	/**
	 * Catch attachment URLS from HTML content.
	 *
	 * @param string $content The HTML to catch URLS from.
	 */
	public function catch_urls( $content ) {

		$this->init_delivery();
		$this->get_urls( $content );
		$known = $this->convert_tags( $content );

		// Attempt to get the unknowns.
		if ( ! empty( $this->unknown ) ) {
			$this->find_attachment_size_urls();
		}

		// Replace the knowns.
		foreach ( $known as $src => $replace ) {
			String_Replace::replace( $src, $replace );
		}

	}
}
