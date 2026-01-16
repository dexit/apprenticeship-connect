<?php
/**
 * UK Government Apprenticeships Provider
 *
 * Provider implementation for the UK Government Display Advert API v2.
 * Base URL: https://api.apprenticeships.education.gov.uk/vacancies
 *
 * @package ApprenticeshipConnect
 * @since   2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Apprco_UK_Gov_Provider
 *
 * Implements the vacancy provider interface for the UK Government
 * Display Advert API (Apprenticeships).
 */
class Apprco_UK_Gov_Provider extends Apprco_Abstract_Provider {

    /**
     * Provider ID
     *
     * @var string
     */
    public const PROVIDER_ID = 'uk-gov-apprenticeships';

    /**
     * API Version
     *
     * @var string
     */
    public const API_VERSION = '2';

    /**
     * Production base URL
     *
     * @var string
     */
    public const BASE_URL = 'https://api.apprenticeships.education.gov.uk/vacancies';

    /**
     * Supported endpoints
     *
     * @var array
     */
    public const ENDPOINTS = array(
        'vacancy'                    => '/vacancy',
        'vacancy_single'             => '/vacancy/{ref}',
        'referencedata_courses'      => '/referencedata/courses',
        'referencedata_routes'       => '/referencedata/courses/routes',
        'account_legal_entities'     => '/accountlegalentities',
    );

    /**
     * Rate limits for this provider
     *
     * @var array
     */
    protected $rate_limits = array(
        'requests_per_minute' => 60,
        'delay_ms'            => 250,
    );

    /**
     * Get unique provider identifier
     *
     * @return string
     */
    public function get_id(): string {
        return self::PROVIDER_ID;
    }

    /**
     * Get human-readable provider name
     *
     * @return string
     */
    public function get_name(): string {
        return 'UK Government Apprenticeships API';
    }

    /**
     * Get provider description
     *
     * @return string
     */
    public function get_description(): string {
        return 'Official UK Government Display Advert API v2 for apprenticeship vacancies. Provides access to all apprenticeship listings in England.';
    }

    /**
     * Get base API URL
     *
     * @return string
     */
    public function get_base_url(): string {
        return $this->get_config_value( 'base_url', self::BASE_URL );
    }

    /**
     * Get supported endpoints
     *
     * @return array
     */
    public function get_supported_endpoints(): array {
        return array_keys( self::ENDPOINTS );
    }

    /**
     * Get provider configuration schema
     *
     * @return array
     */
    public function get_config_schema(): array {
        return array(
            'subscription_key' => array(
                'type'        => 'string',
                'label'       => 'Subscription Key',
                'description' => 'Ocp-Apim-Subscription-Key from API Management Portal',
                'required'    => true,
                'default'     => '',
            ),
            'base_url'         => array(
                'type'        => 'url',
                'label'       => 'Base URL',
                'description' => 'API base URL (production or sandbox)',
                'required'    => false,
                'default'     => self::BASE_URL,
            ),
            'ukprn'            => array(
                'type'        => 'string',
                'label'       => 'UKPRN',
                'description' => 'UK Provider Reference Number for filtering',
                'required'    => false,
                'default'     => '',
            ),
            'page_size'        => array(
                'type'        => 'integer',
                'label'       => 'Page Size',
                'description' => 'Number of results per page (max 100)',
                'required'    => false,
                'default'     => 100,
            ),
        );
    }

    /**
     * Initialize API client with provider-specific headers
     */
    private function ensure_api_client(): void {
        if ( null === $this->api_client ) {
            $this->api_client = new Apprco_API_Client(
                $this->get_base_url(),
                array(
                    'rate_limit_delay_ms' => $this->rate_limits['delay_ms'],
                ),
                $this->logger
            );
        }

        // Set authentication headers
        $this->api_client->set_default_headers( array(
            'X-Version'                 => self::API_VERSION,
            'Ocp-Apim-Subscription-Key' => $this->get_config_value( 'subscription_key', '' ),
            'Accept'                    => 'application/json',
        ) );
    }

