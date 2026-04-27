# API Endpoint Testing Guide

## Prerequisites
1. WordPress site with plugin installed
2. API subscription key configured in Settings Manager
3. curl or REST client installed

---

## OSM Geocoding Tests

### Test 1: Forward Geocoding (Postcode)
```bash
curl "http://your-site.com/wp-json/apprco/v1/geocode/forward?location=CV213FE"
```

**Expected Response:**
```json
{
  "success": true,
  "location": {
    "lat": 52.3778300,
    "lon": -1.2404400,
    "display_name": "CV21 3FE, Rugby, Warwickshire, England, United Kingdom"
  },
  "source": "osm"
}
```

**OSM API Called:**
```
https://nominatim.openstreetmap.org/search?q=CV213FE&addressdetails=1&extratags=1&namedetails=1&format=jsonv2&limit=1
```

---

### Test 2: Reverse Geocoding
```bash
curl "http://your-site.com/wp-json/apprco/v1/geocode/reverse?lat=52.3778300&lon=-1.2404400"
```

**Expected Response:**
```json
{
  "success": true,
  "address": {
    "display_name": "Ridge Drive, Rugby, Warwickshire, England, CV21 3FG, United Kingdom",
    "postcode": "CV21 3FG",
    "city": "Rugby",
    "county": "Warwickshire",
    "country": "United Kingdom",
    "full": {
      "road": "Ridge Drive",
      "town": "Rugby",
      "county": "Warwickshire",
      "state": "England",
      "postcode": "CV21 3FG",
      "country": "United Kingdom",
      "country_code": "gb"
    }
  },
  "source": "osm"
}
```

**OSM API Called:**
```
https://nominatim.openstreetmap.org/reverse?lat=52.3778300&lon=-1.2404400&addressdetails=1&extratags=1&namedetails=1&format=jsonv2
```

---

### Test 3: Current Location (from browser geolocation)
```bash
curl -X POST "http://your-site.com/wp-json/apprco/v1/geocode/current" \
  -H "Content-Type: application/json" \
  -d '{"lat": 52.3778300, "lon": -1.2404400}'
```

**Expected Response:**
```json
{
  "success": true,
  "location": {
    "lat": 52.3778300,
    "lon": -1.2404400,
    "display_name": "Ridge Drive, Rugby, Warwickshire, England, CV21 3FG, United Kingdom"
  },
  "address": {
    "postcode": "CV21 3FG",
    "city": "Rugby",
    "county": "Warwickshire",
    "country": "United Kingdom"
  },
  "source": "browser_geolocation"
}
```

---

## Display Advert API v2 Tests

### Test 4: Search Vacancies (Basic)
```bash
curl "http://your-site.com/wp-json/apprco/v1/proxy/vacancies?PageSize=10&PageNumber=1"
```

**Headers Sent to Gov.uk API:**
- `X-Version: 2`
- `Ocp-Apim-Subscription-Key: [your-key]`
- `Accept: application/json`
- `Content-Type: application/json`

**Gov.uk API Called:**
```
https://api.apprenticeships.education.gov.uk/vacancies/vacancy?PageSize=10&PageNumber=1
```

---

### Test 5: Location-Based Search
```bash
curl "http://your-site.com/wp-json/apprco/v1/proxy/vacancies?Lat=52.408056&Lon=-1.510556&Sort=DistanceAsc&DistanceInMiles=20&PageSize=20&PageNumber=1"
```

**Headers Sent to Gov.uk API:**
- `X-Version: 2`
- `Ocp-Apim-Subscription-Key: [your-key]`
- `Accept: application/json`
- `Content-Type: application/json`

**Gov.uk API Called:**
```
https://api.apprenticeships.education.gov.uk/vacancies/vacancy?Lat=52.408056&Lon=-1.510556&Sort=DistanceAsc&DistanceInMiles=20&PageSize=20&PageNumber=1
```

**Expected Response:**
```json
{
  "totalResults": 123,
  "pageNumber": 1,
  "pageSize": 20,
  "results": [
    {
      "vacancyReference": "VAC1234567890",
      "title": "Software Developer Apprenticeship",
      "employer": "Tech Company Ltd",
      "distance": 2.3,
      ...
    }
  ]
}
```

---

### Test 6: Posted in Last 7 Days
```bash
curl "http://your-site.com/wp-json/apprco/v1/proxy/vacancies?PostedInLastNumberOfDays=7&PageSize=30"
```

**Headers Sent to Gov.uk API:**
- `X-Version: 2`
- `Ocp-Apim-Subscription-Key: [your-key]`
- `Accept: application/json`
- `Content-Type: application/json`

---

### Test 7: Single Vacancy by Reference
```bash
curl "http://your-site.com/wp-json/apprco/v1/proxy/vacancy/VAC1234567890"
```

**Headers Sent to Gov.uk API:**
- `X-Version: 2`
- `Ocp-Apim-Subscription-Key: [your-key]`
- `Accept: application/json`
- `Content-Type: application/json`

