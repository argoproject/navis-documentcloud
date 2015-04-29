<?php
/***
 * Plugin Name: DocumentCloud
 * Plugin URI: https://www.documentcloud.org/
 * Description: Embed DocumentCloud resources in WordPress content.
 * Version: 0.3
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

    const CACHING_ENABLED          = true,
          DEFAULT_EMBED_FULL_WIDTH = 940,
          OEMBED_RESOURCE_DOMAIN   = 'www.documentcloud.org',
          OEMBED_PROVIDER          = 'https://www.documentcloud.org/api/oembed.{format}',
          DOCUMENT_PATTERN         = '^(?P<protocol>https?)://www\.documentcloud\.org/documents/(?P<document_slug>[0-9]+-[a-z0-9-]+)';
    
    function __construct() {

        add_action('init', array(&$this, 'register_dc_oembed_provider'));
        add_shortcode('documentcloud', array(&$this, 'handle_dc_shortcode'));
        add_filter('oembed_fetch_url', array(&$this, 'add_dc_arguments'), 10, 3);

        // Setup TinyMCE shortcode-generation plugin
        // add_action('init', array(&$this, 'register_tinymce_filters'));

        // Setup admin settings
        add_action('admin_menu', array(&$this, 'add_options_page'));
        add_action('admin_init', array(&$this, 'settings_init'));

        // Store metadata upon post save
        add_action('save_post', array(&$this, 'save'));
    }
    
    function register_dc_oembed_provider() {
    /*
        Hello developer. If you wish to test this plugin against your
        local installation of DocumentCloud (with its own testing
        domain), set the OEMBED_PROVIDER and OEMBED_RESOURCE_DOMAIN
        constants above to your local testing domain. You'll also want
        to uncomment the next line to let WordPress connect to local
        domains.
    */
        // add_filter( 'http_request_host_is_external', '__return_true');

        wp_oembed_add_provider("http://"  . WP_DocumentCloud::OEMBED_RESOURCE_DOMAIN . "/documents/*",  WP_DocumentCloud::OEMBED_PROVIDER);
        wp_oembed_add_provider("https://" . WP_DocumentCloud::OEMBED_RESOURCE_DOMAIN . "/documents/*",  WP_DocumentCloud::OEMBED_PROVIDER);
    }

    function get_default_sizes() {
        $wp_embed_defaults = wp_embed_defaults();

        $height     = intval(get_option('documentcloud_default_height', $wp_embed_defaults['height']));
        $width      = intval(get_option('documentcloud_default_width', $wp_embed_defaults['width']));
        $full_width = intval(get_option('documentcloud_full_width', WP_DocumentCloud::DEFAULT_EMBED_FULL_WIDTH));

        return array (
            'height'     => $height,
            'width'      => $width,
            'full_width' => $full_width,
        );
    }

    // TODO: Add admin options to adjust all defaults.
    function get_default_atts() {
        $default_sizes = $this->get_default_sizes();

        return array(
            'url'               => null,
            'container'         => null,
            'notes'             => null,
            'responsive_offset' => null,
            'default_page'      => null,
            'default_note'      => null,
            'zoom'              => null,
            'search'            => null,
            'responsive'        => 'true',
            // The following defaults match the existing plugin, except 
            // `height/width` are prefixed `max*` per the oEmbed spec.
            // You can still use `height/width` for backwards
            // compatibility, but they'll be mapped to `max*`.
            // Precedence (lower number == higher priority):
            //  1. `width` on shortcode
            //  2. `maxwidth` on shortcode
            //  3. Settings > DocumentCloud > "Default embed width"
            //  4. `wp_embed_defaults()['width']`
            'maxheight'         => $default_sizes['height'],
            'maxwidth'          => $default_sizes['width'],
            'format'            => 'normal',
            'sidebar'           => 'false',
            'text'              => 'true',
            'pdf'               => 'true',
        );
    }

    function add_dc_arguments($provider, $url, $args) {
        foreach ($args as $key => $value) {
            switch ($key) {
                case 'format':
                case 'height':
                case 'width':
                case 'discover':
                    // Don't pass these attributes to the provider
                    break;
                default:
                    $provider = add_query_arg( $key, $value, $provider );
                    break;
            }
        }
    	return $provider;
    }

    function handle_dc_shortcode($atts) {
        $default_sizes  = $this->get_default_sizes();
        $default_atts   = $this->get_default_atts();

        // Smooshes together passed-in shortcode attrs with defaults
        // and filters to only those we accept.
        $filtered_atts  = shortcode_atts($default_atts, $atts);

        // Either the `url` or `id` attributes are required, but `id` 
        // is only supported for backwards compatibility. If it's used,
        // we force this to embed a document. I.e., `id` can't be used 
        // for embedding notes, pages, or other non-document resources.
        if (!$atts['url']) {
            if (!$atts['id']) {
                return '';
            }
            else {
                $url = $filtered_atts['url'] = "https://" . WP_DocumentCloud::OEMBED_RESOURCE_DOMAIN . "/documents/{$atts['id']}.html";
            }
        } else {
            // Some resources (like notes) have multiple possible
            // user-facing URLs. We recompose them into a single form.
            $url = $filtered_atts['url'] = $this->clean_dc_url($atts['url']);
        }

        // `height/width` beat `maxheight/maxwidth`; see full precedence list in `get_default_atts()`.
        if (isset($atts['height'])) {
            $filtered_atts['maxheight'] = $atts['height'];
        }
        if (isset($atts['width'])) {
            $filtered_atts['maxwidth'] = $atts['width'];
        }
        
        // `responsive` defaults true, but our responsive layout 
        // ignores width declarations. If a user indicates a width and 
        // hasn't otherwise specifically indicated `responsive='true'`, 
        // it's safe to assume they expect us to respect the width, so 
        // we disable the responsive flag.
        if ((isset($atts['width']) || isset($atts['maxwidth'])) && (string) $atts['responsive'] != 'true') {
            $filtered_atts['responsive'] = 'false';
        }

        // If the format is set to wide, it blows away all other width 
        // settings.
        if ($filtered_atts['format'] == 'wide') {
            $filtered_atts['maxwidth'] = $default_sizes['full_width'];
        }
        
        // For the benefit of some templates, notify template that 
        // we're requesting an asset wider than the default size.
        global $post;
        $is_wide = intval($filtered_atts['maxwidth']) > $default_sizes['width'];

        if (WP_DocumentCloud::CACHING_ENABLED) {
            // This lets WordPress cache the result of the oEmbed call.
            // Thanks to http://bit.ly/1HykA0U for this pattern.
            global $wp_embed;
            return $wp_embed->shortcode($filtered_atts, $url);
        } else {
            return wp_oembed_get($url, $filtered_atts);
        }

    }

    function parse_dc_url($url) {
        $patterns = array(
            // Document
            '{' . WP_DocumentCloud::DOCUMENT_PATTERN . '\.html$}',
            // Notes and note variants
            '{' . WP_DocumentCloud::DOCUMENT_PATTERN . '/annotations/(?P<note_id>[0-9]+)\.(html|js)$}',
            '{' . WP_DocumentCloud::DOCUMENT_PATTERN . '.html#document/p([0-9]+)/a(?P<note_id>[0-9]+)$}',
            '{' . WP_DocumentCloud::DOCUMENT_PATTERN . '.html#annotation/a(?P<note_id>[0-9]+)$}',
        );

        $elements = array();
        foreach ($patterns as $pattern) {
            $perfect_match = preg_match($pattern, $url, $elements);
            if ($perfect_match) {
                break;
            }
        }
        return $elements;
    }

    function clean_dc_url($url) {
        $elements = $this->parse_dc_url($url);
        if ($elements['document_slug']) {
            $url = "{$elements['protocol']}://" . WP_DocumentCloud::OEMBED_RESOURCE_DOMAIN . "/documents/{$elements['document_slug']}" .
                   ($elements['note_id'] ? "/annotations/{$elements['note_id']}" : '') . '.html';
        }
        return $url;
    }

    // Setup TinyMCE shortcode button

    function register_tinymce_filters() {
        add_filter('mce_external_plugins', 
            array(&$this, 'add_tinymce_plugin')
        );

        add_filter('mce_buttons', 
            array(&$this, 'register_button')
        );
        
    }
        
    function add_tinymce_plugin($plugin_array) {
        $plugin_array['documentcloud'] = plugins_url(
            'js/documentcloud-editor-plugin.js', __FILE__);
        return $plugin_array;
    }
    
    function register_button($buttons) {
        array_push($buttons, '|', 'documentcloud');
        return $buttons;
    }
    
    // Setup settings for plugin

    function add_options_page() {
        add_options_page('DocumentCloud', 'DocumentCloud', 'manage_options', 
                        'documentcloud', array(&$this, 'render_options_page'));
    }
    
    function render_options_page() { ?>
        <h2>DocumentCloud Options</h2>
        <form action="options.php" method="post">

            <p>Any widths set here will only take effect if you set <code>responsive="false"</code> on an embed.</p>
            
            <?php settings_fields('documentcloud'); ?>
            <?php do_settings_sections('documentcloud'); ?>
            
            <p><input class="button-primary" name="Submit" type="submit" value="<?php esc_attr_e('Save Changes'); ?>" /></p>
        </form>
        <?php
    }
    
    function settings_init() {
        add_settings_section('documentcloud', '',
            array(&$this, 'settings_section'), 'documentcloud');
        
        add_settings_field('documentcloud_default_height', 'Default embed height (px)',
            array(&$this, 'default_height_field'), 'documentcloud', 'documentcloud');
        register_setting('documentcloud', 'documentcloud_default_height');
        
        add_settings_field('documentcloud_default_width', 'Default embed width (px)',
            array(&$this, 'default_width_field'), 'documentcloud', 'documentcloud');
        register_setting('documentcloud', 'documentcloud_default_width');
        
        add_settings_field('documentcloud_full_width', 'Full-width embed width (px)',
            array(&$this, 'full_width_field'), 'documentcloud', 'documentcloud');
        register_setting('documentcloud', 'documentcloud_full_width');
        
    }
    
    function default_height_field() {
        $default_sizes = $this->get_default_sizes();
        echo "<input type='text' value='{$default_sizes['height']}' name='documentcloud_default_height' />";
    }
    
    function default_width_field() {
        $default_sizes = $this->get_default_sizes();
        echo "<input type='text' value='{$default_sizes['width']}' name='documentcloud_default_width' />";
    }
    
    function full_width_field() {
        $default_sizes = $this->get_default_sizes();
        echo "<input type='text' value='{$default_sizes['full_width']}' name='documentcloud_full_width' />";
    }
    
    function settings_section() {}
    
    function save($post_id) {
        // tell the post if we're carrying a wide load        
        
        $post = get_post($post_id);
        
        // avoid autosave
        if (!in_array($post->post_status, array(
            'publish', 'draft', 'private', 'future', 'pending'
            )) 
        ) { return; }
        
        $default_sizes = $this->get_default_sizes();
        $default_atts = $this->get_default_atts();
        $wide_assets = get_post_meta($post_id, 'wide_assets', true);
        $documents = get_post_meta($post_id, 'documentcloud', true);
        $matches = array();
                
        preg_match_all('/'.get_shortcode_regex().'/', $post->post_content, $matches);
        $tags = $matches[2];
        $args = $matches[3];
        foreach($tags as $i => $tag) {
            if ($tag == "documentcloud") {
                $parsed_atts = shortcode_parse_atts($args[$i]);
                $atts = shortcode_atts($default_atts, $parsed_atts);

                // get a doc id to keep array keys consistent
                if (isset($atts['url'])) {
                    $elements = $this->parse_dc_url($atts['url']);
                    $meta_key = $elements['document_slug'];
                    if ($elements['note_id']) {
                        $meta_key .= "-{$elements['note_id']}";
                    }
                } else if (isset($atts['id'])) {
                    $meta_key = $atts['id'];
                }
                
                // if no id, don't bother storing because it's wrong
                if ($meta_key) {
                    $width = intval(isset($parsed_atts['width']) ? $parsed_atts['width'] : $atts['maxwidth']);
                    if ($atts['format'] == "wide" || $width > $default_sizes['width']) {
                        $wide_assets[$meta_key] = true;
                    } else {
                        $wide_assets[$meta_key] = false;
                    }
                    $documents[$meta_key] = $atts;
                }
            }
        }
        update_post_meta($post_id, 'documents', $documents);
        update_post_meta($post_id, 'wide_assets', $wide_assets);
    }
    
}

new WP_DocumentCloud;