    /**
     * Test connection to the provider's API
     *
     * @return array{success: bool, message: string, data?: array}
     */
    public function test_connection(): array {
        if ( ! $this->is_configured() ) {
            return array(
                'success' => false,
                'message' => 'Provider not configured. Please set the Subscription Key.',
            );
        }

        $this->ensure_api_client();

        $params = array(
            'PageNumber' => 1,
            'PageSize'   => 10,
            'Sort'       => 'AgeDesc',
        );

        // Add UKPRN filter if configured
        $ukprn = $this->get_config_value( 'ukprn' );
        if ( ! empty( $ukprn ) ) {
            $params['Ukprn']                = (int) $ukprn;
            $params['FilterBySubscription'] = 'true';
        }

        $this->log( 'info', 'Testing connection to UK Gov Apprenticeships API...', 'api' );

        $result = $this->api_client->get( self::ENDPOINTS['vacancy'], $params );

        if ( ! $result['success'] ) {
            $this->log( 'error', 'Connection test failed: ' . ( $result['error'] ?? 'Unknown error' ), 'api' );

            return array(
                'success' => false,
                'message' => 'Connection failed: ' . ( $result['error'] ?? 'Unknown error' ),
            );
        }

        $data = $result['data'];

        // Extract response info per v2 OpenAPI spec
        $total          = $data['total'] ?? 0;
        $total_filtered = $data['totalFiltered'] ?? $total;
        $total_pages    = $data['totalPages'] ?? 0;
        $vacancies      = isset( $data['vacancies'] ) ? $data['vacancies'] : array();

        $this->log( 'info', sprintf(
            'Connection successful: %d total vacancies, %d pages',
            $total,
            $total_pages
        ), 'api' );

        return array(
            'success' => true,
            'message' => sprintf(
                'Connection successful! Found %s vacancies (%s filtered, %d pages)',
                number_format( $total ),
                number_format( $total_filtered ),
                $total_pages
            ),
            'data'    => array(
                'total'          => $total,
                'total_filtered' => $total_filtered,
                'total_pages'    => $total_pages,
                'sample_count'   => count( $vacancies ),
                'sample'         => array_slice( $vacancies, 0, 3 ),
            ),
        );
    }

    /**
     * Fetch vacancies from the provider
     *
     * @param array $params Query parameters.
     * @return array{success: bool, vacancies?: array, total?: int, total_pages?: int, error?: string}
     */
    public function fetch_vacancies( array $params = array() ): array {
        if ( ! $this->is_configured() ) {
            return array(
                'success' => false,
                'error'   => 'Provider not configured.',
            );
        }

        $this->ensure_api_client();

        // Build default params per v2 OpenAPI spec
        $default_params = array(
            'PageSize' => $this->get_config_value( 'page_size', 100 ),
            'Sort'     => 'AgeDesc',
        );

        // Add UKPRN filter if configured
        $ukprn = $this->get_config_value( 'ukprn' );
        if ( ! empty( $ukprn ) ) {
            $default_params['Ukprn']                = (int) $ukprn;
            $default_params['FilterBySubscription'] = 'true';
        }

        $params = array_merge( $default_params, $params );

        $this->log( 'info', 'Fetching vacancies from UK Gov API...', 'provider', array(
            'page_size' => $params['PageSize'],
            'ukprn'     => $ukprn ?: 'not set',
        ) );

        // Check if fetching all pages or single page
        $fetch_all = ! isset( $params['PageNumber'] );

        if ( $fetch_all ) {
            $result = $this->api_client->fetch_all_pages(
                self::ENDPOINTS['vacancy'],
                $params,
                'PageNumber',
                'vacancies',
                'totalPages',
                500
            );

            if ( ! $result['success'] ) {
                return array(
                    'success' => false,
                    'error'   => $result['error'] ?? 'Failed to fetch vacancies',
                );
            }

            return array(
                'success'       => true,
                'vacancies'     => $result['items'],
                'total'         => $result['total'],
                'pages_fetched' => $result['pages_fetched'],
            );
        } else {
            // Single page fetch
            $result = $this->api_client->get( self::ENDPOINTS['vacancy'], $params );

            if ( ! $result['success'] ) {
                return array(
                    'success' => false,
                    'error'   => $result['error'] ?? 'Failed to fetch vacancies',
                );
            }

            $data = $result['data'];

            return array(
                'success'     => true,
                'vacancies'   => $data['vacancies'] ?? array(),
                'total'       => $data['total'] ?? 0,
                'total_pages' => $data['totalPages'] ?? 0,
            );
        }
    }

