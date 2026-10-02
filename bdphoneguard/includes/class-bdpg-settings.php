<?php
/**
 * Admin settings page under Settings → BD Phone Guard.
 *
 * @package BDPhoneGuard
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers and renders the settings screen.
 */
class BD_Phone_Guard_Settings {

	/**
	 * Hooks the screen. Registered on init; admin_menu and admin_init both
	 * fire after init in the admin.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( BDPG_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Adds the settings page.
	 */
	public static function register_page() {
		add_options_page(
			__( 'BD Phone Guard', 'bdphoneguard' ),
			__( 'BD Phone Guard', 'bdphoneguard' ),
			'manage_options',
			'bdphoneguard',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Registers the option group, section and fields. settings_fields()
	 * renders the nonce for the form.
	 */
	public static function register_settings() {
		register_setting(
			'bdpg_settings',
			BD_Phone_Guard_Options::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'BD_Phone_Guard_Options', 'sanitize' ),
				'default'           => BD_Phone_Guard_Options::defaults(),
			)
		);

		add_settings_section(
			'bdpg_section_rules',
			__( 'Validation rules', 'bdphoneguard' ),
			array( __CLASS__, 'render_rules_intro' ),
			'bdphoneguard'
		);

		add_settings_field( 'output_format', __( 'Saved number format', 'bdphoneguard' ), array( __CLASS__, 'field_output_format' ), 'bdphoneguard', 'bdpg_section_rules' );
		add_settings_field( 'error_language', __( 'Error message language', 'bdphoneguard' ), array( __CLASS__, 'field_error_language' ), 'bdphoneguard', 'bdpg_section_rules' );
		add_settings_field( 'reject_repeated', __( 'Strictness', 'bdphoneguard' ), array( __CLASS__, 'field_reject_repeated' ), 'bdphoneguard', 'bdpg_section_rules' );

		add_settings_section(
			'bdpg_section_places',
			__( 'Where to validate', 'bdphoneguard' ),
			'__return_false',
			'bdphoneguard'
		);

		add_settings_field( 'enable_checkout', __( 'WooCommerce checkout', 'bdphoneguard' ), array( __CLASS__, 'field_enable_checkout' ), 'bdphoneguard', 'bdpg_section_places' );
		add_settings_field( 'enable_account', __( 'WooCommerce My Account pages', 'bdphoneguard' ), array( __CLASS__, 'field_enable_account' ), 'bdphoneguard', 'bdpg_section_places' );
	}

	/**
	 * Short explainer above the rules section.
	 */
	public static function render_rules_intro() {
		echo '<p class="description">' . esc_html__( 'A valid Bangladeshi mobile number has 11 digits and starts with an operator prefix from 013 to 019, for example 01712345678 or +8801712345678.', 'bdphoneguard' ) . '</p>';
	}

	/**
	 * Radio list for the output format.
	 */
	public static function field_output_format() {
		$options = bdpg_get_options();

		$formats = array(
			'national' => __( '01XXXXXXXXX — no country code', 'bdphoneguard' ),
			'dashed'   => __( '01XXX-XXXXXX — pretty local style', 'bdphoneguard' ),
			'e164'     => __( '+8801XXXXXXXXX — international (E.164)', 'bdphoneguard' ),
			'off'      => __( 'Keep what the customer typed — validate only', 'bdphoneguard' ),
		);

		echo '<fieldset>';
		foreach ( $formats as $value => $label ) {
			printf(
				'<label style="display:block;margin-bottom:4px;"><input type="radio" name="%1$s[output_format]" value="%2$s" %3$s /> %4$s</label>',
				esc_attr( BD_Phone_Guard_Options::OPTION_KEY ),
				esc_attr( $value ),
				checked( $options['output_format'], $value, false ),
				esc_html( $label )
			);
		}
		echo '</fieldset>';
	}

