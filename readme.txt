=== DocumentCloud ===
Contributors: chrisamico, reefdog
Tags: documentcloud, documents, journalism, reporting, research
Requires at least: 3.2
Tested up to: 4.1.1
Stable tag: trunk
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Embed DocumentCloud resources in WordPress content.

== Description ==

[DocumentCloud](https://www.documentcloud.org/) is a service that allows journalists to analyze, annotate and publish documents, hosted by Investigative Reporters & Editors. Initial development of this plugin supported by [NPR](http://www.npr.org) as part of [StateImpact](http://stateimpact.npr.org) project.

This plugin allows you embed DocumentCloud resources using a custom shortcode:

    [documentcloud url="https://www.documentcloud.org/documents/282753-lefler-thesis.html"]

When you save, WordPress fetches and stores the actual embed code HTML from the DocumentCloud servers using oEmbed. You can freely toggle between visual and HTML mode without mangling embed code, and your embed will always be up to date with the latest embed code.

By default, documents will be 600px wide and 620px tall. You can set your own defaults in Settings > DocumentCloud, or override the defaults on individual embeds using these attributes:

    [documentcloud url="https://www.documentcloud.org/documents/282753-lefler-thesis.html" width="400" height="500"]

You can also forego width/height and have the document fill the horizontal width of its container by using the `responsive="true"` shortcode. (Notes ignore width/height and always act responsively.)

Here's the full list of embed options you can pass via shortcode attributes.

All resources (documents and notes):

- `url` (**required**, string): Full URL of the DocumentCloud resource.
- `container` (string): ID of element to insert the embed into; if excluded, embedder will create its own container.

Documents only:

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

You can read more about publishing and embedding DocumentCloud resources on https://www.documentcloud.org/help/publishing.

== Installation ==

1. Upload the contents of the plugin to `wp-content/plugins/documentcloud`
2. Activate the plugin through the "Plugins" menu
3. Set a default width/height for all DocumentCloud embeds (which can be overridden on a per-embed basis with the `height/width` attributes) at Settings > DocumentCloud
4. In your posts, add documents using the DocumentCloud button or the `[documentcloud]` shortcode

== Changelog ==

= 0.3 =
* Added support for embedding notes.
* Default to responsive

= 0.2 =
* Fetch embed code via oEmbed instead of generating statically.
* Added new options: `container`, `responsive`, `responsive_offset`, `default_page`, `default_note`, `notes`, `search`, and `zoom`.
* Deprecated `id` attribute. It's still usable, but support may drop in the future. Use `url` instead.

= 0.1 =
* Initial release.

== Upgrade Notice ==

= 0.3 =
Adds support for embedding notes.

= 0.2 =
Adds oEmbed support for future-proofing embed codes. Provides additional embed options like `default_page`.
