<?php
/***
 * Plugin Name: DocumentCloud
 * Plugin URI: https://www.documentcloud.org/
 * Description: Embed DocumentCloud resources in WordPress content.
 * Version: 0.2
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

    const CACHING_ENABLED          = false,
          DEFAULT_EMBED_HEIGHT     = 620,
          DEFAULT_EMBED_WIDTH      = 600,
          DEFAULT_EMBED_FULL_WIDTH = 940,
          OEMBED_RESOURCE_DOMAIN   = 'www.documentcloud.org',
          OEMBED_PROVIDER          = 'http://www.documentcloud.org/api/oembed.{format}',
          DOCUMENT_PATTERN         = '^(?P<protocol>https?)://www\.documentcloud\.org/documents/(?P<document_id>[0-9]+-[a-z0-9-]+)';
    
    function __construct() {

        add_action('init', array(&$this, 'register_dc_oembed_provider'));
        add_shortcode('documentcloud', array(&$this, 'handle_dc_shortcode'));
        add_filter('oembed_fetch_url', array(&$this, 'add_dc_arguments'), 10, 3);

        // Setup TinyMCE shortcode-generation plugin
        add_action('init', array(&$this, 'register_tinymce_filters'));

        // Setup admin settings
        add_action('admin_menu', array(&$this, 'add_options_page'));
        add_action('admin_init', array(&$this, 'settings_init'));

        // Register [documentcloud] shortcode using old embed method
        // add_shortcode('documentcloud', array(&$this, 'embed_shortcode'));
        
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

    function get_default_atts() {
        // TODO: Add admin options to adjust all defaults.
        return array(
            'url'               => null,
            'container'         => null,
            'notes'             => null,
            'responsive_offset' => null,
            'default_page'      => null,
            'default_note'      => null,
            'zoom'              => null,
            'search'            => null,
            'responsive'        => null,
            // The following defaults match the existing plugin, except 
            // `height/width` are prefixed `max*` per the oEmbed spec.
            // You can still use `height/width` for backwards
            // compatibility, but they'll be mapped to `max*`.
            // Precedence (lower number == higher priority):
            //  1. `width` on shortcode
            //  2. `maxwidth` from shortcode
            //  3. Settings > DocumentCloud > "Default embed width"
            //  4. `WP_DocumentCloud::DEFAULT_EMBED_WIDTH`
            'maxheight'         => intval(get_option('documentcloud_default_height', WP_DocumentCloud::DEFAULT_EMBED_HEIGHT)),
            'maxwidth'          => intval(get_option('documentcloud_default_width',  WP_DocumentCloud::DEFAULT_EMBED_WIDTH)),
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
        $default_atts  = $this->get_default_atts();
        // Smooshes together passed-in shortcode attributes with 
        // default attributes/values.
        $filtered_atts = shortcode_atts($default_atts, $atts);

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
            $url = $filtered_atts['url'] = $this->clean_url($atts['url']);
        }

        // `height/width` beat `maxheight/maxwidth`; see full precedence list in `get_default_atts().
        if (isset($atts['height'])) {
            $filtered_atts['maxheight'] = $atts['height'];
        }
        if (isset($atts['width'])) {
            $filtered_atts['maxwidth'] = $atts['width'];
        }

        // If the format is set to wide, it blows away all other width 
        // settings.
        if ($filtered_atts['format'] == 'wide') {
            $filtered_atts['maxwidth'] = get_option('documentcloud_full_width', DEFAULT_EMBED_FULL_WIDTH);
        }
        
        // For the benefit of some templates
        global $post;
        $is_wide = intval($filtered_atts['maxwidth']) > $default_atts['maxwidth'];

        if (WP_DocumentCloud::CACHING_ENABLED) {
            // This lets WordPress cache the result of the oEmbed call.
            // Thanks to http://bit.ly/1HykA0U for this pattern.
            global $wp_embed;
            return $wp_embed->shortcode($filtered_atts, $url);
        } else {
            return wp_oembed_get($url, $filtered_atts);
        }

    }

    function clean_url($url) {
        $patterns = array(
            // Document
            '{' . WP_DocumentCloud::DOCUMENT_PATTERN . '\.html$}',
            // Notes and note variants
            '{' . WP_DocumentCloud::DOCUMENT_PATTERN . '/annotations/(?P<note_id>[0-9]+)\.(html|js)$}',
            '{' . WP_DocumentCloud::DOCUMENT_PATTERN . '.html#document/p([0-9]+)/a(?P<note_id>[0-9]+)$}',
            '{' . WP_DocumentCloud::DOCUMENT_PATTERN . '.html#annotations/a(?P<note_id>[0-9]+)$}',
        );

        $elements = array();
        foreach ($patterns as $pattern) {
            $perfect_match = preg_match($pattern, $url, $elements);
            if ($perfect_match) {
                return "{$elements['protocol']}://" . WP_DocumentCloud::OEMBED_RESOURCE_DOMAIN . "/documents/{$elements['document_id']}" .
                        ($elements['note_id'] ? "/annotations/{$elements['note_id']}" : '') . '.html';
            }
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
        $option = intval(get_option('documentcloud_default_height', 600));
        echo "<input type='text' value='$option' name='documentcloud_default_height' />";
    }
    
    function default_width_field() {
        $option = intval(get_option('documentcloud_default_width', 620));
        echo "<input type='text' value='$option' name='documentcloud_default_width' />";
    }
    
    function full_width_field() {
        $option = intval(get_option('documentcloud_full_width', 620));
        echo "<input type='text' value='$option' name='documentcloud_full_width' />";
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
        
        $defaults = $this->get_default_atts();
        $wide_assets = get_post_meta($post_id, 'wide_assets', true);
        $documents = get_post_meta($post_id, 'documentcloud', true);
        $matches = array();
                
        preg_match_all('/'.get_shortcode_regex().'/', $post->post_content, $matches);
        $tags = $matches[2];
        $args = $matches[3];
        foreach($tags as $i => $tag) {
            if ($tag == "documentcloud") {
                $atts = shortcode_parse_atts($args[$i]);
                $atts = shortcode_atts($defaults, $atts);

                // TODO: Reconsider using document ID as array key,
                // because we'll be using this same shortcode to
                // consume more than just documents in the future.
                // Perhaps we can use `url` as key?

                // get a doc id to keep array keys consistent
                if (isset($atts['url']) && !isset($atts['id']) ) {
                    $atts['id'] = $this->parse_id_from_url($atts['url']);
                }
                
                // if no id, don't bother storing because it's wrong
                if ($atts['id'] != null) {
                    $width = isset($atts['width']) ? $atts['width'] : $atts['maxwidth'];
                    if ($atts['format'] == "wide" || $width > $defaults['maxwidth']) {
                        $wide_assets[$atts['id']] = true;
                    } else {
                        $wide_assets[$atts['id']] = false;
                    }
                
                    $documents[$atts['id']] = $atts;
                    
                }
            }
        }
        update_post_meta($post_id, 'documents', $documents);
        update_post_meta($post_id, 'wide_assets', $wide_assets);
    }
    
    function parse_id_from_url($url) {
        $regex = '{^https://www\.documentcloud\.org/documents/(?P<id>.+)\.html}';
        $matches = array();
        if (preg_match($regex, $url, $matches)) {
            return $matches['id'];
        } else {
            return null;
        }
    }
    
    // TODO: Remove this once we've ensured `handle_dc_shortcode()` 
    // replicates all the functionality properly. Note that `save()` 
    // has been adjusted to use `get_default_atts()`, but I left 
    // `embed_shortcode()` using `get_defaults()` for inspection.

    function get_defaults() {
        // add admin options to adjust these defaults
        // storing js params as strings instead of real booleans
        return array(
            'url' => null,
            'id' => null,
            'height' => get_option('documentcloud_default_height', 600),
            'width' => get_option('documentcloud_default_width', 620),
            'format' => 'normal',
            'sidebar' => 'false',
            'text' => 'true',
            'pdf' => 'true'
        );
    }

    function embed_shortcode($atts, $content, $code) {        
        global $post;
        $defaults = $this->get_defaults();
        extract(shortcode_atts($defaults, $atts));
        
        // we need a document ID or URL, or it's a no op
        if ($url && !$id) {
            // parse id from url
            $id = $this->parse_id_from_url($url);
        }
        
        // still no id? nothin doing
        if (!$id) return;
        
        // we only deal with integers
        $height = intval($height);
        $width = intval($width);
        if ($format == 'wide') {
            $width = get_option('documentcloud_full_width', 940);
        }
        
        $is_wide = $width > $defaults['width'];
        
        // full control in single templates
        if (is_single() || is_page()) {
            return "
            <div id='DV-viewer-$id' class='DV-container'></div>
            <script src='http://s3.documentcloud.org/viewer/loader.js'></script>
            <script>
              DV.load('http://www.documentcloud.org/documents/$id.js', {
                width: $width,
                height: $height,
                sidebar: $sidebar,
                text: $text,
                pdf: $pdf,
                container: '#DV-viewer-$id'
              });
            </script>";

        } else {
            // index view is always normal width, no sidebar
            return "
            <div id='DV-viewer-$id' class='DV-container'></div>
            <script src='http://s3.documentcloud.org/viewer/loader.js'></script>
            <script>
              DV.load('http://www.documentcloud.org/documents/$id.js', {
                width: {$defaults['width']},
                height: $height,
                sidebar: {$defaults['sidebar']},
                text: $text,
                pdf: $pdf,
                container: '#DV-viewer-$id'
              });
            </script>";
        }
    }
}

new WP_DocumentCloud;
