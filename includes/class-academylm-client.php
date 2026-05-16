<?php
/**
 * AcademyLM client (local CPT + optional external REST).
 *
 * Modes:
 *  - LOCAL  : if MLE_ACADEMYLM_BASE_URL is empty, courses are read from the
 *             local "academy_courses" post type and lessons are inserted as
 *             "academy_lesson" posts (same approach used by Academy LMS).
 *  - REMOTE : if MLE_ACADEMYLM_BASE_URL is set, courses are fetched and
 *             lessons are POSTed to that base. Endpoints can be filtered via
 *             "mle_academylm_endpoint".
 *
 * Constants (override in wp-config.php):
 *  - MLE_ACADEMYLM_BASE_URL    Base URL such as "https://lms.example.com".
 *  - MLE_ACADEMYLM_API_TOKEN   Optional bearer token.
 *
 * @package My_Lesson_Editor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'MLE_ACADEMYLM_BASE_URL' ) ) {
	define( 'MLE_ACADEMYLM_BASE_URL', '' );
}
if ( ! defined( 'MLE_ACADEMYLM_API_TOKEN' ) ) {
	define( 'MLE_ACADEMYLM_API_TOKEN', '' );
}

class MLE_AcademyLM_Client {

	const COURSE_CPT = 'academy_courses';
	const LESSON_CPT = 'academy_lesson';

	/**
	 * Whether the integration is using a remote REST API.
	 *
	 * @return bool
	 */
	public function is_remote() {
		return ! empty( MLE_ACADEMYLM_BASE_URL );
	}

	/**
	 * Build the REST endpoint for a logical action.
	 * Filterable via "mle_academylm_endpoint".
	 *
	 * @param string $action  One of "courses", "create_lesson".
	 * @param array  $context Extra context (e.g. course_id).
	 * @return string Full URL.
	 */
	protected function endpoint( $action, $context = array() ) {
		$base = trailingslashit( (string) MLE_ACADEMYLM_BASE_URL );
		$map  = array(
			'courses'        => $base . 'wp-json/academy-lms/v1/courses',
			'create_lesson'  => $base . 'wp-json/academy-lms/v1/lessons',
		);
		$url  = isset( $map[ $action ] ) ? $map[ $action ] : '';
		return apply_filters( 'mle_academylm_endpoint', $url, $action, $context );
	}

	/**
	 * Default headers for remote calls.
	 *
	 * @return array
	 */
	protected function headers() {
		$headers = array(
			'Accept'       => 'application/json',
			'Content-Type' => 'application/json',
		);
		if ( ! empty( MLE_ACADEMYLM_API_TOKEN ) ) {
			$headers['Authorization'] = 'Bearer ' . MLE_ACADEMYLM_API_TOKEN;
		}
		return apply_filters( 'mle_academylm_headers', $headers );
	}

	/**
	 * Get a list of courses ready for a <select>.
	 *
	 * @return array<int,array{id:int|string,title:string}>
	 */
	public function get_courses() {
		if ( $this->is_remote() ) {
			$response = wp_remote_get(
				$this->endpoint( 'courses' ),
				array(
					'timeout' => 20,
					'headers' => $this->headers(),
				)
			);
			if ( is_wp_error( $response ) ) {
				return array();
			}
			$code = (int) wp_remote_retrieve_response_code( $response );
			if ( $code < 200 || $code >= 300 ) {
				return array();
			}
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( ! is_array( $body ) ) {
				return array();
			}
			$rows = isset( $body['data'] ) && is_array( $body['data'] ) ? $body['data'] : $body;
			$out  = array();
			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$id    = isset( $row['id'] ) ? $row['id'] : ( isset( $row['ID'] ) ? $row['ID'] : '' );
				$title = isset( $row['title'] ) ? $row['title'] : ( isset( $row['name'] ) ? $row['name'] : '' );
				if ( $id === '' || $title === '' ) {
					continue;
				}
				$out[] = array(
					'id'    => is_numeric( $id ) ? (int) $id : sanitize_text_field( (string) $id ),
					'title' => wp_strip_all_tags( (string) $title ),
				);
			}
			return $out;
		}

		// LOCAL: read from CPT "academy_courses" if it exists.
		if ( ! post_type_exists( self::COURSE_CPT ) ) {
			return array();
		}
		$posts = get_posts(
			array(
				'post_type'      => self::COURSE_CPT,
				'posts_per_page' => -1,
				'post_status'    => array( 'publish', 'draft' ),
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		$out = array();
		foreach ( $posts as $p ) {
			$out[] = array(
				'id'    => (int) $p->ID,
				'title' => $p->post_title,
			);
		}
		return $out;
	}

	/**
	 * Create a lesson against AcademyLM.
	 *
	 * @param array $payload {
	 *     @type string $title     Lesson title.
	 *     @type string $content   HTML content.
	 *     @type int|string $course_id Course identifier.
	 * }
	 * @return array|WP_Error On success, an array with at least "lesson_id".
	 */
	public function create_lesson( array $payload ) {
		$title     = isset( $payload['title'] ) ? wp_strip_all_tags( (string) $payload['title'] ) : '';
		$content   = isset( $payload['content'] ) ? (string) $payload['content'] : '';
		$course_id = isset( $payload['course_id'] ) ? $payload['course_id'] : '';

		if ( $title === '' || $content === '' ) {
			return new WP_Error( 'mle_lms_invalid', __( 'Title and content are required.', 'my-lesson-editor' ) );
		}

		if ( $this->is_remote() ) {
			$body = wp_json_encode(
				array(
					'course_id' => $course_id,
					'title'     => $title,
					'content'   => $content,
				)
			);
			$response = wp_remote_post(
				$this->endpoint( 'create_lesson', array( 'course_id' => $course_id ) ),
				array(
					'timeout' => 30,
					'headers' => $this->headers(),
					'body'    => $body,
				)
			);
			if ( is_wp_error( $response ) ) {
				return $response;
			}
			$code = (int) wp_remote_retrieve_response_code( $response );
			$raw  = wp_remote_retrieve_body( $response );
			if ( $code < 200 || $code >= 300 ) {
				return new WP_Error( 'mle_lms_http', sprintf( 'HTTP %d: %s', $code, substr( $raw, 0, 300 ) ) );
			}
			$decoded = json_decode( $raw, true );
			if ( ! is_array( $decoded ) ) {
				return new WP_Error( 'mle_lms_decode', __( 'Invalid JSON response from AcademyLM.', 'my-lesson-editor' ) );
			}
			$lesson_id = isset( $decoded['lesson_id'] )
				? $decoded['lesson_id']
				: ( isset( $decoded['id'] ) ? $decoded['id'] : 0 );
			return array(
				'lesson_id' => $lesson_id,
				'remote'    => true,
				'response'  => $decoded,
			);
		}

		// LOCAL: insert as academy_lesson and link to course via meta.
		if ( ! post_type_exists( self::LESSON_CPT ) ) {
			return new WP_Error( 'mle_lms_missing', __( 'AcademyLMS lesson post type not found. Activate AcademyLMS or define MLE_ACADEMYLM_BASE_URL.', 'my-lesson-editor' ) );
		}
		$lesson_id = wp_insert_post(
			array(
				'post_title'   => $title,
				'post_content' => wp_kses_post( $content ),
				'post_status'  => 'publish',
				'post_type'    => self::LESSON_CPT,
				'post_author'  => get_current_user_id(),
			),
			true
		);
		if ( is_wp_error( $lesson_id ) ) {
			return $lesson_id;
		}
		if ( $course_id ) {
			update_post_meta( $lesson_id, '_academy_course_id', is_numeric( $course_id ) ? (int) $course_id : sanitize_text_field( (string) $course_id ) );
		}
		return array(
			'lesson_id' => (int) $lesson_id,
			'remote'    => false,
		);
	}

	/**
	 * Extract plain text from an uploaded file (TXT/DOCX/PDF/DOC, best effort).
	 *
	 * @param string $tmp_path  Server-side temp path.
	 * @param string $filename  Original file name (used for extension hint).
	 * @return string|WP_Error Extracted text on success.
	 */
	public function extract_text( $tmp_path, $filename ) {
		if ( ! is_string( $tmp_path ) || ! file_exists( $tmp_path ) || ! is_readable( $tmp_path ) ) {
			return new WP_Error( 'mle_extract_unreadable', __( 'Uploaded file is not readable.', 'my-lesson-editor' ) );
		}
		$ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

		if ( $ext === 'txt' || $ext === 'md' || $ext === 'csv' ) {
			$raw = file_get_contents( $tmp_path );
			if ( $raw === false ) {
				return new WP_Error( 'mle_extract_read', __( 'Could not read text file.', 'my-lesson-editor' ) );
			}
			return $this->normalize_text( $raw );
		}

		if ( $ext === 'docx' ) {
			$text = $this->extract_docx( $tmp_path );
			if ( is_wp_error( $text ) ) {
				return $text;
			}
			return $this->normalize_text( $text );
		}

		// PDF / DOC fallback: scan printable strings (best effort, no external lib).
		$raw = @file_get_contents( $tmp_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( $raw === false ) {
			return new WP_Error( 'mle_extract_read', __( 'Could not read uploaded file.', 'my-lesson-editor' ) );
		}
		$cleaned = preg_replace( '/[^\P{C}\n\r\t\x20-\x7E\xA0-\x{10FFFF}]+/u', ' ', $raw );
		$cleaned = preg_replace( '/\s{2,}/u', ' ', (string) $cleaned );
		return trim( (string) $cleaned );
	}

	/**
	 * Best-effort extractor for DOCX files (zip with word/document.xml).
	 *
	 * @param string $path
	 * @return string|WP_Error
	 */
	protected function extract_docx( $path ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'mle_extract_zip', __( 'ZipArchive extension is required to read DOCX.', 'my-lesson-editor' ) );
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $path ) ) {
			return new WP_Error( 'mle_extract_zip', __( 'Could not open DOCX archive.', 'my-lesson-editor' ) );
		}
		$xml = $zip->getFromName( 'word/document.xml' );
		$zip->close();
		if ( ! is_string( $xml ) || $xml === '' ) {
			return new WP_Error( 'mle_extract_xml', __( 'DOCX is missing word/document.xml.', 'my-lesson-editor' ) );
		}
		// Convert paragraph and break tags to newlines, then strip.
		$text = preg_replace( '/<w:p[^>]*>/u', "\n", $xml );
		$text = preg_replace( '/<w:br\s*\/>/u', "\n", (string) $text );
		$text = wp_strip_all_tags( (string) $text );
		return html_entity_decode( (string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}

	/**
	 * Collapse whitespace and trim to a sane size.
	 *
	 * @param string $raw
	 * @return string
	 */
	protected function normalize_text( $raw ) {
		$text = (string) $raw;
		$text = str_replace( array( "\r\n", "\r" ), "\n", $text );
		$text = preg_replace( "/\n{3,}/u", "\n\n", $text );
		$text = preg_replace( '/[ \t]{2,}/u', ' ', (string) $text );
		return trim( (string) $text );
	}
}