    /**
     * Fetch a single vacancy by reference
     *
     * @param string $reference Vacancy reference.
     * @return array{success: bool, vacancy?: array, error?: string}
     */
    public function fetch_vacancy( string $reference ): array {
        if ( ! $this->is_configured() ) {
            return array(
                'success' => false,
                'error'   => 'Provider not configured.',
            );
        }

        $this->ensure_api_client();

        $endpoint = str_replace( '{ref}', $reference, self::ENDPOINTS['vacancy_single'] );

        $this->log( 'debug', sprintf( 'Fetching vacancy: %s', $reference ), 'provider' );

        $result = $this->api_client->get( $endpoint );

        if ( ! $result['success'] ) {
            return array(
                'success' => false,
                'error'   => $result['error'] ?? 'Failed to fetch vacancy',
            );
        }

        return array(
            'success' => true,
            'vacancy' => $result['data'],
        );
    }

    /**
     * Fetch reference data - courses
     *
     * @return array{success: bool, courses?: array, error?: string}
     */
    public function fetch_courses(): array {
        $this->ensure_api_client();

        $result = $this->api_client->get( self::ENDPOINTS['referencedata_courses'] );

        if ( ! $result['success'] ) {
            return array(
                'success' => false,
                'error'   => $result['error'] ?? 'Failed to fetch courses',
            );
        }

        return array(
            'success' => true,
            'courses' => $result['data'],
        );
    }

    /**
     * Fetch reference data - course routes
     *
     * @return array{success: bool, routes?: array, error?: string}
     */
    public function fetch_course_routes(): array {
        $this->ensure_api_client();

        $result = $this->api_client->get( self::ENDPOINTS['referencedata_routes'] );

        if ( ! $result['success'] ) {
            return array(
                'success' => false,
                'error'   => $result['error'] ?? 'Failed to fetch routes',
            );
        }

        return array(
            'success' => true,
            'routes'  => $result['data'],
        );
    }

    /**
     * Fetch account legal entities
     *
     * @param int $account_id Account ID.
     * @return array{success: bool, entities?: array, error?: string}
     */
    public function fetch_account_entities( int $account_id ): array {
        $this->ensure_api_client();

        $result = $this->api_client->get( self::ENDPOINTS['account_legal_entities'], array(
            'AccountId' => $account_id,
        ) );

        if ( ! $result['success'] ) {
            return array(
                'success' => false,
                'error'   => $result['error'] ?? 'Failed to fetch entities',
            );
        }

        return array(
            'success'  => true,
            'entities' => $result['data'],
        );
    }

