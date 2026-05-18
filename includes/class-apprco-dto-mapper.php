<?php
/**
 * DTO Mapper — API response → CPT / vacancy store
 *
 * Maps raw API item arrays to structured CPT data using configurable field
 * mapping rules. Each rule supports a source path (dot-notation, wildcard
 * array joins), a target type (post_field|meta|taxonomy|store), and an
 * optional PHP transform expression evaluated in a sandboxed context.
 *
 * Transform expressions
 * ─────────────────────
 * Expressions are small PHP snippets with two variable conventions:
 *   {value}       – the resolved source field value
 *   {fieldName}   – any other field from the raw item (camelCase API names)
 *
 * Examples:
 *   strtolower( {value} )
 *   date( 'Y-m-d', strtotime( {value} ) )
 *   {title} . ' — ' . {employerName}
 *   number_format( {wageAmount}, 2 )
 *   empty( {value} ) ? 'Not specified' : {value}
 *
 * Only functions in the ALLOWED_FUNCTIONS whitelist are permitted.
 * The expression is validated before execution; invalid transforms fall
 * back to the raw value and log a notice.
 *
 * @package ApprenticeshipConnect
 */

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class Apprco_DTO_Mapper
 */
class Apprco_DTO_Mapper {

	/**
	 * PHP functions allowed inside transform expressions.
	 *
	 * @var string[]
	 */
	private const ALLOWED_FUNCTIONS = array(
		// String
		'strtolower', 'strtoupper', 'ucfirst', 'ucwords', 'trim', 'ltrim', 'rtrim',
		'strlen', 'substr', 'str_replace', 'str_pad', 'str_repeat', 'str_word_count',
		'sprintf', 'number_format', 'nl2br', 'wordwrap', 'strip_tags', 'htmlspecialchars',
		'htmlspecialchars_decode', 'html_entity_decode', 'addslashes', 'stripslashes',
		'implode', 'explode', 'join', 'split', 'preg_replace',
		// Type
		'intval', 'floatval', 'strval', 'boolval', 'is_numeric', 'is_string',
		'is_array', 'is_null', 'is_empty', 'empty', 'isset',
		// Date/time
		'date', 'strtotime', 'mktime', 'time',
		// Math
		'abs', 'ceil', 'floor', 'round', 'min', 'max', 'pow', 'sqrt', 'fmod',
		// Array
		'count', 'array_map', 'array_filter', 'array_values', 'array_keys',
		'array_unique', 'array_merge', 'array_slice', 'in_array', 'array_pop',
		'array_shift', 'array_push', 'implode',
		// WP safe
		'wp_kses_post', 'wp_strip_all_tags', 'sanitize_text_field',
		'sanitize_email', 'sanitize_url', 'esc_html', 'esc_attr', 'esc_url',
		'get_permalink', 'wp_slash', 'wp_unslash',
	);

	/**
	 * Default field mappings for UK Gov Display Advert API v2.
	 * Used when a task has no explicit field_mappings saved.
	 *
	 * @return array[]
	 */
	public static function default_mappings(): array {
		return array(
			// ── Post fields ───────────────────────────────────────────────
			array(
				'source'      => 'title',
				'target_type' => 'post_field',
				'target'      => 'post_title',
				'transform'   => '',
			),
			array(
				'source'      => 'fullDescription',
				'target_type' => 'post_field',
				'target'      => 'post_content',
				'transform'   => 'wp_kses_post( {value} )',
			),
			// ── Core meta ─────────────────────────────────────────────────
			array(
				'source'      => 'vacancyReference',
				'target_type' => 'meta',
				'target'      => '_apprco_vacancy_reference',
				'transform'   => '',
			),
			array(
				'source'      => 'employerName',
				'target_type' => 'meta',
				'target'      => '_apprco_employer_name',
				'transform'   => '',
			),
			array(
				'source'      => 'employerWebsite',
				'target_type' => 'meta',
				'target'      => '_apprco_employer_website',
				'transform'   => 'esc_url( {value} )',
			),
			array(
				'source'      => 'vacancyUrl',
				'target_type' => 'meta',
				'target'      => '_apprco_vacancy_url',
				'transform'   => 'esc_url( {value} )',
			),
			array(
				'source'      => 'closingDate',
				'target_type' => 'meta',
				'target'      => '_apprco_closing_date',
				'transform'   => "!empty({value}) ? date('Y-m-d', strtotime({value})) : ''",
			),
			array(
				'source'      => 'postedDate',
				'target_type' => 'meta',
				'target'      => '_apprco_posted_date',
				'transform'   => "!empty({value}) ? date('Y-m-d', strtotime({value})) : ''",
			),
			array(
				'source'      => 'addresses.0.postcode',
				'target_type' => 'meta',
				'target'      => '_apprco_postcode',
				'transform'   => 'strtoupper( trim( {value} ) )',
			),
			array(
				'source'      => 'addresses.0.town',
				'target_type' => 'meta',
				'target'      => '_apprco_town',
				'transform'   => '',
			),
			array(
				'source'      => 'wageText',
				'target_type' => 'meta',
				'target'      => '_apprco_wage_text',
				'transform'   => '',
			),
			array(
				'source'      => 'wageAmount',
				'target_type' => 'meta',
				'target'      => '_apprco_wage_amount',
				'transform'   => 'floatval( {value} )',
			),
			array(
				'source'      => 'workingWeek',
				'target_type' => 'meta',
				'target'      => '_apprco_working_week',
				'transform'   => '',
			),
			array(
				'source'      => 'expectedDuration',
				'target_type' => 'meta',
				'target'      => '_apprco_duration',
				'transform'   => '',
			),
			array(
				'source'      => 'numberOfPositions',
				'target_type' => 'meta',
				'target'      => '_apprco_positions',
				'transform'   => 'intval( {value} )',
			),
			array(
				'source'      => 'providerUkprn',
				'target_type' => 'meta',
				'target'      => '_apprco_provider_ukprn',
				'transform'   => 'intval( {value} )',
			),
			array(
				'source'      => 'providerName',
				'target_type' => 'meta',
				'target'      => '_apprco_provider_name',
				'transform'   => '',
			),
			// ── Taxonomies ─────────────────────────────────────────────────
			array(
				'source'      => 'apprenticeshipLevel',
				'target_type' => 'taxonomy',
				'target'      => 'apprco_level',
				'transform'   => '',
			),
			array(
				'source'      => 'route',
				'target_type' => 'taxonomy',
				'target'      => 'apprco_route',
				'transform'   => '',
			),
			// ── Vacancy store columns (direct DB storage) ─────────────────
			array(
				'source'      => 'vacancyReference',
				'target_type' => 'store',
				'target'      => 'vacancy_reference',
				'transform'   => '',
			),
			array(
				'source'      => 'title',
				'target_type' => 'store',
				'target'      => 'title',
				'transform'   => '',
			),
			array(
				'source'      => 'employerName',
				'target_type' => 'store',
				'target'      => 'employer_name',
				'transform'   => '',
			),
		);
	}

