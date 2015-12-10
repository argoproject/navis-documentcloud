# DocumentCloud WordPress plugin

The DocumentCloud WordPress plugin lets you embed [DocumentCloud](https://www.documentcloud.org/) resources into WordPress content using [shortcodes](https://codex.wordpress.org/Shortcode_API).

    [documentcloud url="https://www.documentcloud.org/documents/282753-lefler-thesis.html"]

## Installation

1. Upload the contents of the plugin to `wp-content/plugins/documentcloud`
2. Activate the plugin through the "Plugins" menu
3. In your posts, embed documents, pages, or notes using the DocumentCloud button or the `[documentcloud]` shortcode
4. Optional: Set a default width/height for all DocumentCloud embeds (which can be overridden on a per-embed basis with the `height/width` attributes) at Settings > DocumentCloud. (This default width will only be used if you set `responsive="false"` on an embed.)

**Upgrading from Navis DocumentCloud:** If you're currently using the Navis DocumentCloud plugin (from which this plugin was built), you'll want to deactivate or delete it before installing this plugin.

## Usage

This plugin allows you to embed DocumentCloud resources using either the raw URL on its own line:

    Here's something you should really take a look at:
    
    https://www.documentcloud.org/documents/282753-lefler-thesis.html
    
    Isn't that interesting?

Or a custom shortcode:

    [documentcloud url="https://www.documentcloud.org/documents/282753-lefler-thesis.html"]

When you save, WordPress fetches and stores the actual embed code HTML from the DocumentCloud servers using oEmbed. You can freely toggle between visual and HTML mode without mangling embed code, and your embed will always be up to date with the latest embed code.

By default, documents will have a responsive width (it will narrow and widen as necessary to fill available content area) and use the theme's default height. If you want to override this, you can either set `responsive="false"` or explicitly set a `width`:

    [documentcloud url="https://www.documentcloud.org/documents/282753-lefler-thesis.html" width="600"]

You can set your own defaults in Settings > DocumentCloud, but default widths will be ignored unless `responsive` is disabled:

    [documentcloud url="https://www.documentcloud.org/documents/282753-lefler-thesis.html" responsive="false"]

To embed a single page, use any page-specific URL. Pages ignore `width/height` and always act responsively:

    [documentcloud url="https://www.documentcloud.org/documents/282753-lefler-thesis.html#document/p1"]

To embed a note, use any note-specific URL. Notes ignore `width/height` and always act responsively:

    [documentcloud url="https://www.documentcloud.org/documents/282753-lefler-thesis.html#document/p1/a53674"]

Here's the full list of embed options you can pass via shortcode attributes; some are specific to the type of resource you're embedding.

### All resources:

- `url` (**required**, string): Full URL of the DocumentCloud resource.
- `container` (string): ID of element to insert the embed into; if excluded, embedder will create its own container.

### Documents only:

- `height` (integer): Height (in pixels) of the embed.
- `width` (integer): Width (in pixels) of the embed. If used, will implicitly set `responsive="false"`.
- `responsive` (boolean): Use responsive layout, which dynamically adjusts width to fill content area. Defaults `true`.
- `responsive_offset` (integer): Distance (in pixels) to vertically offset the viewer for some responsive embeds.
- `default_page` (integer): Page number to have the document scroll to by default.
- `default_note` (integer): ID of the note that the document should highlight by default.
- `notes` (boolean): Show/hide notes:
- `search` (boolean): Hide or show search form.
- `sidebar` (boolean): Hide or show sidebar. Defaults `false`.
- `pdf` (boolean): Hide or show link to download original PDF. Defaults `true`.
- `text` (boolean): Hide or show text tab. Defaults `true`.
- `zoom` (boolean): Hide or show zoom slider.
- `format` (string): Indicate to the theme that this is a wide asset by setting this to `wide`. Defaults `normal`.

You can read more about publishing and embedding DocumentCloud resources on https://www.documentcloud.org/help/publishing.

## How the oEmbed endpoint is discovered

To make a resource [discoverable](http://oembed.com/#section4) by oEmbed consumers, you can include a `<link>` tag that specifies the oEmbed endpoint URL. So in one version of reality, once you tell WordPress "this resource is oEmbeddable", WordPress would cURL the resource URL, look for the oEmbed link tag in its header, pluck out the oEmbed endpoint from the `<link>` tag's `href`, and then hit that endpoint for the embed code. In our actual reality, that's considered a waste of a cURL, so we have to actually describe the format of our oEmbed endpoint within WordPress itself.

## Caching

Ideally, when WordPress hits our oEmbed service to fetch the embed code, it would obey the `cache_age` we return. Despite [conversation](https://core.trac.wordpress.org/ticket/14759) around this, it doesn't seem to.

Instead, it lets us choose between no cache at all (so *every pageload* triggers a call to our oEmbed service to get the embed code) or a supposed 24-hour cache stored in the `postmeta` table. Unfortunately, [our tests](https://github.com/documentcloud/wordpress-documentcloud/issues/20) seem to show this cache is never expired, which means we can choose between no cache (thus possibly DDOSing ourselves) or a permanent cache (thus possibly having stale embed codes). We've chosen the latter; hopefully this cache does eventually expire, and our embed codes shouldn't change that often anyway.

If you find yourself absolutely needing to expire the cache, though, you have two choices:

1. Delete the appropriate `_oembed_*` rows from your `postmeta` table.
2. Modify the shortcode attributes for the embed, since this is recognized as a new embed by WordPress.

## Changelog

### 0.4.0
* Support embedding pages (#28)
* Support embedding raw contextual page/note URLs (#29)
* Check for existence of things to stop triggering PHP notices (#27)
* Add DocumentCloud logo for plugin (#26)
* Fix ability to specificy a container in the shortcode
* Improve embeddable resource pattern recognition

### 0.3.3
* Remove unused TinyMCE components
* Resolve CVE-2015-2807 reported by dxw Security at https://security.dxw.com/advisories/publicly-exploitable-xss-in-wordpress-plugin-navis-documentcloud/

### 0.3.2
* Implement a few best practice security measures

### 0.3.1
* Check for old (Navis) plugin and warn admins of conflict
* Add note about raw URLs to README
* Stop storing shortcode attributes in the `postmeta` table

### 0.3
* Add support for embedding notes.
* Default to responsive.
* Enable caching.

### 0.2
* Fetch embed code via oEmbed instead of generating statically.
* Add new options: `container`, `responsive`, `responsive_offset`, `default_page`, `default_note`, `notes`, `search`, and `zoom`.
* Deprecate `id` attribute. It's still usable, but support may drop in the future. Use `url` instead.

### 0.1
* Initial release.

## License and History

The DocumentCloud WordPress plugin is [GPLv2](http://www.gnu.org/licenses/gpl-2.0.html). Initial development of this plugin by Chris Amico (@eyeseast) supported by [NPR](http://www.npr.org) as part of the [StateImpact](http://stateimpact.npr.org) project. Development continued by Justin Reese (@reefdog) at [DocumentCloud](https://www.documentcloud.org/).