    /**
     * Normalize vacancy data to unified format
     *
     * Transforms UK Gov API v2 vacancy data into the common format.
     *
     * @param array $vacancy Raw vacancy data from API.
     * @return array Normalized vacancy data.
     */
    public function normalize_vacancy( array $vacancy ): array {
        // Extract employer info
        $employer_name         = $vacancy['employerName'] ?? '';
        $employer_website      = $vacancy['employerWebsiteUrl'] ?? '';
        $employer_description  = $vacancy['employerDescription'] ?? '';
        $employer_contact      = $vacancy['employerContactEmail'] ?? '';
        $employer_phone        = $vacancy['employerContactPhone'] ?? '';
        $employer_contact_name = $vacancy['employerContactName'] ?? '';

        // Extract training provider info
        $provider_name  = $vacancy['providerName'] ?? '';
        $provider_ukprn = isset( $vacancy['ukprn'] ) ? (string) $vacancy['ukprn'] : '';
        $provider_email = $vacancy['providerContactEmail'] ?? '';
        $provider_phone = $vacancy['providerContactPhone'] ?? '';
        $provider_contact_name = $vacancy['providerContactName'] ?? '';

        // Extract addresses (v2 uses 'addresses' array)
        $addresses = array();
        if ( isset( $vacancy['addresses'] ) && is_array( $vacancy['addresses'] ) ) {
            foreach ( $vacancy['addresses'] as $addr ) {
                $addresses[] = array(
                    'address_line1' => $addr['addressLine1'] ?? '',
                    'address_line2' => $addr['addressLine2'] ?? '',
                    'address_line3' => $addr['addressLine3'] ?? '',
                    'address_line4' => $addr['addressLine4'] ?? '',
                    'postcode'      => $addr['postcode'] ?? '',
                    'latitude'      => isset( $addr['latitude'] ) ? (float) $addr['latitude'] : null,
                    'longitude'     => isset( $addr['longitude'] ) ? (float) $addr['longitude'] : null,
                );
            }
        }

        // Get primary address (first in array or fallback to top-level)
        $primary_address = ! empty( $addresses ) ? $addresses[0] : array(
            'address_line1' => $vacancy['addressLine1'] ?? '',
            'address_line2' => $vacancy['addressLine2'] ?? '',
            'address_line3' => $vacancy['addressLine3'] ?? '',
            'address_line4' => $vacancy['addressLine4'] ?? '',
            'postcode'      => $vacancy['postcode'] ?? '',
            'latitude'      => isset( $vacancy['latitude'] ) ? (float) $vacancy['latitude'] : null,
            'longitude'     => isset( $vacancy['longitude'] ) ? (float) $vacancy['longitude'] : null,
        );

        // Extract wage info
        $wage_type   = $vacancy['wageType'] ?? '';
        $wage_amount = 0;
        $wage_lower  = 0;
        $wage_upper  = 0;

        if ( isset( $vacancy['wageAmount'] ) ) {
            $wage_amount = (float) $vacancy['wageAmount'];
        }
        if ( isset( $vacancy['wageAmountLowerBound'] ) ) {
            $wage_lower = (float) $vacancy['wageAmountLowerBound'];
        }
        if ( isset( $vacancy['wageAmountUpperBound'] ) ) {
            $wage_upper = (float) $vacancy['wageAmountUpperBound'];
        }

        // Extract skills and qualifications (arrays in v2)
        $skills         = array();
        $qualifications = array();

        if ( isset( $vacancy['skills'] ) && is_array( $vacancy['skills'] ) ) {
            $skills = $vacancy['skills'];
        }

        if ( isset( $vacancy['qualifications'] ) && is_array( $vacancy['qualifications'] ) ) {
            $qualifications = $vacancy['qualifications'];
        }

        // Build normalized data
        $normalized = array(
            // Core identifiers
            'vacancy_reference'       => $vacancy['vacancyReference'] ?? '',
            'vacancy_url'             => $vacancy['vacancyUrl'] ?? '',
            'provider_id'             => $this->get_id(),

            // Basic info
            'title'                   => $vacancy['title'] ?? '',
            'description'             => $this->clean_description( $vacancy['description'] ?? '' ),
            'short_description'       => $this->clean_description( $vacancy['shortDescription'] ?? '' ),

            // Employer
            'employer_name'           => $employer_name,
            'employer_website'        => $employer_website,
            'employer_description'    => $this->clean_description( $employer_description ),
            'employer_contact_email'  => $employer_contact,
            'employer_contact_phone'  => $employer_phone,
            'employer_contact_name'   => $employer_contact_name,

            // Training Provider
            'training_provider_name'  => $provider_name,
            'training_provider_ukprn' => $provider_ukprn,
            'training_provider_email' => $provider_email,
            'training_provider_phone' => $provider_phone,
            'training_provider_contact_name' => $provider_contact_name,

            // Location
            'addresses'               => $addresses,
            'primary_address'         => $primary_address,

            // Course/Qualification
            'course_title'            => $vacancy['courseTitle'] ?? '',
            'course_route'            => $vacancy['courseRoute'] ?? '',
            'course_level'            => isset( $vacancy['courseLevel'] ) ? (int) $vacancy['courseLevel'] : 0,
            'course_id'               => isset( $vacancy['courseId'] ) ? (int) $vacancy['courseId'] : 0,
            'apprenticeship_level'    => $vacancy['apprenticeshipLevel'] ?? '',

            // Employment
            'wage_type'               => $wage_type,
            'wage_amount'             => $wage_amount,
            'wage_amount_lower'       => $wage_lower,
            'wage_amount_upper'       => $wage_upper,
            'wage_unit'               => $vacancy['wageUnit'] ?? 'Annually',
            'wage_text'               => $vacancy['wageText'] ?? '',
            'wage_additional_info'    => $vacancy['wageAdditionalInformation'] ?? '',
            'working_week'            => $vacancy['workingWeek'] ?? '',
            'hours_per_week'          => isset( $vacancy['hoursPerWeek'] ) ? (float) $vacancy['hoursPerWeek'] : 0,
            'expected_duration'       => $vacancy['expectedDuration'] ?? '',
            'employment_type'         => $vacancy['employmentType'] ?? 'Apprenticeship',
            'positions_available'     => isset( $vacancy['numberOfPositions'] ) ? (int) $vacancy['numberOfPositions'] : 1,

            // Requirements
            'skills_required'         => $skills,
            'qualifications_required' => $qualifications,
            'things_to_consider'      => $vacancy['thingsToConsider'] ?? '',
            'outcome_description'     => $vacancy['outcomeDescription'] ?? '',

            // Dates
            'posted_date'             => $this->parse_date( $vacancy['postedDate'] ?? '' ),
            'closing_date'            => $this->parse_date( $vacancy['closingDate'] ?? '' ),
            'start_date'              => $this->parse_date( $vacancy['startDate'] ?? '' ),

            // Application
            'apply_url'               => $vacancy['applicationUrl'] ?? '',
            'apply_instructions'      => $vacancy['applicationInstructions'] ?? '',

            // Status
            'status'                  => 'active',
            'is_disability_confident' => ! empty( $vacancy['isDisabilityConfident'] ),
            'is_national'             => ! empty( $vacancy['isNational'] ),

            // Meta
            'raw_data'                => $vacancy,
            'imported_at'             => current_time( 'mysql' ),
        );

        return $this->merge_with_template( $normalized );
    }

