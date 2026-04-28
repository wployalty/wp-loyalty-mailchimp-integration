<?php

namespace WLMI\App\Helper;

defined( 'ABSPATH' ) || exit;

class File {
	/**
	 * Ensure directory exists (recursive).
	 *
	 * @param string $dir Directory path.
	 *
	 * @return bool
	 */
	public static function ensureDir( string $dir ): bool {
		if ( empty( $dir ) ) {
			return false;
		}

		if ( self::exists( $dir ) && self::isDir( $dir ) ) {
			return true;
		}

		return wp_mkdir_p( $dir );
	}

	/**
	 * Check if path is writable using WP_Filesystem when possible.
	 *
	 * @param string $path
	 *
	 * @return bool
	 */
	public static function isWritable( string $path ): bool {
		$wp_fs = self::getFilesystem();
		if ( ! $wp_fs ) {
			return is_writable( $path );
		}

		return $wp_fs->is_writable( $path );
	}

	/**
	 * Set file/folder permissions recursively (mirrors wp-loyalty-rules CsvHelper approach).
	 *
	 * @param string $path
	 * @param string $filemode
	 * @param string $foldermode
	 *
	 * @return bool
	 */
	public static function setPermissions( string $path, string $filemode = '0644', string $foldermode = '0755' ): bool {
		$wp_fs = self::getFilesystem();
		if ( ! $wp_fs ) {
			return false;
		}

		$ret = true;
		if ( is_dir( $path ) ) {
			$dh = @opendir( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( $dh === false ) {
				return false;
			}

			while ( ( $file = readdir( $dh ) ) !== false ) {
				if ( $file === '.' || $file === '..' ) {
					continue;
				}
				$fullpath = rtrim( $path, '/' ) . '/' . $file;
				if ( is_dir( $fullpath ) ) {
					if ( ! self::setPermissions( $fullpath, $filemode, $foldermode ) ) {
						$ret = false;
					}
				} else {
					if ( ! $wp_fs->chmod( $fullpath, octdec( $filemode ) ) ) {
						$ret = false;
					}
				}
			}

			closedir( $dh );

			if ( ! $wp_fs->chmod( $path, octdec( $foldermode ) ) ) {
				$ret = false;
			}
		} else {
			$ret = $wp_fs->chmod( $path, octdec( $filemode ) );
		}

		return $ret;
	}

	/**
	 * Move a file safely using WP_Filesystem and then fix permissions.
	 *
	 * @param string $src
	 * @param string $dest
	 *
	 * @return bool
	 */
	public static function move( string $src, string $dest ): bool {
		$wp_fs = self::getFilesystem();
		if ( ! $wp_fs ) {
			return false;
		}

		$base_dir = dirname( $dest );
		if ( ! $wp_fs->is_writable( $base_dir ) ) {
			return false;
		}

		$ok = $wp_fs->move( $src, $dest, true );
		if ( ! $ok ) {
			return false;
		}

		return self::setPermissions( $dest );
	}

	/**
	 * Delete a file after attempting to chmod it (mirrors wp-loyalty-rules CsvHelper approach).
	 *
	 * @param string $path
	 *
	 * @return bool
	 */
	public static function deleteWithPerms( string $path ): bool {
		$wp_fs = self::getFilesystem();
		if ( ! $wp_fs ) {
			return false;
		}

		// Best effort; some hosts will block chmod.
		$wp_fs->chmod( $path, 0777 );

		return self::delete( $path );
	}

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
