# API Template Variables & Example Responses

## Overview

The Apprenticeship Connect plugin now provides **context-aware custom PHP functions** with built-in support for:

- ✅ Example API responses with real data structures
- ✅ Request/response body template variable extraction
- ✅ Enhanced rate limiting with detailed monitoring
- ✅ Dynamic template variable replacement
- ✅ Response structure validation

## Table of Contents

1. [Getting Example Responses](#getting-example-responses)
2. [Template Variables](#template-variables)
3. [REST API Endpoints](#rest-api-endpoints)
4. [Rate Limiting](#rate-limiting)
5. [Custom Provider Implementation](#custom-provider-implementation)
6. [Usage Examples](#usage-examples)

---

## Getting Example Responses

### Via REST API

```bash
# Get example response for UK Gov provider
curl https://your-site.com/wp-json/apprco/v1/provider/uk-gov-apprenticeships/example-response

# Get example request structure
curl https://your-site.com/wp-json/apprco/v1/provider/uk-gov-apprenticeships/example-request

# Get response template variables
curl https://your-site.com/wp-json/apprco/v1/provider/uk-gov-apprenticeships/response-template

# Get rate limit information
curl https://your-site.com/wp-json/apprco/v1/provider/uk-gov-apprenticeships/rate-limits
```

### Via PHP

```php
// Get provider instance
$provider = new Apprco_UK_Gov_Provider();

// Get example response with template variables
$example_response = $provider->get_example_response();
/*
Returns:
[
    'response' => [
        'items' => [...],
        'total' => 4551,
        'totalFiltered' => 460,
        ...
    ],
    'template_vars' => [
        'title' => 'Title (string) - Example: Apprentice Invoicing Administrator',
        'description' => 'Description (string) - Example: A fantastic opportunity...',
        ...
    ],
    'description' => 'Example response from UK Government Apprenticeships API v2'
]
*/

// Get example request structure
$example_request = $provider->get_example_request();
/*
Returns:
[
    'url' => 'https://api.apprenticeships.education.gov.uk/vacancies/vacancy',
    'method' => 'GET',
    'headers' => [...],
    'params' => [...],
    'template_vars' => [
        '{{subscription_key}}' => 'Your Ocp-Apim-Subscription-Key...',
        '{{page}}' => 'Page number (default: 1)',
        ...
    ]
]
*/
```

---

## Template Variables

### Available Template Variables (UK Gov API)

#### Wrapper Fields
```
items              → Array of vacancy objects
total              → Total number of vacancies available (integer)
totalFiltered      → Number of vacancies matching filters (integer)
totalPages         → Total number of pages (integer)
pageNumber         → Current page number (integer)
pageSize           → Number of items per page (integer)
```

#### Vacancy Fields
```
items[0].title                     → Vacancy title (string)
items[0].description               → Full description with HTML (string)
items[0].numberOfPositions         → Number of positions available (integer)
items[0].postedDate                → Date posted in ISO 8601 format (string)
items[0].closingDate               → Application closing date (string)
items[0].startDate                 → Expected start date (string)
items[0].hoursPerWeek              → Working hours per week (number)
items[0].expectedDuration          → Duration of apprenticeship (string)
items[0].employerName              → Employer company name (string)
items[0].providerName              → Training provider name (string)
items[0].vacancyReference          → Unique vacancy reference number (string)
items[0].apprenticeshipLevel       → Level: Intermediate/Advanced/Higher/Degree
```

#### Wage Fields
```
items[0].wage.wageType             → ApprenticeshipMinimum/NationalMinimum/Custom/CompetitiveSalary
items[0].wage.wageUnit             → Annually/Weekly/Monthly
items[0].wage.wageAdditionalInformation → Additional wage information
items[0].wage.workingWeekDescription    → Description of working hours
```

#### Address Fields
```
items[0].addresses[0].addressLine1 → Address line 1 (string)
items[0].addresses[0].postcode     → Postcode (string)
items[0].addresses[0].latitude     → Latitude coordinate (number)
items[0].addresses[0].longitude    → Longitude coordinate (number)
```

#### Course Fields
```
items[0].course.larsCode           → LARS code (integer)
items[0].course.title              → Course/Standard title (string)
items[0].course.level              → Course level 2-7 (integer)
items[0].course.route              → Apprenticeship route/pathway (string)
items[0].course.type               → Standard or Framework (string)
```

### Extracting Template Variables from Custom Data

```php
// Extract variables from any JSON/array structure
$custom_data = json_decode($api_response, true);

$provider = new Apprco_UK_Gov_Provider();
$template_vars = $provider->extract_template_variables($custom_data);

// Result: ['path.to.field' => 'Field Name (type) - Example: value']
```

### Via REST API

```bash
curl -X POST https://your-site.com/wp-json/apprco/v1/tools/extract-template-vars \
  -H "Content-Type: application/json" \
  -d '{
    "data": {
      "user": {
        "name": "John Doe",
        "email": "john@example.com"
      }
    }
  }'

# Returns:
# {
#   "template_vars": {
#     "user.name": "Name (string) - Example: John Doe",
#     "user.email": "Email (string) - Example: john@example.com"
#   },
#   "count": 2
# }
```

---

## REST API Endpoints

### Provider Example Data

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/apprco/v1/provider/{id}/example-response` | GET | Get example API response with actual data structure |
| `/apprco/v1/provider/{id}/example-request` | GET | Get example request with all parameters and headers |
| `/apprco/v1/provider/{id}/response-template` | GET | Get complete response field mapping |
| `/apprco/v1/provider/{id}/request-body-template` | GET | Get request body template (for POST requests) |
| `/apprco/v1/provider/{id}/rate-limits` | GET | Get detailed rate limiting information |

### Utilities

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/apprco/v1/tools/extract-template-vars` | POST | Extract template variables from custom JSON data |

### Example Usage

```javascript
// Fetch example response
fetch('/wp-json/apprco/v1/provider/uk-gov-apprenticeships/example-response')
  .then(res => res.json())
  .then(data => {
    console.log('Example Response:', data.response);
    console.log('Template Variables:', data.template_vars);
    console.log('Description:', data.description);
  });

// Fetch rate limits
fetch('/wp-json/apprco/v1/provider/uk-gov-apprenticeships/rate-limits')
  .then(res => res.json())
  .then(limits => {
    console.log('Requests per minute:', limits.requests_per_minute);
    console.log('Delay between requests:', limits.delay_ms, 'ms');
    console.log('Daily limit:', limits.requests_per_day);
  });

// Extract variables from custom data
fetch('/wp-json/apprco/v1/tools/extract-template-vars', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    data: {
      products: [
        { id: 1, name: 'Product A', price: 99.99 }
      ]
    }
  })
})
  .then(res => res.json())
  .then(result => {
    console.log('Extracted Variables:', result.template_vars);
  });
```

---

## Rate Limiting

### Enhanced Rate Limit Information

The plugin provides **5 layers of rate limiting**:

#### Layer 1: API Client (Base)
```php
// Default: 200ms delay between requests
// Configurable per provider
```

#### Layer 2: Provider Level
```php
// UK Gov API: 60 requests/minute, 250ms delay
$provider->get_rate_limits();
/*
[
    'requests_per_minute' => 60,
    'delay_ms' => 250
]
*/
```

#### Layer 3: Enhanced Info
```php
$provider->get_rate_limit_info();
/*
[
    'requests_per_minute' => 60,
    'delay_ms' => 250,
    'requests_per_hour' => 3600,
    'requests_per_day' => 86400,
    'recommended_page_size' => 100,
    'retry_config' => [
        'max_retries' => 3,
        'initial_delay' => 1000,
        'backoff_multiplier' => 2
    ],
    'throttle_on_429' => true,
    'respect_retry_after' => true
]
*/
```

#### Layer 4: Retry with Exponential Backoff
```php
// Automatic retry on failures:
// Attempt 1: Immediate
// Attempt 2: Wait 1000ms (1s)
// Attempt 3: Wait 2000ms (2s)
// Attempt 4: Wait 4000ms (4s)
```

#### Layer 5: Respect Retry-After Header
```php
// If API returns 429 with Retry-After header,
// the client automatically waits the specified time
```

### Monitoring Rate Limits

```php
// Check if approaching limits
$limits = $provider->get_rate_limit_info();
$max_per_day = $limits['requests_per_day'];
$current_usage = get_transient('apprco_api_usage_' . date('Y-m-d'));

if ($current_usage > $max_per_day * 0.9) {
    // Warning: 90% of daily limit reached
}
```

---

## Custom Provider Implementation

### Creating a New Provider with Example Support

```php
<?php
class My_Custom_Provider extends Apprco_Abstract_Provider {

    /**
     * Override to provide actual API response example
     */
    public function get_example_response(): array {
        $example_item = array(
            'id' => 123,
            'title' => 'Sample Item',
            'price' => 99.99,
            'category' => 'Electronics',
            'attributes' => array(
                'color' => 'Blue',
                'size' => 'Large'
            )
        );

        return array(
            'response' => array(
                'data' => array($example_item),
                'total' => 1,
                'page' => 1
            ),
            'template_vars' => $this->extract_template_variables($example_item),
            'description' => 'Example response from My Custom API'
        );
    }

    /**
     * Override to provide request example
     */
    public function get_example_request(): array {
        return array(
            'url' => 'https://api.example.com/v1/items',
            'method' => 'GET',
            'headers' => array(
                'Authorization' => 'Bearer {{api_key}}',
                'Accept' => 'application/json'
            ),
            'params' => array(
                'page' => '{{page}}',
                'limit' => '{{limit}}',
                'category' => '{{category}}'
            ),
            'template_vars' => array(
                '{{api_key}}' => 'Your API authentication key',
                '{{page}}' => 'Page number (1-based)',
                '{{limit}}' => 'Items per page (max 100)',
                '{{category}}' => 'Filter by category name'
            ),
            'description' => 'Example request to My Custom API',
            'rate_limit_info' => $this->get_rate_limit_info()
        );
    }

    /**
     * Optional: Override to provide POST body template
     */
    public function get_request_body_template(): array {
        return array(
            'template' => array(
                'item' => array(
                    'title' => '{{title}}',
                    'price' => '{{price}}',
                    'category' => '{{category}}'
                )
            ),
            'template_vars' => array(
                '{{title}}' => 'Item title (required)',
                '{{price}}' => 'Price in USD (required)',
                '{{category}}' => 'Category name (optional)'
            ),
            'description' => 'POST body template for creating items'
        );
    }

    /**
     * Optional: Override to provide detailed response template
     */
    public function get_response_body_template(): array {
        return array(
            'template' => array(
                'data[0].id' => 'Unique item ID (integer)',
                'data[0].title' => 'Item title (string)',
                'data[0].price' => 'Price in USD (number)',
                'data[0].category' => 'Category name (string)',
                'data[0].attributes.color' => 'Color variant (string)',
                'data[0].attributes.size' => 'Size variant (string)',
                'total' => 'Total number of items (integer)',
                'page' => 'Current page number (integer)'
            ),
            'description' => 'Complete field mapping for API response',
            'example_values' => array(
                'category' => array('Electronics', 'Clothing', 'Books'),
                'attributes.color' => array('Red', 'Blue', 'Green'),
                'attributes.size' => array('Small', 'Medium', 'Large')
            )
        );
    }
}
```

---

## Usage Examples

### Example 1: Display Available Fields in Import Wizard

```php
// In your import wizard JavaScript
async function loadFieldOptions(providerId) {
    const response = await fetch(
        `/wp-json/apprco/v1/provider/${providerId}/example-response`
    );
    const data = await response.json();

    // Display all available fields
    const fields = Object.keys(data.template_vars);
    const fieldList = document.getElementById('available-fields');

    fields.forEach(field => {
        const option = document.createElement('option');
        option.value = field;
        option.textContent = data.template_vars[field];
        fieldList.appendChild(option);
    });
}
```

### Example 2: Validate Response Structure

```php
// Validate that API response contains required fields
$provider = new Apprco_UK_Gov_Provider();
$response = json_decode($api_response, true);

$validation = $provider->validate_response_structure(
    $response['items'][0],
    array('title', 'employerName', 'postedDate', 'closingDate')
);

if (!$validation['valid']) {
    error_log('Missing required fields: ' . implode(', ', $validation['missing']));
}

if (!empty($validation['warnings'])) {
    error_log('Warnings: ' . implode(', ', $validation['warnings']));
}
```

### Example 3: Generate Field Mapping UI

```javascript
// Auto-generate mapping interface
fetch('/wp-json/apprco/v1/provider/uk-gov-apprenticeships/response-template')
    .then(res => res.json())
    .then(template => {
        const mappingTable = document.getElementById('field-mapping');

        Object.entries(template.template).forEach(([path, description]) => {
            const row = mappingTable.insertRow();
            row.innerHTML = `
                <td>${path}</td>
                <td>${description}</td>
                <td>
                    <select name="mapping[${path}]">
                        <option value="">-- Skip --</option>
                        <option value="post_title">Post Title</option>
                        <option value="post_content">Content</option>
                        <option value="_apprco_${path}">Custom Field</option>
                    </select>
                </td>
            `;
        });
    });
```

### Example 4: Test API Connection with Example

```php
// Test connection and show example data
$provider = new Apprco_UK_Gov_Provider();
$provider->set_config(array(
    'subscription_key' => 'your-key-here'
));

// Test connection
$test_result = $provider->test_connection();

if ($test_result['success']) {
    // Show example of what data looks like
    $example = $provider->get_example_response();

    echo '<h3>Connection Successful!</h3>';
    echo '<p>Example of data structure you\'ll receive:</p>';
    echo '<pre>' . json_encode($example['response'], JSON_PRETTY_PRINT) . '</pre>';

    echo '<h4>Available Fields:</h4>';
    echo '<ul>';
    foreach ($example['template_vars'] as $field => $description) {
        echo '<li><code>' . esc_html($field) . '</code> - ' . esc_html($description) . '</li>';
    }
    echo '</ul>';
}
```

### Example 5: Monitor Rate Limits in Real-Time

```php
// Dashboard widget showing rate limit usage
function display_rate_limit_widget() {
    $provider = new Apprco_UK_Gov_Provider();
    $limits = $provider->get_rate_limit_info();

    $today = date('Y-m-d');
    $usage_key = 'apprco_api_usage_' . $today;
    $current_usage = (int) get_transient($usage_key);

    $percentage = ($current_usage / $limits['requests_per_day']) * 100;

    ?>
    <div class="rate-limit-widget">
        <h3>API Rate Limit Status</h3>
        <div class="progress-bar">
            <div class="progress" style="width: <?php echo $percentage; ?>%"></div>
        </div>
        <p>
            <strong><?php echo number_format($current_usage); ?></strong> /
            <?php echo number_format($limits['requests_per_day']); ?> requests today
        </p>
        <ul>
            <li>Per minute: <?php echo $limits['requests_per_minute']; ?></li>
            <li>Per hour: <?php echo number_format($limits['requests_per_hour']); ?></li>
            <li>Delay: <?php echo $limits['delay_ms']; ?>ms</li>
            <li>Retry on 429: <?php echo $limits['throttle_on_429'] ? 'Yes' : 'No'; ?></li>
        </ul>
    </div>
    <?php
}
```

---

## Best Practices

### 1. Always Check Rate Limits Before Bulk Operations

```php
$provider = new Apprco_UK_Gov_Provider();
$limits = $provider->get_rate_limit_info();

$total_pages = 100;
$estimated_time = ($total_pages * $limits['delay_ms']) / 1000; // seconds

if ($estimated_time > 3600) {
    // Will take over an hour - warn user or schedule as background job
}
```

### 2. Cache Example Responses

```php
$cache_key = 'apprco_example_response_' . $provider_id;
$example = get_transient($cache_key);

if (false === $example) {
    $provider = new Apprco_UK_Gov_Provider();
    $example = $provider->get_example_response();
    set_transient($cache_key, $example, DAY_IN_SECONDS);
}
```

### 3. Validate Before Processing

```php
$provider = new Apprco_UK_Gov_Provider();

foreach ($api_items as $item) {
    $validation = $provider->validate_response_structure($item);

    if (!$validation['valid']) {
        $logger->warning('Skipping invalid item', array(
            'missing_fields' => $validation['missing']
        ));
        continue;
    }

    // Process valid item
    $normalized = $provider->normalize_vacancy($item);
}
```

### 4. Use Template Variables for Documentation

```php
// Generate API documentation automatically
$provider = new Apprco_UK_Gov_Provider();
$request_example = $provider->get_example_request();
$response_example = $provider->get_example_response();

// Output to docs
generate_api_docs(array(
    'request' => $request_example,
    'response' => $response_example
));
```

---

## Troubleshooting

### Issue: Template variables not showing

**Solution**: Ensure the provider implements `get_example_response()` method.

### Issue: Rate limit exceeded

**Solution**: Check current usage and increase delay or reduce page size:

```php
$limits = $provider->get_rate_limit_info();
$recommended_delay = $limits['delay_ms'] * 2; // Double the delay
```

### Issue: Missing fields in extracted variables

**Solution**: Ensure data is properly nested array/object. Use validator:

```php
if (!is_array($data) && !is_object($data)) {
    $data = json_decode($data, true);
}
$vars = $provider->extract_template_variables($data);
```

---

## Additional Resources

- [UK Government Apprenticeships API Documentation](https://api.apprenticeships.education.gov.uk)
- [WordPress REST API Handbook](https://developer.wordpress.org/rest-api/)
- [Rate Limiting Best Practices](https://tools.ietf.org/id/draft-polli-ratelimit-headers-00.html)

---

## Support

For issues or questions:
1. Check the [GitHub Issues](https://github.com/your-repo/issues)
2. Review example implementations in `/includes/providers/`
3. Test with REST API endpoints first before implementing in code