    /**
     * Get example API response structure for UK Gov API
     *
     * Returns actual structure from the UK Government Apprenticeships API v2
     * based on the OpenAPI specification.
     *
     * @return array Example response with template variables.
     */
    public function get_example_response(): array {
        $example_item = array(
            'title'                     => 'Apprentice Invoicing Administrator',
            'description'               => '<p>A fantastic opportunity to join an established employer, in South Normanton. You will provide administration duties for all live contracts within the Maintenance group, supporting the operational contract admin with all aspects of sales invoicing.</p>',
            'numberOfPositions'         => 1,
            'postedDate'                => '2026-01-05T10:07:27.453Z',
            'closingDate'               => '2026-01-23T23:59:59Z',
            'startDate'                 => '2026-01-26T00:00:00Z',
            'wage'                      => array(
                'wageType'                  => 'Custom',
                'wageUnit'                  => 'Annually',
                'wageAdditionalInformation' => '£15,808 a year',
                'workingWeekDescription'    => 'Monday to Friday 8:00am to 5:00pm, 1-hour unpaid lunch break',
            ),
            'hoursPerWeek'              => 40,
            'expectedDuration'          => '19 Months',
            'addresses'                 => array(
                array(
                    'addressLine1' => '2 Maisies Way',
                    'addressLine2' => 'South Normanton',
                    'addressLine3' => 'Alfreton',
                    'postcode'     => 'DE55 2DS',
                    'latitude'     => 53.10721,
                    'longitude'    => -1.31883,
                ),
            ),
            'distance'                  => 48.5759261775074,
            'employerName'              => 'BGIS BUILDING SERVICES MAINTENANCE LIMITED',
            'employerWebsiteUrl'        => 'https://www.bgis.com/uk/',
            'course'                    => array(
                'larsCode' => 133,
                'title'    => 'Assistant accountant (level 3)',
                'level'    => 3,
                'route'    => 'Legal, finance and accounting',
                'type'     => 'Standard',
            ),
            'apprenticeshipLevel'       => 'Advanced',
            'providerName'              => 'WEST NOTTINGHAMSHIRE COLLEGE',
            'ukprn'                     => 10007427,
            'isDisabilityConfident'     => false,
            'vacancyUrl'                => 'https://www.findapprenticeship.service.gov.uk/apprenticeship/reference/2000005420',
            'vacancyReference'          => '2000005420',
            'isNationalVacancy'         => false,
            'isNationalVacancyDetails'  => '',
        );

        $example_response = array(
            'items'         => array( $example_item ),
            'total'         => 4551,
            'totalFiltered' => 460,
            'totalPages'    => 46,
            'pageNumber'    => 1,
            'pageSize'      => 30,
        );

        return array(
            'response'       => $example_response,
            'template_vars'  => $this->extract_template_variables( $example_item ),
            'description'    => 'Example response from UK Government Apprenticeships API v2. This shows the structure of vacancy data returned by the /vacancy endpoint.',
        );
    }

