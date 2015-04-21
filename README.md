# DocumentCloud WordPress plugin

The DocumentCloud WordPress plugin lets you embed [DocumentCloud](https://www.documentcloud.org/) resources into WordPress content using [shortcodes](https://codex.wordpress.org/Shortcode_API).

    [documentcloud url="https://www.documentcloud.org/documents/282753-lefler-thesis.html"]

## Installation

1. Upload the contents of the plugin to `wp-content/plugins/documentcloud`
2. Activate the plugin through the "Plugins" menu
3. Set a default width/height for all DocumentCloud embeds (which can be overridden on a per-embed basis with the `height/width` attributes) at Settings > DocumentCloud
4. In your posts, add documents using the DocumentCloud button or the `[documentcloud]` shortcode

## Usage

There are many options you can set using shortcode attributes:

- `url` (**required**, string): Full URL of the DocumentCloud resource.
- `container` (string): ID of element to insert the document into; if excluded, embedder will create its own container.
- `height` (integer): Height (in pixels) of the embed.
- `width` (integer): Width (in pixels) of the embed.
- `responsive` (boolean): Use responsive layout.
- `responsive_offset` (integer): Distance (in pixels) to vertically offset the viewer for some responsive embeds.
- `default_page` (integer): Page number to have the document scroll to by default.
- `default_note` (integer): ID of the note that the document should highlight by default.
- `notes` (boolean): Show/hide notes:
- `search` (boolean): Hide or show search form.
- `sidebar` (boolean): Hide or show sidebar. Defaults `false`.
- `pdf` (boolean): Hide or show link to download original PDF.
- `text` (boolean): Hide or show text tab.
- `zoom` (boolean): Hide or show zoom slider.
- `format` (string): Indicate to the theme that this is a wide asset by setting this to `wide`. Defaults `normal`.

For example, if you want to embed a document at 800px wide with no sidebar, pre-scrolled to page 3:

    [documentcloud url="https://www.documentcloud.org/documents/282753-lefler-thesis.html" width="800" sidebar="false" default_page="3"]

## Changelog

### 0.2
* Fetch embed code via oEmbed instead of generating statically.
* Added new options: `container`, `responsive`, `responsive_offset`, `default_page`, `default_note`, `notes`, `search`, and `zoom`.
* Deprecated `id` attribute. It's still usable, but support may drop in the future. Use `url` instead.

### 0.1
* Initial release.

## History

Initial development of this plugin by Chris Amico (@eyeseast) supported by [NPR](http://www.npr.org) as part of [StateImpact](http://stateimpact.npr.org) project.

## License

The DocumentCloud WordPress plugin is [GPLv2](http://www.gnu.org/licenses/gpl-2.0.html).