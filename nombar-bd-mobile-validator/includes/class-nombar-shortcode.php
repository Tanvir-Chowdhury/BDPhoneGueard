<?php
/**
 * [nombar] shortcode: a phone field that validates itself live.
 *
 * @package Nombar
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders a single phone input with client-side validation and a live hint.
 *
 * Without a name attribute the field stores nothing — useful as a demo.
 * With a name attribute it can be dropped into any plain HTML form.
 */
class Nombar_Shortcode {

	/**
	 * Registers the shortcode.
	 */
	public static function init() {
		add_shortcode( 'nombar', array( __CLASS__, 'render' ) );
	}

	/**
	 * Renders the field.
	 *
	 * @param array $atts name, label, placeholder, required.
	 * @return string
	 */
	public static function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'name'        => '',
				'label'       => '',
				'placeholder' => '',
				'required'    => 'no',
			),
			$atts,
			'nombar'
		);

		wp_enqueue_style( 'nombar-public' );
		wp_enqueue_script( 'nombar-public' );

		$field_id = 'nombar-phone-' . wp_unique_id();

		$attributes = array(
			'type="tel"',
			'id="' . esc_attr( $field_id ) . '"',
			'class="nombar-phone-input"',
			'autocomplete="tel"',
			'inputmode="tel"',
		);

		if ( '' !== $atts['name'] ) {
			$attributes[] = 'name="' . esc_attr( $atts['name'] ) . '"';
		}

		if ( '' !== $atts['placeholder'] ) {
			$attributes[] = 'placeholder="' . esc_attr( $atts['placeholder'] ) . '"';
		}

		if ( 'yes' === strtolower( (string) $atts['required'] ) ) {
			$attributes[] = 'required';
		}

		ob_start();
		?>
		<div class="nombar-phone-field">
			<?php if ( '' !== $atts['label'] ) : ?>
				<label class="nombar-phone-label" for="<?php echo esc_attr( $field_id ); ?>"><?php echo esc_html( $atts['label'] ); ?></label>
			<?php endif; ?>
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every attribute value is esc_attr()'d when the list is built.
			echo '<input ' . implode( ' ', $attributes ) . ' />';
			?>
			<p class="nombar-phone-message" role="status" aria-live="polite"></p>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
