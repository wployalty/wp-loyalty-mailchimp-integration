<?php

namespace WLMI\App\Helper;

use Valitron\Validator;

defined( 'ABSPATH' ) || exit;

class Validation {
	/**
	 * validate and sanitize text field value
	 *
	 * @param $field
	 * @param $value
	 * @param array $params
	 * @param array $fields
	 *
	 * @return bool
	 */
	public static function validateSanitizeText( $field, $value, array $params, array $fields ) {
		$after_value = sanitize_text_field( $value );
		$status      = false;
		if ( $value === $after_value ) {
			$status = true;
		}

		return $status;
	}

	/**
	 * validate the conditional values
	 *
	 * @param $field
	 * @param $value
	 * @param array $params
	 * @param array $fields
	 *
	 * @return bool
	 */
	public static function validateCleanHtml( $field, $value, array $params, array $fields ) {
		$html  = Util::getCleanHtml( $value );
		$value = str_replace( '&amp;', '&', $value );
		$html  = str_replace( '&amp;', '&', $html );
		if ( $html != $value ) {
			return false;
		}

		return true;
	}

	/**
	 * Validate the number field value.
	 *
	 * @param mixed $field The field being validated.
	 * @param mixed $value The value of the field to be validated.
	 * @param array $params Additional parameters for validation (not used in this method).
	 * @param array $fields Other fields in the form (not used in this method).
	 *
	 * @return bool True if the value is a valid number, false otherwise.
	 */
	public static function validateNumber( $field, $value, $params, $fields ) {
		$value = (int) $value;

		return preg_match( '/^([0-9])+$/i', $value );
	}

	/**
	 * Validates if a field is empty.
	 *
	 * @param mixed $field The field being validated.
	 * @param mixed $value The value of the field to be checked for emptiness.
	 * @param array $params Additional parameters for validation (not used in the method).
	 * @param array $fields Additional fields for validation (not used in the method).
	 *
	 * @return bool Returns true if the value is not empty, false otherwise.
	 */
	public static function validateIsEmpty( $field, $value, array $params, array $fields ) {
		$status = false;
		if ( ! empty( $value ) ) {
			$status = true;
		}

		return $status;
	}

	/**
	 * Validate the settings tab fields.
	 *
	 * @param array $post The post data containing settings tab information.
	 *
	 * @return bool|array True if validation passes, or an array of validation errors.
	 */
	public static function validateSettingsTab( $post ) {
		$validator       = new Validator( $post );
		$settings_labels = [
			'settings.api_key',
		];
		$this_field      = __( 'This field', 'wp-loyalty-mailchimp-integration' );
		$labels          = array_fill_keys( $settings_labels, $this_field );
		$validator->labels( $labels );
		$validator->stopOnFirstFail( false );

		Validator::addRule( 'sanitizeText', [
			self::class,
			'validateSanitizeText'
		], __( 'Invalid characters', 'wp-loyalty-mailchimp-integration' ) );

		$required_fields = $sanitize_text = [];
		if ( ! empty( $post['settings'] ) && is_array( $post['settings'] ) ) {
			foreach ( $post['settings'] as $key => $value ) {
				switch ( $key ) {
					case 'api_key':
						$required_fields[] = 'settings.' . $key;
						$sanitize_text[]   = 'settings.' . $key;
						break;
				}
			}
		}
		$validator->rule( 'required', $required_fields )->message( __( '{field} is required', 'wp-loyalty-mailchimp-integration' ) );
		$validator->rule( 'sanitizeText', $sanitize_text );

		return $validator->validate() ? true : $validator->errors();
	}
}