    /**
     * Get example API request structure for UK Gov API
     *
     * Returns a sample request configuration with all available parameters
     * and template variables for the UK Government Apprenticeships API.
     *
     * @return array Example request structure.
     */
    public function get_example_request(): array {
        $base_url = $this->get_base_url();
        $endpoint = self::ENDPOINTS['vacancy'];

        return array(
            'url'            => $base_url . $endpoint,
            'method'         => 'GET',
            'headers'        => array(
                'X-Version'                 => self::API_VERSION,
                'Ocp-Apim-Subscription-Key' => '{{subscription_key}}',
                'Accept'                    => 'application/json',
            ),
            'params'         => array(
                // Pagination
                'PageNumber'           => '{{page}}',
                'PageSize'             => '{{page_size}}',

                // Sorting
                'Sort'                 => '{{sort}}',

                // Filtering
                'Ukprn'                => '{{ukprn}}',
                'FilterBySubscription' => '{{filter_by_subscription}}',
                'StandardLarsCode'     => '{{lars_code}}',
                'NationWideOnly'       => '{{nationwide_only}}',
                'Postcode'             => '{{postcode}}',
                'DistanceInMiles'      => '{{distance}}',
                'Routes'               => '{{routes}}',
                'Levels'               => '{{levels}}',
            ),
            'template_vars'  => array(
                '{{subscription_key}}'      => 'Your Ocp-Apim-Subscription-Key from API Management Portal',
                '{{page}}'                  => 'Page number (default: 1)',
                '{{page_size}}'             => 'Results per page (default: 100, max: 100)',
                '{{sort}}'                  => 'Sort order: "AgeAsc", "AgeDesc", "ClosingAsc", "ClosingDesc", "DistanceAsc", "DistanceDesc", "ExpectedStartDateAsc", "ExpectedStartDateDesc"',
                '{{ukprn}}'                 => 'UK Provider Reference Number for filtering by training provider',
                '{{filter_by_subscription}}' => 'Filter by subscription (true/false)',
                '{{lars_code}}'             => 'Standard LARS Code to filter by specific course',
                '{{nationwide_only}}'       => 'Show only nationwide vacancies (true/false)',
                '{{postcode}}'              => 'Postcode for location-based search',
                '{{distance}}'              => 'Distance from postcode in miles',
                '{{routes}}'                => 'Comma-separated route names (e.g., "Digital,Engineering and manufacturing")',
                '{{levels}}'                => 'Comma-separated levels: "Intermediate" (2), "Advanced" (3), "Higher" (4-5), "Degree" (6-7)',
            ),
            'description'    => 'Example request to UK Government Apprenticeships API /vacancy endpoint. All parameters are optional except pagination.',
            'rate_limit_info' => $this->get_rate_limit_info(),
        );
    }

    /**
     * Get body template variables for POST requests
     *
     * Note: UK Gov API v2 uses GET requests only for public consumption.
     * This method is provided for completeness and future POST endpoints.
     *
     * @return array Request body template structure.
     */
    public function get_request_body_template(): array {
        return array(
            'template' => array(
                'message' => 'The UK Government Apprenticeships API v2 uses GET requests only.',
                'note'    => 'For creating or updating vacancies, you need access to the Employer Portal.',
            ),
            'template_vars' => array(),
            'description'   => 'UK Gov API does not accept POST requests for vacancy retrieval.',
        );
    }