	/**
	 * Map a raw API item to structured CPT/store data.
	 *
	 * @param array $item     Raw API item.
	 * @param array $mappings Array of mapping rule arrays (from task config).
	 *                        If empty, default_mappings() is used.
	 * @return array {
	 *   @type array  post_data  Keys: post_title, post_content, post_excerpt, post_status, post_date.
	 *   @type array  meta       Flat key→value pairs for update_post_meta.
	 *   @type array  taxonomies Slug→array-of-terms pairs for wp_set_post_terms.
	 *   @type array  store      Flat key→value pairs for Apprco_Vacancy_Store::upsert.
	 * }
	 */
	public function map( array $item, array $mappings = array() ): array {
		if ( empty( $mappings ) ) {
			$mappings = self::default_mappings();
		}

		$result = array(
			'post_data'  => array(),
			'meta'       => array(),
			'taxonomies' => array(),
			'store'      => array(),
		);

		foreach ( $mappings as $rule ) {
			if ( empty( $rule['source'] ) || empty( $rule['target'] ) ) {
				continue;
			}

			$value = $this->resolve_path( $item, $rule['source'] );

			if ( ! empty( $rule['transform'] ) ) {
				$value = $this->apply_transform( $value, $rule['transform'], $item );
			}

			$target_type = $rule['target_type'] ?? 'meta';
			$target      = $rule['target'];

			switch ( $target_type ) {
				case 'post_field':
					$result['post_data'][ $target ] = $value;
					break;
				case 'taxonomy':
					if ( ! isset( $result['taxonomies'][ $target ] ) ) {
						$result['taxonomies'][ $target ] = array();
					}
					if ( ! empty( $value ) ) {
						$result['taxonomies'][ $target ][] = (string) $value;
					}
					break;
				case 'store':
					$result['store'][ $target ] = $value;
					break;
				case 'meta':
				default:
					$result['meta'][ $target ] = $value;
					break;
			}
		}

		// Always store the full raw item.
		$result['meta']['_apprco_raw_data'] = $item;

		return $result;
	}

	/**
	 * Resolve a dot-notation path from an array.
	 *
	 * Supports:
	 *   - Simple keys:           'title'
	 *   - Nested:                'addresses.0.postcode'
	 *   - Wildcard array join:   'addresses.*.postcode'  → joined with ', '
	 *
	 * @param array  $data Raw item.
	 * @param string $path Dot-notation path.
	 * @return mixed
	 */
	public function resolve_path( array $data, string $path ) {
		$parts = explode( '.', $path );
		return $this->walk_path( $data, $parts );
	}

