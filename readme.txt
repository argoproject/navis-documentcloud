=== Navis DocumentCloud ===
Contributors: chrisamico
Tags: documentcloud, documents
Requires at least: 3.2
Tested up to: 3.3.2
Stable tag: trunk

Embed DocumentCloud documents that won't be eaten by the visual editor.

== Description ==

[DocumentCloud](http://www.documentcloud.org/home) is a free service allowing journalists to analyze, annotate and publish documents, funded by the Knight Foundation. Initial development supported by [NPR](http://www.npr.org) as part of [StateImpact](http://stateimpact.npr.org) project.

DocumentCloud's normal embed code looks like this:

    <div id="DV-viewer-265231-11-07-2011-letter-to-idaho-congressional" class="DV-container"></div>
    <script src="http://s3.documentcloud.org/viewer/loader.js"></script>
    <script>
     DV.load('http://www.documentcloud.org/documents/265231-11-07-2011-letter-to-idaho-congressional.js', {
        width: 600,
        height: 450,
        sidebar: false,
        container: "#DV-viewer-265231-11-07-2011-letter-to-idaho-congressional"
      });
    </script>
    
That works great as long as you edit in HTML mode. Switch to the visual editor, and your container `div` disappears and your JavaScript is broken.

To get around this, use this short code:

    [documentcloud id="265231-11-07-2011-letter-to-idaho-congressional"]
    
Or use the URL from DocumentCloud:

    [documentcloud url="http://www.documentcloud.org/documents/265231-11-07-2011-letter-to-idaho-congressional.html"]



== Installation ==

1. Upload the navis-documentcloud directory to `wp-content/plugins/navis-documentcloud`
2. Activate the plugin
3. In your posts, add documents using the DocumentCloud button, or the `[documentcloud]` shortcode.

