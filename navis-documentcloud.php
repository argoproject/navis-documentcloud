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
            'sidebar' => 'false',
            'text' => 'true',
            'pdf' => 'true'
        );
    }
    
    function save($post_id) {
        # tell the post if we're carrying a wide load
        $wide_assets = get_post_meta($post_id, 'wide_assets', true);
        if ($width > $defaults['width']) {
            $wide_assets[$id] = true;
        } else {
            $wide_assets[$id] = false;
        }
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
    }
}

new Navis_DocumentCloud;