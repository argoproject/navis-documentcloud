<?php
/***
 * Plugin Name: Navis DocumentCloud
 * Description: Embed DocumentCloud documents that won't be eaten by the visual editor
 * Version: 0.1
 * Author: Chris Amico
 * License: GPLv2
***/
/*
    Copyright 2011 National Public Radio, Inc. 

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

class Navis_DocumentCloud {
    
    function __construct() {
        // shortcode
        // mce plugins
        // mce buttons
        add_shortcode( 'documentcloud', array(&$this, 'embed_shortcode'));
        
        add_action( 'init', array(&$this, 'register_tinymce_filters'));
        
        add_action( 'save_post', array(&$this, 'save'));
        
        add_action( 'admin_menu', array(&$this, 'add_options_page'));
        
        add_action( 'admin_init', array(&$this, 'settings_init'));
    }
    
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
            'js/navis-documentcloud-editor-plugin.js', __FILE__);
        return $plugin_array;
    }
    
    function register_button($buttons) {
        array_push($buttons, '|', "documentcloud");
        return $buttons;
    }
    
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
        add_settings_section( 'documentcloud', '',
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
        $option = intval(get_option( 'documentcloud_default_height', 600 ));
        echo "<input type='text' value='$option' name='documentcloud_default_height' />";
    }
    
    function default_width_field() {
        $option = intval(get_option( 'documentcloud_default_width', 620 ));
        echo "<input type='text' value='$option' name='documentcloud_default_width' />";
    }
    
    function full_width_field() {
        $option = intval(get_option( 'documentcloud_full_width', 620 ));
        echo "<input type='text' value='$option' name='documentcloud_full_width' />";
    }
    
    function settings_section() {}
    
    function save($post_id) {
        // tell the post if we're carrying a wide load        
        
        $post = get_post($post_id);
        
        // avoid autosave
        if ( !in_array( $post->post_status, array(
            'publish', 'draft', 'private', 'future', 'pending'
            )) 
        ) { return; }
        
        $defaults = $this->get_defaults();
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

                // get a doc id to keep array keys consistent
                if ( isset($atts['url']) && !isset($atts['id']) ) {
                    $atts['id'] = $this->parse_id_from_url($atts['url']);
                }
                
                // if no id, don't bother storing because it's wrong
                if ($atts['id'] != null) {
                    if ( $atts['format'] == "wide" || $atts['width'] > $defaults['width']) {
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
    
    function embed_shortcode($atts, $content, $code) {        
        global $post;
        $defaults = $this->get_defaults();
        extract( shortcode_atts($defaults, $atts));
        
        // we need a document ID or URL, or it's a no op
        if ($url && !$id) {
            // parse id from url
            $id = $this->parse_id_from_url($url);
        }
        
        // still no id? nothin doing
        if (!$id) return;
        
        # we only deal with integers
        $height = intval($height);
        $width = intval($width);
        if ($format == "wide") {
            $width = get_option('documentcloud_full_width', 940);
        }
        
        $is_wide = $width > $defaults['width'];
        
        // full control in single templates
        if (is_single()) {
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
            </script>
            ";
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
            </script>            
            ";
        }
    }
}

new Navis_DocumentCloud;