    /**
     * Get response body template variables with examples
     *
     * Provides a detailed mapping of all fields in the API response
     * with their types, descriptions, and example values.
     *
     * @return array Response template structure.
     */
    public function get_response_body_template(): array {
        $template_vars = array(
            // Wrapper fields
            'items'              => 'Array of vacancy objects',
            'total'              => 'Total number of vacancies available (integer)',
            'totalFiltered'      => 'Number of vacancies matching filters (integer)',
            'totalPages'         => 'Total number of pages (integer)',
            'pageNumber'         => 'Current page number (integer)',
            'pageSize'           => 'Number of items per page (integer)',

            // Vacancy fields (inside items array)
            'items[0].title'                     => 'Vacancy title (string)',
            'items[0].description'               => 'Full description with HTML (string)',
            'items[0].numberOfPositions'         => 'Number of positions available (integer)',
            'items[0].postedDate'                => 'Date posted in ISO 8601 format (string)',
            'items[0].closingDate'               => 'Application closing date (string)',
            'items[0].startDate'                 => 'Expected start date (string)',
            'items[0].hoursPerWeek'              => 'Working hours per week (number)',
            'items[0].expectedDuration'          => 'Duration of apprenticeship (string)',
            'items[0].distance'                  => 'Distance from search location in miles (number)',
            'items[0].employerName'              => 'Employer company name (string)',
            'items[0].employerWebsiteUrl'        => 'Employer website URL (string)',
            'items[0].employerDescription'       => 'Employer description (string)',
            'items[0].providerName'              => 'Training provider name (string)',
            'items[0].ukprn'                     => 'UK Provider Reference Number (integer)',
            'items[0].isDisabilityConfident'     => 'Disability Confident employer (boolean)',
            'items[0].vacancyUrl'                => 'Link to vacancy on Find an Apprenticeship (string)',
            'items[0].vacancyReference'          => 'Unique vacancy reference number (string)',
            'items[0].isNationalVacancy'         => 'Available nationwide (boolean)',
            'items[0].apprenticeshipLevel'       => 'Level: Intermediate/Advanced/Higher/Degree (string)',

            // Wage fields
            'items[0].wage.wageType'             => 'Type: ApprenticeshipMinimum, NationalMinimum, Custom, CompetitiveSalary (string)',
            'items[0].wage.wageUnit'             => 'Unit: Annually, Weekly, Monthly (string)',
            'items[0].wage.wageAdditionalInformation' => 'Additional wage information (string)',
            'items[0].wage.workingWeekDescription' => 'Description of working hours (string)',

            // Address fields
            'items[0].addresses[0].addressLine1' => 'Address line 1 (string)',
            'items[0].addresses[0].addressLine2' => 'Address line 2 (string)',
            'items[0].addresses[0].addressLine3' => 'Town/City (string)',
            'items[0].addresses[0].addressLine4' => 'County (string)',
            'items[0].addresses[0].postcode'     => 'Postcode (string)',
            'items[0].addresses[0].latitude'     => 'Latitude coordinate (number)',
            'items[0].addresses[0].longitude'    => 'Longitude coordinate (number)',

            // Course fields
            'items[0].course.larsCode'           => 'LARS (Learning Aim Reference Service) code (integer)',
            'items[0].course.title'              => 'Course/Standard title (string)',
            'items[0].course.level'              => 'Course level 2-7 (integer)',
            'items[0].course.route'              => 'Apprenticeship route/pathway (string)',
            'items[0].course.type'               => 'Type: Standard or Framework (string)',
        );

        return array(
            'template'       => $template_vars,
            'description'    => 'Complete field mapping for UK Government Apprenticeships API v2 response',
            'example_values' => array(
                'wageType' => array( 'ApprenticeshipMinimum', 'NationalMinimum', 'Custom', 'CompetitiveSalary' ),
                'wageUnit' => array( 'Annually', 'Weekly', 'Monthly', 'Daily' ),
                'apprenticeshipLevel' => array( 'Intermediate', 'Advanced', 'Higher', 'Degree' ),
                'courseType' => array( 'Standard', 'Framework' ),
            ),
        );
    }
}
