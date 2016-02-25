<?php
/***
 * Plugin Name: DocumentCloud
 * Plugin URI: https://www.documentcloud.org/
 * Description: Embed DocumentCloud resources in WordPress content.
 * Version: 0.4.2
 * Authors: Chris Amico, Justin Reese
 * License: GPLv2
***/
/*
	Copyright 2011 National Public Radio, Inc.
	Copyright 2015 DocumentCloud, Investigative Reporters & Editors

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License, version 2, as
	published by the Free Software Foundation.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

class WP_DocumentCloud {

	// Plugin constants
	const CACHING_ENABLED		   = true,
		  DEFAULT_EMBED_FULL_WIDTH = 940,
		  OEMBED_RESOURCE_DOMAIN   = 'www.documentcloud.org',
		  OEMBED_PROVIDER		   = 'https://www.documentcloud.org/api/oembed.{format}',
		  DOCUMENT_PATTERN		   = '^(?P<protocol>https?):\/\/www\.documentcloud\.org\/documents\/(?P<document_slug>[0-9]+-[a-zA-Z0-9-]+)';
	/**
	 * Constructor.
	 */
	function __construct() {
		// Check for conflicts with other DocumentCloud plugins.
		// Not needed on WordPress VIP since no other DocumentCloud plugins exist.
		if ( ! defined( 'WPCOM_IS_VIP_ENV' ) || ! WPCOM_IS_VIP_ENV ) {
			add_action( 'admin_init', array( $this, 'check_dc_plugin_conflict') );
		}

		// Register the oEmbed provider
		add_action( 'init', array( $this, 'register_dc_oembed_provider' ) );

		// Set the textdomain for the plugin so it is translation compatible
		add_action( 'plugins_loaded', array( $this, 'load_plugin_textdomain' ) );

		// Only called when `[documentcloud]` shortcode is used
		add_shortcode( 'documentcloud', array( $this, 'process_dc_shortcode' ) );

		// Called just before oEmbed endpoint is hit
		add_filter( 'oembed_fetch_url', array( $this, 'prepare_oembed_fetch' ), 10, 3 );

		// Setup the settings page
		add_action( 'admin_menu', array( $this, 'add_options_page' ) );
		add_action( 'admin_init', array( $this, 'settings_init' ) );

		// Store DocumentCloud metadata upon post save
		add_action( 'save_post', array( $this, 'save' ), 10, 2 );
	}

	/**
	 * Load plugin textdomain.
	 */
	function load_plugin_textdomain() {
		load_plugin_textdomain( 'documentcloud' );
	}

	/**
	 * Check for conflicts with the Navis DocumentCloud plugin.
	 */
	function check_dc_plugin_conflict() {
		if ( is_plugin_active( 'navis-documentcloud/navis-documentcloud.php' ) ) {
			add_action( 'admin_notices', array( $this, 'dc_conflict_admin_notice' ) );
		}
	}

	/**
	 * Create an admin notice when conflicts exist with Navis DocumentCloud.
	 */
	function dc_conflict_admin_notice() {
		?>
		<div class="error">
			<p><?php echo wp_kses_post( __( '<b>Warning!</b> You have two conflicting DocumentCloud plugins activated. Please deactivate Navis DocumentCloud, which has been replaced by <a target="_blank" href="https://wordpress.org/plugins/documentcloud/">DocumentCloud</a>.', 'documentcloud' ) ); ?></p>
		</div>
		<?php
	}

	/**
	 * Register ourselves as an oEmbed provider. WordPress does NOT cURL the
	 * resource to inspect it for an oEmbed link tag; we have to tell it what
	 * our oEmbed endpoint looks like.
	 */
	function register_dc_oembed_provider() {
		/*
			Hello developer. If you wish to test this plugin against your
			local installation of DocumentCloud (with its own testing
			domain), set the OEMBED_PROVIDER and OEMBED_RESOURCE_DOMAIN
			constants above to your local testing domain. You'll also want
			to add the following line to your theme to let WordPress connect to local
			domains:

			add_filter( 'http_request_host_is_external', '__return_true');
		*/

		$oembed_resource_domain = apply_filters( 'documentcloud_oembed_resource_domain', WP_DocumentCloud::OEMBED_RESOURCE_DOMAIN );
		$oembed_provider = apply_filters( 'documentcloud_oembed_provider', WP_DocumentCloud::OEMBED_PROVIDER );

		wp_oembed_add_provider( 'http://'  . $oembed_resource_domain . '/documents/*', $oembed_provider );
		wp_oembed_add_provider( 'https://' . $oembed_resource_domain . '/documents/*', $oembed_provider );
	}

	/**
	 * Get the default sizes for DocumentCloud.
	 *
	 * @return array
	 */
	function get_default_sizes() {
		$wp_embed_defaults = wp_embed_defaults();

		$height		= intval( get_option( 'documentcloud_default_height', $wp_embed_defaults['height'] ) );
		$width		= intval( get_option( 'documentcloud_default_width', $wp_embed_defaults['width'] ) );
		$full_width	= intval( get_option( 'documentcloud_full_width', WP_DocumentCloud::DEFAULT_EMBED_FULL_WIDTH ) );

		return array (
			'height'		=> $height,
			'width'			=> $width,
			'full_width'	=> $full_width,
		);
	}

	/**
	 * Get the attribute defaults for the shortcode.
	 *
	 * @return array
	 */
	function get_default_atts() {
		$default_sizes = $this->get_default_sizes();

		return array(
			'url'				=> null,
			'container'			=> null,
			'notes'				=> null,
			'responsive_offset'	=> null,
			'default_page'		=> null,
			'default_note'		=> null,
			'zoom'				=> null,
			'search'			=> null,
			'responsive'		=> 'true',
			// The following defaults match the existing plugin, except
			// `height/width` are prefixed `max*` per the oEmbed spec.
			// You can still use `height/width` for backwards
			// compatibility, but they'll be mapped to `max*`.
			// Precedence (lower number == higher priority):
			//	1. `width` on shortcode
			//	2. `maxwidth` on shortcode
			//	3. Settings > DocumentCloud > "Default embed width"
			//	4. `wp_embed_defaults()['width']`
			'maxheight'			=> $default_sizes['height'],
			'maxwidth'			=> $default_sizes['width'],
			'format'			=> 'normal',
			'sidebar'			=> 'false',
			'text'				=> 'true',
			'pdf'				=> 'true',
		);
	}

	/**
	 * Prepare the oEmbed fetch URL.
	 *
	 * @param string $provider
	 * @param string $url
	 * @param array $args
	 * @return string
	 */
	function prepare_oembed_fetch( $provider, $url, $args ) {
        // Merge actual args with default attributes so that defaults are always
        // sent to oEmbed endpoint
        $default_atts = $this->get_default_atts();
        $atts         = array_merge( $default_atts, $args );

		// Clean and prepare arguments
		foreach ( $atts as $key => $value ) {
			switch ( $key ) {
				case 'format':
				case 'height':
				case 'width':
				case 'discover':
					// Don't pass these attributes to the provider
					break;
				default:
					// Without this check, `add_query_arg()` will treat values
					// that are actually ID selectors, like `container=#foo`,
					// as URL fragments and throw them at the end of the URL.
					if ( 0 === strpos( $value, '#' ) ) {
						$value = urlencode( $value );
					}
					$provider = add_query_arg( $key, $value, $provider );
					break;
			}
		}

		// Some resources (like notes) have multiple possible
		// user-facing URLs. We recompose them into a single form.
		$url = $this->clean_dc_url( $url );
		$provider = add_query_arg( 'url', urlencode( $url ), $provider );

		return $provider;
	}

	/**
	 * Create the DocumentCloud embed output from the shortcode.
	 *
	 * @param array $atts
	 * @return string
	 */
	function process_dc_shortcode( $atts ) {
		$default_sizes	= $this->get_default_sizes();
		$default_atts	= $this->get_default_atts();

		// Smooshes together passed-in shortcode attrs with defaults
		// and filters to only those we accept.
		$filtered_atts	= shortcode_atts( $default_atts, $atts );

		// Either the `url` or `id` attributes are required, but `id`
		// is only supported for backwards compatibility. If it's used,
		// we force this to embed a document. I.e., `id` can't be used
		// for embedding notes, pages, or other non-document resources.
		if ( empty( $atts['url'] ) ) {
			if ( empty( $atts['id'] ) ) {
				return '';
			}
			else {
				$url = $filtered_atts['url'] = "https://" . WP_DocumentCloud::OEMBED_RESOURCE_DOMAIN . "/documents/{$atts['id']}.html";
			}
		}

		// `height/width` beat `maxheight/maxwidth`; see full precedence list in `get_default_atts()`.
		if ( isset( $atts['height'] ) ) {
			$filtered_atts['maxheight'] = $atts['height'];
		}
		if ( isset( $atts['width'] ) ) {
			$filtered_atts['maxwidth'] = $atts['width'];
		}

		// `responsive` defaults true, but our responsive layout
		// ignores width declarations. If a user indicates a width and
		// hasn't otherwise specifically indicated `responsive='true'`,
		// it's safe to assume they expect us to respect the width, so
		// we disable the responsive flag.
		if ( ( isset( $atts['width'] ) || isset( $atts['maxwidth'] ) ) && 'true' !== $atts['responsive'] ) {
			$filtered_atts['responsive'] = 'false';
		}

		// If the format is set to wide, it blows away all other width
		// settings.
		if ( 'wide' == $filtered_atts['format'] ) {
			$filtered_atts['maxwidth'] = $default_sizes['full_width'];
		}

		// For the benefit of some templates, notify template that
		// we're requesting an asset wider than the default size.
		global $post;
		$is_wide = intval( $filtered_atts['maxwidth'] ) > $default_sizes['width'];

		if ( apply_filters( 'documentcloud_caching_enabled', WP_DocumentCloud::CACHING_ENABLED ) ) {
			// This lets WordPress cache the result of the oEmbed call.
			// Thanks to http://bit.ly/1HykA0U for this pattern.
			global $wp_embed;
			$url = $filtered_atts['url'] = $this->clean_dc_url( $atts['url'] );
			return $wp_embed->shortcode( $filtered_atts, $url );
		} else {
			return wp_oembed_get( $atts['url'], $filtered_atts );
		}

	}

	/**
	 * Parse the DocumentCloud URL into its components.
	 *
	 * @param string $url
	 * @return array
	 */
	function parse_dc_url( $url ) {
		$patterns = array(
			// Document
			'{' . WP_DocumentCloud::DOCUMENT_PATTERN . '\.html$}',
			// Pages and page variants
			'{' . WP_DocumentCloud::DOCUMENT_PATTERN . '.html#document\/p(?P<page_number>[0-9]+)$}',
			'{' . WP_DocumentCloud::DOCUMENT_PATTERN . '\/pages\/(?P<page_number>[0-9]+)\.(html|js)$}',
			// Notes and note variants
			'{' . WP_DocumentCloud::DOCUMENT_PATTERN . '\/annotations\/(?P<note_id>[0-9]+)\.(html|js)$}',
			'{' . WP_DocumentCloud::DOCUMENT_PATTERN . '.html#document\/p([0-9]+)/a(?P<note_id>[0-9]+)$}',
			'{' . WP_DocumentCloud::DOCUMENT_PATTERN . '.html#annotation\/a(?P<note_id>[0-9]+)$}',
		);

		$elements = array();
		foreach ( $patterns as $pattern ) {
			$perfect_match = preg_match( $pattern, $url, $elements );
			if ( $perfect_match ) {
				break;
			}
		}

		return $elements;
	}

	/**
	 * Clean the DocumentCloud URL.
	 *
	 * @param string $url
	 * @return string
	 */
	function clean_dc_url( $url ) {
		$elements = $this->parse_dc_url( $url );
		if ( isset( $elements['document_slug'] ) ) {
			$url = "{$elements['protocol']}://" . WP_DocumentCloud::OEMBED_RESOURCE_DOMAIN . "/documents/{$elements['document_slug']}";
			if ( isset( $elements['page_number'] ) ) {
				$url .= "/pages/{$elements['page_number']}";
			}
			else if ( isset( $elements['note_id'] ) ) {
				$url .= "/annotations/{$elements['note_id']}";
			}
			$url .= '.html';
		}
		return $url;
	}

	/**
	 * Add the DocumentCloud options page.
	 */
	function add_options_page() {
		if ( current_user_can( 'manage_options' ) ) {
			add_options_page( 'DocumentCloud', 'DocumentCloud', 'manage_options', 'documentcloud', array( $this, 'render_options_page' ) );
		}
	}

	/**
	 * Render the DocumentCloud options page.
	 */
	function render_options_page() {
		?>
		<h2><?php esc_html_e( 'DocumentCloud Options', 'documentcloud' ) ?></h2>
		<form action="options.php" method="post">

			<p><?php echo wp_kses_post( __( 'Any widths set here will only take effect if you set <code>responsive="false"</code> on an embed.', 'documentcloud' ) ) ?></p>

			<?php settings_fields( 'documentcloud' ); ?>
			<?php do_settings_sections( 'documentcloud' ); ?>

			<p><input class="button-primary" name="<?php esc_attr_e( 'Submit', 'documentcloud' ); ?>" type="submit" value="<?php esc_attr_e( 'Save Changes', 'documentcloud' ); ?>" /></p>
		</form>
		<?php
	}

	/**
	 * Initialize settings for the DocumentCloud options page.
	 */
	function settings_init() {
		if ( current_user_can( 'manage_options' ) ) {
			add_settings_section(
				'documentcloud',
				'',
				'__return_null',
				'documentcloud'
			);

			add_settings_field(
				'documentcloud_default_height',
				__( 'Default embed height (px)', 'documentcloud' ),
				array( $this, 'default_height_field' ),
				'documentcloud',
				'documentcloud'
			);
			register_setting( 'documentcloud', 'documentcloud_default_height' );

			add_settings_field(
				'documentcloud_default_width',
				__( 'Default embed width (px)', 'documentcloud' ),
				array( $this, 'default_width_field' ),
				'documentcloud',
				'documentcloud'
			);
			register_setting( 'documentcloud', 'documentcloud_default_width' );

			add_settings_field(
				'documentcloud_full_width',
				__( 'Full-width embed width (px)', 'documentcloud' ),
				array( $this, 'full_width_field' ),
				'documentcloud',
				'documentcloud'
			);
			register_setting( 'documentcloud', 'documentcloud_full_width' );
		}
	}

	/**
	 * Render the default height field.
	 */
	function default_height_field() {
		$default_sizes = $this->get_default_sizes();
		echo '<input type="text" value="' . esc_attr( $default_sizes['height'] ) . '" name="documentcloud_default_height" />';
	}

	/**
	 * Render the default width field.
	 */
	function default_width_field() {
		$default_sizes = $this->get_default_sizes();
		echo '<input type="text" value="' . esc_attr( $default_sizes['width'] ) . '" name="documentcloud_default_width" />';
	}

	/**
	 * Render the full width field.
	 */
	function full_width_field() {
		$default_sizes = $this->get_default_sizes();
		echo '<input type="text" value="' . esc_attr( $default_sizes['full_width'] ) . '" name="documentcloud_full_width" />';
	}

	/**
	 * Save DocumentCloud postmeta required by the shortcode on post save.
	 *
	 * @param int $post_id
	 * @param WP_Post $post
	 */
	function save( $post_id, $post ) {
		// Avoid autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Tell the post if we're carrying a wide load
		if ( current_user_can( 'edit_posts' ) ) {
			$default_sizes = $this->get_default_sizes();
			$default_atts = $this->get_default_atts();
			$wide_assets = get_post_meta( $post_id, 'wide_assets', true );
			$matches = array();

			preg_match_all( '/'.get_shortcode_regex().'/', $post->post_content, $matches );
			$tags = $matches[2];
			$args = $matches[3];
			foreach( $tags as $i => $tag ) {
				if ( 'documentcloud' == $tag ) {
					$parsed_atts = shortcode_parse_atts( $args[$i] );
					$atts = shortcode_atts( $default_atts, $parsed_atts );

					// Get a doc id to keep array keys consistent
					if ( isset( $atts['url'] ) ) {
						$elements = $this->parse_dc_url( $atts['url'] );
						if ( isset( $elements['document_slug'] ) ) {
							$meta_key = $elements['document_slug'];
							if ( isset( $elements['page_number'] ) ) {
								$meta_key .= "-p{$elements['page_number']}";
							}
							else if ( isset( $elements['note_id'] ) ) {
								$meta_key .= "-a{$elements['note_id']}";
							}
						}
					} else if ( isset( $atts['id'] ) ) {
						$meta_key = $atts['id'];
					}

					// If no id, don't bother storing because it's wrong
					if ( isset( $meta_key ) ) {
						$width = intval( isset( $parsed_atts['width'] ) ? $parsed_atts['width'] : $atts['maxwidth'] );
						if ( 'wide' == $atts['format'] || $width > $default_sizes['width'] ) {
							$wide_assets[ $meta_key ] = true;
						} else {
							$wide_assets[ $meta_key ] = false;
						}
					}
				}
			}
			update_post_meta( $post_id, 'wide_assets', $wide_assets );
		}
	}

}

new WP_DocumentCloud;
