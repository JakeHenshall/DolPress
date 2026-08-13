<?php
/**
 * Settings screen under Settings > DolPress.
 *
 * @package DolPress
 */

declare(strict_types=1);

namespace Nought\DolPress\Admin;

use Nought\DolPress\Support\PostTypePolicy;
use Nought\DolPress\Support\SettingsRepository;
use Nought\DolPress\Support\SettingsSanitizer;

final class SettingsPage {
	public function __construct( private readonly SettingsRepository $settings ) {}

	public function register(): void {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
	}

	public function register_menu(): void {
		add_options_page(
			__( 'DolPress', 'dolpress' ),
			__( 'DolPress', 'dolpress' ),
			'manage_options',
			'dolpress',
			array( $this, 'render_page' )
		);
	}

	public function register_settings(): void {
		register_setting(
			'dolpress',
			SettingsRepository::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this->settings, 'sanitise_from_request' ),
				'default'           => SettingsSanitizer::defaults(),
				'show_in_rest'      => false,
			)
		);

		add_settings_section(
			'dolpress_general',
			__( 'Editor', 'dolpress' ),
			static function (): void {
				echo '<p>' . esc_html__( 'DolPress replaces Gutenberg for selected public post types. Internal WordPress types cannot be enabled.', 'dolpress' ) . '</p>';
			},
			'dolpress'
		);

		$this->add_field( 'enabled_post_types', __( 'Enabled post types', 'dolpress' ), 'render_post_types' );
		$this->add_field( 'default_mode', __( 'Default editor mode', 'dolpress' ), 'render_default_mode' );
		$this->add_field( 'strict_diagnostics', __( 'Strict publishing diagnostics', 'dolpress' ), 'render_checkbox', __( 'Warn authors before publishing documents with structural errors.', 'dolpress' ) );
		$this->add_field( 'public_invalid_command', __( 'Public invalid-command behaviour', 'dolpress' ), 'render_invalid_command' );
		$this->add_field( 'allowed_meta_keys', __( 'Allowed public meta keys', 'dolpress' ), 'render_meta_keys' );
		$this->add_field( 'max_loop_count', __( 'Maximum loops per document', 'dolpress' ), 'render_number' );
		$this->add_field( 'max_command_count', __( 'Maximum commands per document', 'dolpress' ), 'render_number' );
		$this->add_field( 'cache_enabled', __( 'Cache rendered output', 'dolpress' ), 'render_checkbox' );
		$this->add_field( 'fallback_behaviour', __( 'Fallback behaviour', 'dolpress' ), 'render_fallback' );
		$this->add_field( 'global_disable', __( 'Emergency disable', 'dolpress' ), 'render_checkbox', __( 'Disable editor replacement globally without deleting content.', 'dolpress' ) );
		$this->add_field( 'uninstall_delete_data', __( 'Delete data on uninstall', 'dolpress' ), 'render_checkbox', __( 'When unchecked, settings are preserved after uninstall. Posts are never deleted.', 'dolpress' ) );
	}

	private function add_field( string $id, string $title, string $callback, string $description = '' ): void {
		add_settings_field(
			$id,
			$title,
			array( $this, $callback ),
			'dolpress',
			'dolpress_general',
			array(
				'label_for'   => 'dolpress_' . $id,
				'key'         => $id,
				'description' => $description,
			)
		);
	}

	/**
	 * @param array<string, mixed> $args
	 */
	public function render_post_types( array $args ): void {
		$enabled = $this->settings->get( 'enabled_post_types', PostTypePolicy::DEFAULT_ENABLED );
		$types   = get_post_types( array( 'public' => true ), 'objects' );

		echo '<fieldset>';
		foreach ( $types as $type ) {
			if ( PostTypePolicy::is_blocked( $type->name ) ) {
				continue;
			}

			$checked = in_array( $type->name, (array) $enabled, true );
			printf(
				'<label><input type="checkbox" name="%1$s[enabled_post_types][]" value="%2$s" %3$s /> %4$s <code>%2$s</code></label><br />',
				esc_attr( SettingsRepository::OPTION_KEY ),
				esc_attr( $type->name ),
				checked( $checked, true, false ),
				esc_html( $type->label )
			);
		}
		echo '</fieldset>';
	}

	/**
	 * @param array<string, mixed> $args
	 */
	public function render_default_mode( array $args ): void {
		$current = (string) $this->settings->get( 'default_mode', 'source' );
		echo '<select id="dolpress_default_mode" name="' . esc_attr( SettingsRepository::OPTION_KEY ) . '[default_mode]">';
		foreach ( SettingsSanitizer::MODES as $mode ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $mode ),
				selected( $current, $mode, false ),
				esc_html( ucfirst( $mode ) )
			);
		}
		echo '</select>';
	}

	/**
	 * @param array<string, mixed> $args
	 */
	public function render_invalid_command( array $args ): void {
		$current = (string) $this->settings->get( 'public_invalid_command', 'hide' );
		echo '<select id="dolpress_public_invalid_command" name="' . esc_attr( SettingsRepository::OPTION_KEY ) . '[public_invalid_command]">';
		printf( '<option value="hide" %s>%s</option>', selected( $current, 'hide', false ), esc_html__( 'Hide from visitors', 'dolpress' ) );
		printf( '<option value="escape" %s>%s</option>', selected( $current, 'escape', false ), esc_html__( 'Show escaped source', 'dolpress' ) );
		echo '</select>';
	}

	/**
	 * @param array<string, mixed> $args
	 */
	public function render_fallback( array $args ): void {
		$current = (string) $this->settings->get( 'fallback_behaviour', 'escaped' );
		echo '<select id="dolpress_fallback_behaviour" name="' . esc_attr( SettingsRepository::OPTION_KEY ) . '[fallback_behaviour]">';
		printf( '<option value="escaped" %s>%s</option>', selected( $current, 'escaped', false ), esc_html__( 'Escaped source', 'dolpress' ) );
		printf( '<option value="snapshot" %s>%s</option>', selected( $current, 'snapshot', false ), esc_html__( 'Cached snapshot when available', 'dolpress' ) );
		echo '</select>';
	}

	/**
	 * @param array<string, mixed> $args
	 */
	public function render_meta_keys( array $args ): void {
		$keys = $this->settings->allowed_meta_keys();
		printf(
			'<textarea id="dolpress_allowed_meta_keys" name="%s[allowed_meta_keys]" rows="4" cols="40" class="large-text code">%s</textarea>',
			esc_attr( SettingsRepository::OPTION_KEY ),
			esc_textarea( implode( "\n", $keys ) )
		);
		echo '<p class="description">' . esc_html__( 'One public meta key per line. Keys beginning with an underscore are rejected.', 'dolpress' ) . '</p>';
	}

	/**
	 * @param array<string, mixed> $args
	 */
	public function render_checkbox( array $args ): void {
		$key     = (string) $args['key'];
		$checked = (bool) $this->settings->get( $key, false );
		printf(
			'<label><input type="checkbox" id="dolpress_%1$s" name="%2$s[%1$s]" value="1" %3$s /> %4$s</label>',
			esc_attr( $key ),
			esc_attr( SettingsRepository::OPTION_KEY ),
			checked( $checked, true, false ),
			esc_html( (string) ( $args['description'] ?? '' ) )
		);
	}

	/**
	 * @param array<string, mixed> $args
	 */
	public function render_number( array $args ): void {
		$key   = (string) $args['key'];
		$value = (int) $this->settings->get( $key, 0 );
		printf(
			'<input type="number" id="dolpress_%1$s" name="%2$s[%1$s]" value="%3$s" class="small-text" />',
			esc_attr( $key ),
			esc_attr( SettingsRepository::OPTION_KEY ),
			esc_attr( (string) $value )
		);
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage DolPress settings.', 'dolpress' ) );
		}

		echo '<div class="wrap"><h1>' . esc_html__( 'DolPress', 'dolpress' ) . '</h1>';
		echo '<form action="options.php" method="post">';
		settings_fields( 'dolpress' );
		do_settings_sections( 'dolpress' );
		submit_button();
		echo '</form></div>';
	}
}
