<?php

namespace WLMI\App\Helper;

defined( 'ABSPATH' ) || exit;

class File {

	/**
	 * Get WP_Filesystem object.
	 *
	 * @return \WP_Filesystem_Base|false
	 */
	public static function getFilesystem() {
		global $wp_filesystem;

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if ( WP_Filesystem() ) {
			return $wp_filesystem;
		}

		return false;
	}

	/**
	 * Delete a file or directory safely using WP_Filesystem.
	 *
	 * @param string $path Path to file or directory.
	 * @param bool   $recursive Whether to delete recursively if it's a directory.
	 *
	 * @return bool
	 */
	public static function delete( string $path, bool $recursive = false ): bool {
		$wp_fs = self::getFilesystem();
		if ( ! $wp_fs ) {
			return false;
		}

		return $wp_fs->delete( $path, $recursive );
	}

	/**
	 * Write content to a file safely using WP_Filesystem.
	 *
	 * @param string $path Path to file.
	 * @param string $content Content to write.
	 *
	 * @return bool
	 */
	public static function putContent( string $path, string $content ): bool {
		$wp_fs = self::getFilesystem();
		if ( ! $wp_fs ) {
			return false;
		}

		return $wp_fs->put_contents( $path, $content );
	}

	/**
	 * Read content from a file safely using WP_Filesystem.
	 *
	 * @param string $path Path to file.
	 *
	 * @return string|false
	 */
	public static function getContent( string $path ) {
		$wp_fs = self::getFilesystem();
		if ( ! $wp_fs ) {
			return false;
		}

		return $wp_fs->get_contents( $path );
	}

	/**
	 * Check if file or directory exists using WP_Filesystem.
	 *
	 * @param string $path Path.
	 *
	 * @return bool
	 */
	public static function exists( string $path ): bool {
		$wp_fs = self::getFilesystem();
		if ( ! $wp_fs ) {
			return file_exists( $path );
		}

		return $wp_fs->exists( $path );
	}

	/**
	 * Check if path is a directory using WP_Filesystem.
	 *
	 * @param string $path Path.
	 *
	 * @return bool
	 */
	public static function isDir( string $path ): bool {
		$wp_fs = self::getFilesystem();
		if ( ! $wp_fs ) {
			return is_dir( $path );
		}

		return $wp_fs->is_dir( $path );
	}

	/**
	 * Check if path is a file using WP_Filesystem.
	 *
	 * @param string $path Path.
	 *
	 * @return bool
	 */
	public static function isFile( string $path ): bool {
		$wp_fs = self::getFilesystem();
		if ( ! $wp_fs ) {
			return is_file( $path );
		}

		return $wp_fs->is_file( $path );
	}

	/**
	 * Check if file is readable using WP_Filesystem.
	 *
	 * @param string $path Path.
	 *
	 * @return bool
	 */
	public static function isReadable( string $path ): bool {
		$wp_fs = self::getFilesystem();
		if ( ! $wp_fs ) {
			return is_readable( $path );
		}

		return $wp_fs->is_readable( $path );
	}
}