**Gov.uk API Called:**
```
https://api.apprenticeships.education.gov.uk/vacancies/vacancy/VAC1234567890
```

---

### Test 8: Get All Courses
```bash
curl "http://your-site.com/wp-json/apprco/v1/proxy/courses"
```

**Headers Sent to Gov.uk API:**
- `X-Version: 2`
- `Ocp-Apim-Subscription-Key: [your-key]`
- `Accept: application/json`
- `Content-Type: application/json`

**Gov.uk API Called:**
```
https://api.apprenticeships.education.gov.uk/vacancies/referencedata/courses
```

---

### Test 9: Get All Routes (Categories)
```bash
curl "http://your-site.com/wp-json/apprco/v1/proxy/routes"
```

**Headers Sent to Gov.uk API:**
- `X-Version: 2`
- `Ocp-Apim-Subscription-Key: [your-key]`
- `Accept: application/json`
- `Content-Type: application/json`

**Gov.uk API Called:**
```
https://api.apprenticeships.education.gov.uk/vacancies/referencedata/courses/routes
```

---

## JavaScript Frontend Examples

### Example 1: Search by User's Postcode
```javascript
// Step 1: Get coordinates from postcode
const postcodeResponse = await fetch(
  '/wp-json/apprco/v1/geocode/forward?location=CV213FE'
);
const { location } = await postcodeResponse.json();

// Step 2: Search vacancies within 20 miles
const vacancies = await fetch(
  `/wp-json/apprco/v1/proxy/vacancies?` +
  `Lat=${location.lat}&Lon=${location.lon}&` +
  `DistanceInMiles=20&Sort=DistanceAsc&PageSize=30`
).then(r => r.json());

console.log(`Found ${vacancies.totalResults} vacancies near ${location.display_name}`);
```

---

### Example 2: Use Browser Geolocation
```javascript
navigator.geolocation.getCurrentPosition(async (position) => {
  // Process current location
  const locationResponse = await fetch('/wp-json/apprco/v1/geocode/current', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      lat: position.coords.latitude,
      lon: position.coords.longitude
    })
  });
  const locationData = await locationResponse.json();

  // Search vacancies
  const vacancies = await fetch(
    `/wp-json/apprco/v1/proxy/vacancies?` +
    `Lat=${position.coords.latitude}&Lon=${position.coords.longitude}&` +
    `DistanceInMiles=15&PageSize=20`
  ).then(r => r.json());

  console.log(`Found ${vacancies.totalResults} vacancies near ${locationData.address.city}`);
});
```

---

### Example 3: Get Reference Data for Filters
```javascript
// Get all courses
const courses = await fetch('/wp-json/apprco/v1/proxy/courses')
  .then(r => r.json());

// Get all routes
const routes = await fetch('/wp-json/apprco/v1/proxy/routes')
  .then(r => r.json());

// Populate select dropdowns
const courseSelect = document.getElementById('course-filter');
courses.forEach(course => {
  const option = document.createElement('option');
  option.value = course.id;
  option.textContent = course.title;
  courseSelect.appendChild(option);
});
```

---

## Error Responses

### Invalid API Key (401)
```json
{
  "code": "api_error",
  "message": "Unauthorized - Invalid API subscription key",
  "data": {
    "status": 401
  }
}
```

### Rate Limit Exceeded (429)
```json
{
  "code": "api_error",
  "message": "Rate limit exceeded - Too many requests (max 150 per 5 minutes)",
  "data": {
    "status": 429
  }
}
```

### Location Not Found (404)
```json
{
  "code": "location_not_found",
  "message": "Could not find location: InvalidPostcode",
  "data": {
    "status": 404
  }
}
```

---

## Verification Checklist

- [ ] OSM forward geocoding returns correct coordinates for postcode
- [ ] OSM reverse geocoding returns correct address from coordinates
- [ ] Current location endpoint handles browser geolocation
- [ ] Display Advert API proxy returns vacancy list
- [ ] Location-based search works with Lat/Lon/Distance
- [ ] Single vacancy lookup works by reference
- [ ] Courses reference data returns
- [ ] Routes reference data returns
- [ ] All requests include proper headers (X-Version: 2, Ocp-Apim-Subscription-Key, etc.)
- [ ] CORS headers allow frontend access
- [ ] Error messages are user-friendly

---

## Direct OSM API Format (Reference)

Our geocoder now uses the EXACT format:

**Forward:**
```
https://nominatim.openstreetmap.org/search?q=Cv213fe&addressdetails=1&extratags=1&namedetails=1&format=jsonv2&limit=1
```

**Reverse:**
```
https://nominatim.openstreetmap.org/reverse?lat=52.3778300&lon=-1.2404400&addressdetails=1&extratags=1&namedetails=1&format=jsonv2
```

---

## Display Advert API Required Headers

ALL requests to the gov.uk API include:
- `X-Version: 2` - API version
- `Ocp-Apim-Subscription-Key: [your-key]` - Authentication
- `Accept: application/json` - Expected response format
- `Content-Type: application/json` - Request content type

Rate limit: 150 requests per 5 minutes