	/**
	 * Select for the error language.
	 */
	public static function field_error_language() {
		$options  = bdpg_get_options();
		$language = array(
			'auto' => __( 'Detect from the site language', 'bdphoneguard' ),
			'bn'   => __( 'বাংলা (Bangla)', 'bdphoneguard' ),
			'en'   => __( 'English', 'bdphoneguard' ),
		);

		printf(
			'<select name="%s[error_language]">',
			esc_attr( BD_Phone_Guard_Options::OPTION_KEY )
		);

		foreach ( $language as $value => $label ) {
			printf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( $value ),
				selected( $options['error_language'], $value, false ),
				esc_html( $label )
			);
		}

		echo '</select>';
	}

	/**
	 * Checkbox for the fake-number heuristic.
	 */
	public static function field_reject_repeated() {
		$options = bdpg_get_options();

		printf(
			'<label><input type="checkbox" name="%1$s[reject_repeated]" value="1" %2$s /> %3$s</label>',
			esc_attr( BD_Phone_Guard_Options::OPTION_KEY ),
			checked( ! empty( $options['reject_repeated'] ), true, false ),
			esc_html__( 'Reject obviously fake numbers like 01700000000 or 01877777777.', 'bdphoneguard' )
		);
	}

	/**
	 * Checkbox for the checkout integration.
	 */
	public static function field_enable_checkout() {
		$options = bdpg_get_options();

		printf(
			'<label><input type="checkbox" name="%1$s[enable_checkout]" value="1" %2$s /> %3$s</label>',
			esc_attr( BD_Phone_Guard_Options::OPTION_KEY ),
			checked( ! empty( $options['enable_checkout'] ), true, false ),
			esc_html__( 'Validate the billing and shipping phone during checkout and save them in the chosen format.', 'bdphoneguard' )
		);
	}

	/**
	 * Checkbox for the My Account integration.
	 */
	public static function field_enable_account() {
		$options = bdpg_get_options();

		printf(
			'<label><input type="checkbox" name="%1$s[enable_account]" value="1" %2$s /> %3$s</label>',
			esc_attr( BD_Phone_Guard_Options::OPTION_KEY ),
			checked( ! empty( $options['enable_account'] ), true, false ),
			esc_html__( 'Also check the phone fields on the Addresses and Account details pages.', 'bdphoneguard' )
		);
	}

	/**
	 * Renders the settings form. settings_fields() adds the nonce and
	 * do_settings_sections() renders every field registered above.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$shortcode_example = '[bd_phone_guard name="phone" label="Mobile number" placeholder="01712345678" required="yes"]';
		$php_example       = '$phone = bdpg_normalize_phone( $raw ); // returns "+8801712345678" or a WP_Error';
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<form action="options.php" method="post">
				<?php
				settings_fields( 'bdpg_settings' );
				do_settings_sections( 'bdphoneguard' );
				submit_button();
				?>
			</form>

			<h2><?php esc_html_e( 'Use it anywhere', 'bdphoneguard' ); ?></h2>
			<p><?php esc_html_e( 'Add a self-validating phone field to any form or page with the shortcode:', 'bdphoneguard' ); ?></p>
			<p><code><?php echo esc_html( $shortcode_example ); ?></code></p>

			<p><?php esc_html_e( 'In PHP, other plugins and themes can use these functions:', 'bdphoneguard' ); ?></p>
			<p>
				<code><?php echo esc_html( $php_example ); ?></code><br />
				<code><?php echo esc_html( '$valid = bdpg_validate_phone( $raw ); // true or WP_Error' ); ?></code>
			</p>
		</div>
		<?php
	}

	/**
	 * Adds a Settings link on the Plugins screen.
	 *
	 * @param array $links Existing action links.
	 * @return array
	 */
	public static function action_links( $links ) {
		$url = admin_url( 'options-general.php?page=bdphoneguard' );

		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'bdphoneguard' ) . '</a>' );

		return $links;
	}
}
