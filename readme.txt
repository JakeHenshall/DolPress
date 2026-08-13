=== DolPress ===
Contributors: noughtdigital
Tags: editor, doldoc, templeos, gutenberg, documents
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

DolDoc-inspired document editor for WordPress by Nought Digital. Inspired by TempleOS DolDoc; not TempleOS and not a complete DolDoc implementation.

== Description ==

DolPress replaces Gutenberg and the Classic Editor for selected public post types with one continuous document. Authors write DolPress source using `$XX,...$` commands, switch between source, rendered, and split modes, and publish through native WordPress save, revision, preview, and REST workflows.

DolPress is inspired by TempleOS DolDoc. It is not an emulator, does not run HolyC, and does not claim compatibility with original `.DD` files.

= Recovery =

* Settings > DolPress > Emergency disable restores the native editor.
* Administrators can open a post with a capability- and nonce-protected safe-mode link from the editor toolbar.
* Deactivation restores Gutenberg or Classic Editor. Posts are never deleted.

= Commands =

Presentation: TX, CR, FG, BG, UL, IV, HL, LK, BT, TR, IM, HR.
WordPress: WS, WG, WT, WA, WN, WL, WP, WC, WX, WM, WB.

See the plugin `examples/welcome.dolpress` file for a copyable starter document.

== Installation ==

1. Upload the DolPress ZIP through Plugins > Add New > Upload Plugin.
2. Activate DolPress.
3. Open Settings > DolPress to choose post types, limits, caching, and emergency disable.
4. Edit a post or page. DolPress loads instead of Gutenberg for enabled types.

== Changelog ==

= 0.1.0 =
* Initial Nought Digital MVP.

== Upgrade Notice ==

= 0.1.0 =
First public MVP.

== Security ==

Report vulnerabilities privately to security@noughtdigital.com. DolPress never executes PHP, JavaScript, SQL, shell, or HolyC from document source.