	/**
	 * Recursively walk a path array.
	 *
	 * @param mixed    $data  Current node.
	 * @param string[] $parts Remaining path parts.
	 * @return mixed
	 */
	private function walk_path( $data, array $parts ) {
		if ( empty( $parts ) ) {
			return $data;
		}

		$key  = array_shift( $parts );
		$rest = $parts;

		// Wildcard: join all matching children.
		if ( '*' === $key ) {
			if ( ! is_array( $data ) ) {
				return null;
			}
			$collected = array();
			foreach ( $data as $child ) {
				$val = empty( $rest ) ? $child : $this->walk_path( $child, $rest );
				if ( null !== $val && '' !== $val ) {
					$collected[] = $val;
				}
			}
			return implode( ', ', $collected );
		}

		if ( is_array( $data ) && array_key_exists( $key, $data ) ) {
			return empty( $rest ) ? $data[ $key ] : $this->walk_path( $data[ $key ], $rest );
		}

		return null;
	}

	/**
	 * Apply a transform expression to a value.
	 *
	 * Variable placeholders in the expression:
	 *   {value}      → the resolved source value (PHP-encoded for safe eval)
	 *   {fieldName}  → any top-level field of $raw_item
	 *
	 * @param mixed  $value      The resolved source value.
	 * @param string $expression The transform expression string.
	 * @param array  $raw_item   Full raw API item for cross-field references.
	 * @return mixed Transformed value, or original value on error.
	 */
	public function apply_transform( $value, string $expression, array $raw_item = array() ) {
		if ( empty( trim( $expression ) ) ) {
			return $value;
		}

		if ( ! $this->is_safe_expression( $expression ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
			trigger_error( 'Apprco_DTO_Mapper: blocked unsafe transform: ' . esc_html( $expression ), E_USER_NOTICE );
			return $value;
		}

		// Build substitution map: {value} + {fieldName} for every top-level key.
		$substitutions = array( '{value}' => $value );
		foreach ( $raw_item as $k => $v ) {
			if ( is_scalar( $v ) || null === $v ) {
				$substitutions[ '{' . $k . '}' ] = $v;
			}
		}

		// Replace placeholders with var_export'd PHP literals so the eval
		// receives valid PHP syntax regardless of value type.
		$code = $expression;
		foreach ( $substitutions as $placeholder => $sub_val ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
			$literal = var_export( $sub_val, true );
			$code    = str_replace( $placeholder, $literal, $code );
		}

		try {
			// phpcs:ignore Squiz.PHP.Eval.Discouraged
			$result = eval( 'return ' . $code . ';' );
			return $result;
		} catch ( \Throwable $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
			trigger_error( 'Apprco_DTO_Mapper: transform eval failed: ' . $e->getMessage(), E_USER_NOTICE );
			return $value;
		}
	}

	/**
	 * Validate that an expression only calls whitelisted functions.
	 *
	 * Uses a regex to extract all function-call identifiers and checks
	 * each against the whitelist. Also blocks dangerous PHP constructs.
	 *
	 * @param string $expression The expression to validate.
	 * @return bool True if safe.
	 */
	public function is_safe_expression( string $expression ): bool {
		// Block dangerous constructs outright.
		$blocked_patterns = array(
			'/\beval\b/',
			'/\bexec\b/',
			'/\bsystem\b/',
			'/\bpassthru\b/',
			'/\bshell_exec\b/',
			'/\bproc_open\b/',
			'/\bpopen\b/',
			'/\bfile_get_contents\b/',
			'/\bfile_put_contents\b/',
			'/\bfopen\b/',
			'/\bunlink\b/',
			'/\brm\b/',
			'/\bmysql\b/',
			'/\$_/',             // superglobals
			'/\binclude\b/',
			'/\brequire\b/',
			'/\bbase64_decode\b/',
			'/\bcreate_function\b/',
			'/`/',              // backtick operator
		);
		foreach ( $blocked_patterns as $pattern ) {
			if ( preg_match( $pattern, $expression ) ) {
				return false;
			}
		}

		// Extract all function-call identifiers (word followed by opening paren).
		preg_match_all( '/\b([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/', $expression, $matches );
		$called_functions = array_unique( $matches[1] );

		foreach ( $called_functions as $fn ) {
			if ( ! in_array( $fn, self::ALLOWED_FUNCTIONS, true ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Validate a set of mapping rules for completeness and safe transforms.
	 *
	 * @param array $mappings Array of mapping rule arrays.
	 * @return array { 'valid' => bool, 'errors' => string[] }
	 */
	public function validate_mappings( array $mappings ): array {
		$errors = array();
		foreach ( $mappings as $i => $rule ) {
			$label = '#' . ( $i + 1 );
			if ( empty( $rule['source'] ) ) {
				$errors[] = "Rule {$label}: missing source path.";
			}
			if ( empty( $rule['target'] ) ) {
				$errors[] = "Rule {$label}: missing target.";
			}
			if ( ! empty( $rule['transform'] ) && ! $this->is_safe_expression( $rule['transform'] ) ) {
				$errors[] = "Rule {$label}: transform expression contains disallowed functions or constructs.";
			}
		}
		return array(
			'valid'  => empty( $errors ),
			'errors' => $errors,
		);
	}
}
