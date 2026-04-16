<?php
/**
 * Media importer – downloads remote images and attaches them to vacancy posts.
 *
 * Currently handles:
 *  - Employer logo  (_appcon_employer_logo_url → post thumbnail)
 *  - Training provider logo (_appcon_training_provider_logo_url → meta)
 *
 * Images are deduped by URL: if an attachment already exists for a given URL
 * (tracked in post-meta `_appcon_image_source_url`) it is reused rather than
 * re-downloaded.
 *
 * Requires the WP file and image handling functions (wp-admin/includes/media.php,
 * wp-admin/includes/file.php, wp-admin/includes/image.php).
 *
 * @package ApprenticeshipConnector\Import
 */

namespace ApprenticeshipConnector\Import;

class MediaImporter {

	/** Meta key used to track the original remote URL of an attachment. */
	private const SOURCE_URL_META = '_appcon_image_source_url';

	// ── Public API ─────────────────────────────────────────────────────────

	/**
	 * Sideload the employer logo for a vacancy and set it as the featured image.
	 *
	 * @param int    $post_id    WP post ID of the vacancy.
	 * @param string $logo_url   Remote URL of the employer logo.
	 * @param string $post_title Post title (used as attachment title / alt text).
	 * @return int|null Attachment ID or null on failure.
	 */
	public static function import_employer_logo( int $post_id, string $logo_url, string $post_title = '' ): ?int {
		if ( ! $logo_url || ! filter_var( $logo_url, FILTER_VALIDATE_URL ) ) {
			return null;
		}

		$attachment_id = self::get_or_sideload( $logo_url, $post_id, $post_title );

		if ( $attachment_id ) {
			set_post_thumbnail( $post_id, $attachment_id );
			update_post_meta( $post_id, '_appcon_employer_logo_attachment_id', $attachment_id );
		}

		return $attachment_id;
	}

	/**
	 * Sideload the training-provider logo and store the attachment ID in meta
	 * (does NOT set it as the featured image).
	 *
	 * @param int    $post_id  WP post ID of the vacancy.
	 * @param string $logo_url Remote URL of the provider logo.
	 * @return int|null Attachment ID or null on failure.
	 */
	public static function import_provider_logo( int $post_id, string $logo_url ): ?int {
		if ( ! $logo_url || ! filter_var( $logo_url, FILTER_VALIDATE_URL ) ) {
			return null;
		}

		$attachment_id = self::get_or_sideload( $logo_url, $post_id );

		if ( $attachment_id ) {
			update_post_meta( $post_id, '_appcon_provider_logo_attachment_id', $attachment_id );
		}

		return $attachment_id;
	}

	// ── Internals ──────────────────────────────────────────────────────────

	/**
	 * Return an existing attachment for this URL or sideload a new one.
	 *
	 * @param  string $url       Remote image URL.
	 * @param  int    $parent_id Post to attach the file to.
	 * @param  string $title     Optional title / alt text for the attachment.
	 * @return int|null Attachment ID or null.
	 */
	private static function get_or_sideload( string $url, int $parent_id, string $title = '' ): ?int {
		// ── Dedup: check if we already have this URL ──────────────────────
		global $wpdb;

		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta}
			 WHERE meta_key   = %s
			   AND meta_value = %s
			 LIMIT 1",
			self::SOURCE_URL_META,
			$url
		) );

		if ( $existing ) {
			return (int) $existing;
		}

		// ── Sideload ──────────────────────────────────────────────────────
		self::require_wp_media_functions();

		$tmp = download_url( $url );
		if ( is_wp_error( $tmp ) ) {
			return null;
		}

		$file_array = [
			'name'     => self::filename_from_url( $url ),
			'tmp_name' => $tmp,
		];

		$attachment_id = media_handle_sideload( $file_array, $parent_id, $title ?: $url );

		// Clean up temp file even on failure.
		if ( file_exists( $tmp ) ) {
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}

		if ( is_wp_error( $attachment_id ) ) {
			return null;
		}

		// Record the source URL for future dedup.
		update_post_meta( $attachment_id, self::SOURCE_URL_META, $url );

		return (int) $attachment_id;
	}

	/**
	 * Derive a safe filename from a URL.
	 *
	 * @param  string $url Remote URL.
	 * @return string Filename (e.g. "employer-logo-abc123.jpg").
	 */
	private static function filename_from_url( string $url ): string {
		$path     = wp_parse_url( $url, PHP_URL_PATH ) ?? '';
		$basename = sanitize_file_name( basename( $path ) );

		// Ensure we have a safe, non-empty filename.
		if ( ! $basename || strpos( $basename, '.' ) === false ) {
			$ext      = pathinfo( $path, PATHINFO_EXTENSION ) ?: 'jpg';
			$basename = 'appcon-image-' . substr( md5( $url ), 0, 8 ) . '.' . $ext;
		}

		return $basename;
	}

	/**
	 * Lazy-load the WordPress file and media utility functions.
	 */
	private static function require_wp_media_functions(): void {
		if ( ! function_exists( 'media_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
		}
	}
}
