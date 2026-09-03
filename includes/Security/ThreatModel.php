<?php
/**
 * Structured threat model used by tests and the security pass.
 *
 * @package DolPress
 */

declare(strict_types=1);

namespace Nought\DolPress\Security;

final class ThreatModel {
	/**
	 * @return array<string, mixed>
	 */
	public static function document(): array {
		return array(
			'version'         => '0.1.0',
			'trustBoundaries' => array(
				'untrusted_source' => 'Authors and imported post_content.',
				'wordpress_core'   => 'Capabilities, REST, posts, media, comments.',
				'renderer'         => 'Allowlisted commands to escaped HTML.',
				'browser_editor'   => 'Feedback only; never authoritative.',
			),
			'actors'          => array( 'anonymous', 'subscriber', 'contributor', 'author', 'editor', 'administrator', 'malicious_extension' ),
			'assets'          => array( 'post_content', 'private_posts', 'protected_meta', 'credentials', 'admin_session' ),
			'controls'        => array(
				'no_eval'           => true,
				'schema_validation' => true,
				'capability_checks' => true,
				'rest_permissions'  => true,
				'nonces'            => true,
				'url_allowlist'     => array( 'http', 'https', 'mailto' ),
				'kses_policy'       => true,
				'private_meta_deny' => true,
				'query_limits'      => true,
				'recursion_guards'  => true,
				'cache_separation'  => true,
				'action_allowlist'  => true,
				'html_code_opt_in'  => true,
			),
			'abuseCases'      => array(
				'arbitrary_php'       => 'Commands cannot include PHP or callbacks from source.',
				'xss'                 => 'All text is escaped; tags pass a plugin kses policy.',
				'csrf'                => 'REST uses cookie auth + wp_rest nonce; admin settings use Settings API.',
				'ssrf'                => 'No remote fetches from document source.',
				'sql_injection'       => 'No SQL fragments; WP_Query only through a schema.',
				'capability_bypass'   => 'edit_post required for preview/parse; manage_options for settings.',
				'private_leak'        => 'Loops and WP/WM/IM check visibility.',
				'shortcode_execution' => 'Ordinary text is escaped; shortcodes are not executed.',
				'cache_poisoning'     => 'Cache keys include viewer and locale; logged-in HTML is not cached.',
				'dos'                 => 'Source, token, command, nest, and query limits bound every parse; the render time budget is advisory (diagnostic only); REST compute endpoints are per-user rate limited.',
			),
			'disclosure'      => array(
				'contact' => 'jake@nought.digital',
				'policy'  => 'Report privately. Do not exploit production sites.',
			),
		);
	}
}
