<?php

namespace WLMI\App\Helper;
defined( 'ABSPATH' ) || exit;

class Util {
	/**
	 * Get the admin settings page URL for this plugin.
	 * The page/tab to load is determined by the optional param (hash route).
	 *
	 * @param string $page Optional. Hash route segment to open (e.g. 'license', 'settings'). Results in #/license, #/settings.
	 * @return string Full URL to the plugin admin page, with optional hash.
	 */
	public static function getSettingsPageUrl( string $page = '' ): string {
		$url = admin_url( 'admin.php?' . http_build_query( [ 'page' => WLMI_PLUGIN_SLUG ] ) );
		if ( $page !== '' ) {
			$url .= '#/' . ltrim( $page, '/' );
		}

		return $url;
	}

	/**
	 * Check if a given string is a valid JSON format.
	 *
	 * @param string $string The string to check for JSON format.
	 *
	 * @return bool Returns true if the string is in valid JSON format, false otherwise.
	 */
	public static function isJson( $string ) {
		json_decode( $string );

		return ( json_last_error() == JSON_ERROR_NONE );
	}

	/**
	 * Prepare the date for display before displaying.
	 *
	 * @param mixed $date The date to be formatted.
	 * @param string $format The format in which the date should be displayed. Default is empty.
	 *
	 * @return mixed Formatted date for display or null if date is empty.
	 */
	public static function beforeDisplayDate( $date, $format = '' ) {
		if ( empty( $format ) ) {
			$format = get_option( 'date_format', 'Y-m-d H:i:s' );
		}
		if ( empty( $date ) ) {
			return null;
		}
		if ( (int) $date != $date ) {
			return $date;
		}
		$converted_time = self::convertUTCtoWP( gmdate( 'Y-m-d H:i:s', $date ), $format );
		if ( apply_filters( 'wlmi_translate_display_date', true ) ) {
			$datetime = \DateTime::createFromFormat( $format, $converted_time );
			if ( $datetime !== false ) {
				$time = $datetime->getTimestamp();
			} else {
				$time = strtotime( $converted_time );
			}
			$converted_time = date_i18n( $format, $time );
		}

		return $converted_time;
	}

	/**
	 * Convert UTC timestamp to WordPress timezone.
	 *
	 * @param string $datetime The UTC date/time string to convert.
	 * @param string $format The format to return the date/time in. Default is 'Y-m-d H:i:s'.
	 * @param string $modify Optional. A date interval specification to modify the date/time. Default is ''.
	 *
	 * @return string The converted date/time string in WordPress timezone or the original datetime string if conversion fails.
	 */
	public static function convertUTCtoWP( $datetime, $format = 'Y-m-d H:i:s', $modify = '' ) {
		try {
			$timezone     = new \DateTimeZone( 'UTC' );
			$current_time = new \DateTime( $datetime, $timezone );
			if ( ! empty( $modify ) ) {
				$current_time->modify( $modify );
			}
			$wp_time_zone = new \DateTimeZone( WC::getWPZone() );
			$current_time->setTimezone( $wp_time_zone );
			$converted_time = $current_time->format( $format );
		} catch ( \Exception $e ) {
			$converted_time = $datetime;
		}
		return $converted_time;
	}

	/**
	 * Format UTC datetime string using WordPress date and time format settings.
	 *
	 * @param string $datetime_utc The UTC date/time string to format.
	 *
	 * @return string Formatted date/time string using WordPress date_format and time_format, or original string if conversion fails.
	 */
	public static function formatDateTimeWP( $datetime_utc ) {
		if ( empty( $datetime_utc ) ) {
			return '';
		}

		try {
			// Convert UTC to WP timezone first
			$wp_datetime = self::convertUTCtoWP( $datetime_utc, 'Y-m-d H:i:s' );
			
			// Get WordPress date and time format
			$date_format = get_option( 'date_format', 'Y-m-d' );
			$time_format = get_option( 'time_format', 'H:i:s' );
			$wp_format   = $date_format . ' ' . $time_format;
			
			// Convert to timestamp for date_i18n
			$timestamp = strtotime( $wp_datetime );
			if ( $timestamp === false ) {
				return $datetime_utc;
			}
			
			// Format using WordPress date/time format with i18n support
			return date_i18n( $wp_format, $timestamp );
		} catch ( \Exception $e ) {
			return $datetime_utc;
		}
	}

