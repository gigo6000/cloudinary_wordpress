<?php
/**
 * Defines the settings structure for video.
 *
 * @package Cloudinary
 */

$settings = array(
	array(
		'type'        => 'panel',
		'title'       => __( 'Video - Global Settings', 'cloudinary' ),
		'anchor'      => true,
		'option_name' => 'media_display',
		array(
			'type' => 'tabs',
			'tabs' => array(
				'image_setting' => array(
					'text' => __( 'Settings', 'cloudinary' ),
					'id'   => 'settings',
				),
				'image_preview' => array(
					'text' => __( 'Preview', 'cloudinary' ),
					'id'   => 'preview',
				),
			),
		),
		array(
			'type' => 'row',
			array(
				'type'   => 'column',
				'tab_id' => 'settings',
				array(
					'type'         => 'select',
					'slug'         => 'video_player',
					'title'        => __( 'Video player', 'cloudinary' ),
					'tooltip_text' => __( 'Which video player to use on all videos.', 'cloudinary' ),
					'default'      => 'wp',
					'options'      => array(
						'wp'  => __( 'WordPress player', 'cloudinary' ),
						'cld' => __( 'Cloudinary player', 'cloudinary' ),
					),
				),
				array(
					'type'      => 'group',
					'condition' => array(
						'video_player' => 'cld',
					),
					array(
						'slug'        => 'video_controls',
						'description' => __( 'Show controls', 'cloudinary' ),
						'type'        => 'on_off',
						'default'     => 'on',
					),
					array(
						'slug'        => 'video_loop',
						'description' => __( 'Repeat video', 'cloudinary' ),
						'type'        => 'on_off',
						'default'     => 'off',
					),
					array(
						'slug'         => 'video_autoplay_mode',
						'title'        => __( 'Autoplay', 'cloudinary' ),
						'type'         => 'radio',
						'default'      => 'off',
						'options'      => array(
							'off'       => __( 'Off', 'cloudinary' ),
							'always'    => __( 'Always', 'cloudinary' ),
							'on-scroll' => __( 'On-scroll (autoplay when in view)', 'cloudinary' ),
						),
						'tooltip_text' => sprintf(
						// translators: Placeholders are <a> tags.
							__(
								'Please note that when choosing "always", the video will autoplay without sound (muted). This is a built-in browser feature and applies to all major browsers.%1$sRead more about muted autoplay%2$s',
								'cloudinary'
							),
							'<br><a href="https://developers.google.com/web/updates/2016/07/autoplay" target="_blank">',
							'</a>'
						),
					),
				),
				array(
					'type'    => 'tag',
					'element' => 'hr',
				),
				array(
					'type' => 'group',
					array(
						'type'         => 'on_off',
						'slug'         => 'video_optimization',
						'title'        => __( 'Video optimization', 'cloudinary' ),
						'tooltip_text' => __(
							'Videos will be delivered using Cloudinary’s automatic format and quality algorithms for the best tradeoff between visual quality and file size. Use Advanced Optimization options to manually tune format and quality.',
							'cloudinary'
						),
						'description'  => __( 'Optimize videos on my site.', 'cloudinary' ),
						'default'      => 'on',
						'attributes'   => array(
							'data-context' => 'video',
						),
					),
				),
				array(
					'type'      => 'group',
					'condition' => array(
						'video_optimization' => true,
					),
					array(
						'type'         => 'select',
						'slug'         => 'video_format',
						'title'        => __( 'Video format', 'cloudinary' ),
						'tooltip_text' => __(
							"The video format to use for delivery. Leave as Auto to automatically deliver the most optimal format based on the user's browser and device.",
							'cloudinary'
						),
						'default'      => 'auto',
						'options'      => array(
							'none' => __( 'Not set', 'cloudinary' ),
							'auto' => __( 'Auto', 'cloudinary' ),
						),
						'suffix'       => 'f_auto',
						'attributes'   => array(
							'data-context' => 'video',
							'data-meta'    => 'f',
						),
					),
					array(
						'type'         => 'select',
						'slug'         => 'video_quality',
						'title'        => __( 'Video quality', 'cloudinary' ),
						'tooltip_text' => __(
							'The compression quality to apply when delivering videos. Leave as Auto to apply an algorithm that finds the best tradeoff between visual quality and file size.',
							'cloudinary'
						),
						'default'      => 'auto',
						'options'      => array(
							'none'      => __( 'Not set', 'cloudinary' ),
							'auto'      => __( 'Auto', 'cloudinary' ),
							'auto:best' => __( 'Auto best', 'cloudinary' ),
							'auto:good' => __( 'Auto good', 'cloudinary' ),
							'auto:eco'  => __( 'Auto eco', 'cloudinary' ),
							'auto:low'  => __( 'Auto low', 'cloudinary' ),
							'100'       => '100',
							'80'        => '80',
							'60'        => '60',
							'40'        => '40',
							'20'        => '20',
						),
						'suffix'       => 'q_auto',
						'attributes'   => array(
							'data-context' => 'video',
							'data-meta'    => 'q',
						),
					),

				),
				array(
					'type'    => 'tag',
					'element' => 'hr',
				),
				array(
					'type'           => 'text',
					'slug'           => 'video_freeform',
					'title'          => __( 'Additional video transformations', 'cloudinary' ),
					'tooltip_text'   => sprintf(
						// translators: The link to transformation reference.
						__(
							'A set of additional transformations to apply to all videos. Specify your transformations using Cloudinary URL transformation syntax. See %1$sreference%2$s for all available transformations and syntax.',
							'cloudinary'
						),
						'<a href="https://cloudinary.com/documentation/transformation_reference" target="_blank" rel="noopener noreferrer">',
						'</a>'
					),
					'link'           => array(
						'text' => __( 'See examples', 'cloudinary' ),
						'href' => 'https://cloudinary.com/documentation/transformation_reference',
					),
					'attributes'     => array(
						'data-context' => 'video',
						'placeholder'  => 'fps_15-25,ac_none',
					),
					'taxonomy_field' => array(
						'context'  => 'video',
						'priority' => 10,
					),
				),
				array(
					'type'  => 'info_box',
					'icon'  => $this->dir_url . 'css/images/video.svg',
					'title' => __( 'What are transformations?', 'cloudinary' ),
					'text'  => __(
						'A set of parameters included in a Cloudinary URL to programmatically transform the visual appearance of the assets on your website.',
						'cloudinary'
					),
				),
			),
			array(
				'type'   => 'column',
				'tab_id' => 'preview',
				array(
					'type'           => 'video_preview',
					'title'          => __( 'Video preview', 'cloudinary' ),
					'slug'           => 'video_preview',
					'taxonomy_field' => array(
						'context'  => 'video',
						'priority' => 10,
					),
				),
			),

		),
	),
);

return apply_filters( 'cloudinary_admin_video_settings', $settings );