	/**
	 * Get current time formatted for display using WordPress date and time format settings.
	 *
	 * Gets the current time in UTC, formats it using WordPress date/time format,
	 * or falls back to current WordPress time if UTC is unavailable.
	 *
	 * @return string Formatted current time using WordPress date_format and time_format.
	 */
	public static function getCurrentTimeFormatted() {
		$current_time_utc = current_time( 'mysql', true );
		return ! empty( $current_time_utc ) 
			? self::formatDateTimeWP( $current_time_utc )
			: current_time( 'mysql' );
	}

	/**
	 * Converts the date format.
	 *
	 * @param string $date The date to convert.
	 * @param string $format The format to convert the date into. If empty, the default WordPress date format will be used.
	 *
	 * @return string|null The converted date in the specified format, or null if input date is empty.
	 */
	public static function convertDateFormat( $date, $format = '' ) {
		if ( empty( $format ) ) {
			$format = get_option( 'date_format', 'Y-m-d H:i:s' );
		}
		if ( empty( $date ) ) {
			return null;
		}
		$date             = new \DateTime( $date );
		$converted_format = $date->format( $format );
		if ( apply_filters( 'wlmi_translate_display_date', false ) ) {
			$time             = strtotime( $converted_format );
			$converted_format = date_i18n( $format, $time );
		}

		return $converted_format;
	}

	/**
	 * Cleans up HTML content by decoding entities and removing certain tags.
	 *
	 * @param string $html The HTML content to be cleaned.
	 *
	 * @return string Cleaned HTML content with only specified allowed tags.
	 */
	public static function getCleanHtml( $html ) {
		try {
			$html         = html_entity_decode( $html );
			$html         = preg_replace( '/(<(script|style|iframe)\b[^>]*>).*?(<\/\2>)/is', "$1$3", $html );
			$allowed_html = [
				'br'     => [],
				'strong' => [],
				'span'   => [ 'class' => [] ],
				'div'    => [ 'class' => [] ],
				'p'      => [ 'class' => [] ],
				'b'      => [ 'class' => [] ],
				'i'      => [ 'class' => [] ],
			];

			return wp_kses( $html, $allowed_html );
		} catch ( \Exception $e ) {
			return '';
		}
	}

	/**
	 * Get list of files and directories within a specified folder.
	 *
	 * @param string $folder The folder path to retrieve files and directories from.
	 * @param int $levels The maximum levels of subdirectories to traverse.
	 * @param array $exclusions An array of file names to exclude from the result.
	 *
	 * @return array|bool An array containing the list of files and directories, or false if unable to open the folder.
	 */
	public static function getDirFileLists( $folder = '', $levels = 100, $exclusions = array() ) {
		if ( empty( $folder ) ) {
			return false;
		}

		$folder = trailingslashit( $folder );
		if ( ! $levels ) {
			return false;
		}

		$files = array();

		$dir = @opendir( $folder );

		if ( $dir ) {
			while ( ( $file = readdir( $dir ) ) !== false ) {
				// Skip current and parent folder links.
				if ( in_array( $file, array( '.', '..' ), true ) ) {
					continue;
				}

				// Skip hidden and excluded files.
				if ( '.' === $file[0] || in_array( $file, $exclusions, true ) ) {
					continue;
				}

				if ( is_dir( $folder . $file ) ) {
					$files2 = list_files( $folder . $file, $levels - 1 );
					if ( $files2 ) {
						$files = array_merge( $files, $files2 );
					} else {
						$files[] = $folder . $file . '/';
					}
				} else {
					$files[] = $folder . $file;
				}
			}

			closedir( $dir );
		}

		return $files;
	}
}
