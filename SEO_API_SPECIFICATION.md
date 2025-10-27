# 📋 SEO Tools API Specification

**Version:** 2.0.0  
**Last Updated:** October 13, 2025  
**Status:** ✅ Production Ready

---

## 🎯 Overview

This document provides complete API specifications for the SEO Tools / Search Analytics module. The system integrates with Google Search Console API to provide comprehensive SEO analytics, page performance tracking, and actionable recommendations.

### Key Features

- ✅ Google Search Console OAuth 2.0 integration
- ✅ Daily metrics tracking and trend analysis
- ✅ Page-level performance monitoring
- ✅ Automated SEO recommendations
- ✅ Local database caching for historical data
- ✅ Automated daily synchronization via cron
- ✅ RESTful API design
- ✅ Comprehensive error handling

---

## 🔐 Authentication

All endpoints require Sanctum authentication.

**Header Required:**
```
Authorization: Bearer {your_sanctum_token}
```

---

## 📡 API Endpoints

### Base URL

```
http://localhost:8000/api/seo
https://yourdomain.com/api/seo
```

---

## 1. OAuth & Authentication

### 1.1 Initiate Google OAuth

**Endpoint:** `GET /api/seo/auth/google`  
**Alias:** `GET /api/seo/oauth/redirect`

Get the Google OAuth authorization URL to connect Google Search Console.

**Request:**
```http
GET /api/seo/auth/google
Authorization: Bearer {token}
```

**Response (200):**
```json
{
  "status": "success",
  "auth_url": "https://accounts.google.com/o/oauth2/auth?client_id=..."
}
```

**Frontend Usage:**
```javascript
const response = await axios.get('/api/seo/auth/google');
window.location.href = response.data.auth_url;
```

---

### 1.2 Handle OAuth Callback

**Endpoint:** `GET /api/seo/google/callback`  
**Alias:** `GET /api/seo/oauth/callback`

Handles the OAuth callback from Google and stores access tokens.

**Request:**
```http
GET /api/seo/google/callback?code={auth_code}
Authorization: Bearer {token}
```

**Parameters:**
- `code` (required): Authorization code from Google

**Response (200):**
```json
{
  "status": "success",
  "message": "Successfully connected to Google Search Console",
  "expires_in": 3600
}
```

**Error Response (400):**
```json
{
  "status": "error",
  "message": "Authorization code not provided"
}
```

---

## 2. Dashboard & Analytics

### 2.1 Get Dashboard Data

**Endpoint:** `GET /api/seo/dashboard`

Retrieve comprehensive dashboard data including metrics, top pages, recommendations, and trends.

**Request:**
```http
GET /api/seo/dashboard?days=30
Authorization: Bearer {token}
```

**Query Parameters:**
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `days` | integer | 30 | Number of days of historical data |

**Response (200):**
```json
{
  "status": "success",
  "data": {
    "summary": {
      "total_clicks": 12450,
      "total_impressions": 456789,
      "average_ctr": 2.73,
      "average_position": 12.45
    },
    "top_pages": [
      {
        "id": 1,
        "page_url": "https://example.com/blog/seo-guide",
        "title": "Complete SEO Guide",
        "clicks": 1234,
        "impressions": 45678,
        "ctr": 2.70,
        "position": 8.30,
        "last_fetched_at": "2025-10-13T05:30:00.000000Z"
      }
    ],
    "recommendations": [
      {
        "id": 1,
        "page_url": "https://example.com/blog/post",
        "recommendation_type": "low_ctr",
        "message": "Page has low CTR (1.2%). Consider improving meta title and description.",
        "severity": "medium",
        "is_resolved": false,
        "created_at": "2025-10-13T05:30:00.000000Z"
      }
    ],
    "trends": [
      {
        "date": "2025-09-13",
        "clicks": 450,
        "impressions": 15234,
        "ctr": 2.95,
        "position": 12.1
      }
    ],
    "period": {
      "days": 30,
      "start_date": "2025-09-13",
      "end_date": "2025-10-13"
    }
  }
}
```

**Use Cases:**
- Display main dashboard
- Show performance trends
- Quick overview of SEO health

---

### 2.2 Get All Pages

**Endpoint:** `GET /api/seo/pages`

Retrieve all tracked pages with pagination and sorting.

**Request:**
```http
GET /api/seo/pages?per_page=50&sort_by=clicks&sort_order=desc
Authorization: Bearer {token}
```

**Query Parameters:**
| Parameter | Type | Default | Options | Description |
|-----------|------|---------|---------|-------------|
| `per_page` | integer | 50 | 10-100 | Items per page |
| `sort_by` | string | clicks | clicks, impressions, ctr, position | Sort field |
| `sort_order` | string | desc | asc, desc | Sort direction |

**Response (200):**
```json
{
  "status": "success",
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 1,
        "user_id": 1,
        "page_url": "https://example.com/page",
        "title": "Page Title",
        "clicks": 1234,
        "impressions": 45678,
        "ctr": 2.70,
        "position": 8.30,
        "last_fetched_at": "2025-10-13T05:30:00.000000Z",
        "created_at": "2025-10-01T00:00:00.000000Z",
        "updated_at": "2025-10-13T05:30:00.000000Z"
      }
    ],
    "first_page_url": "http://localhost:8000/api/seo/pages?page=1",
    "from": 1,
    "last_page": 3,
    "last_page_url": "http://localhost:8000/api/seo/pages?page=3",
    "links": [...],
    "next_page_url": "http://localhost:8000/api/seo/pages?page=2",
    "path": "http://localhost:8000/api/seo/pages",
    "per_page": 50,
    "prev_page_url": null,
    "to": 50,
    "total": 150
  }
}
```

**Use Cases:**
- Display pages list/table
- Sort and filter pages
- Identify underperforming pages

---

### 2.3 Get Page Detail

**Endpoint:** `GET /api/seo/page/{id}`

Get detailed information about a specific page including its recommendations.

**Request:**
```http
GET /api/seo/page/1
Authorization: Bearer {token}
```

**URL Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | integer | Yes | Page ID |

**Response (200):**
```json
{
  "status": "success",
  "data": {
    "page": {
      "id": 1,
      "user_id": 1,
      "page_url": "https://example.com/blog/seo-guide",
      "title": "Complete SEO Guide",
      "clicks": 1234,
      "impressions": 45678,
      "ctr": 2.70,
      "position": 8.30,
      "last_fetched_at": "2025-10-13T05:30:00.000000Z",
      "created_at": "2025-10-01T00:00:00.000000Z",
      "updated_at": "2025-10-13T05:30:00.000000Z"
    },
    "recommendations": [
      {
        "id": 1,
        "user_id": 1,
        "page_url": "https://example.com/blog/seo-guide",
        "recommendation_type": "low_ctr",
        "message": "Page has low CTR (2.7%). Consider improving meta title and description.",
        "severity": "medium",
        "is_resolved": false,
        "resolved_at": null,
        "created_at": "2025-10-13T05:30:00.000000Z",
        "updated_at": "2025-10-13T05:30:00.000000Z"
      }
    ]
  }
}
```

**Error Response (404):**
```json
{
  "status": "error",
  "message": "Page not found"
}
```

**Use Cases:**
- Individual page detail view
- Show page-specific recommendations
- Track page performance over time

---

### 2.4 Get All Recommendations

**Endpoint:** `GET /api/seo/recommendations`

Retrieve all unresolved SEO recommendations with optional filtering.

**Request:**
```http
GET /api/seo/recommendations?severity=high&type=low_ctr
Authorization: Bearer {token}
```

**Query Parameters:**
| Parameter | Type | Options | Description |
|-----------|------|---------|-------------|
| `severity` | string | low, medium, high | Filter by severity |
| `type` | string | Various | Filter by recommendation type |

**Response (200):**
```json
{
  "status": "success",
  "data": [
    {
      "id": 1,
      "user_id": 1,
      "page_url": "https://example.com/page",
      "recommendation_type": "low_ctr",
      "message": "Page has low CTR (1.2%). Consider improving meta title and description.",
      "severity": "medium",
      "is_resolved": false,
      "resolved_at": null,
      "created_at": "2025-10-13T05:30:00.000000Z",
      "updated_at": "2025-10-13T05:30:00.000000Z"
    }
  ]
}
```

**Recommendation Types:**
- `low_ctr` - Low click-through rate
- `wasted_impressions` - High impressions but low clicks
- `poor_ranking` - Poor search ranking position
- `underperforming_ranking` - Good ranking but low CTR
- `meta_description` - Missing or poor meta description
- `title_tag` - Title tag issues
- `performance` - Page speed issues

**Severity Levels:**
- `high` - Critical issues requiring immediate attention
- `medium` - Important issues to address soon
- `low` - Minor optimizations

**Use Cases:**
- Display recommendation list
- Prioritize SEO improvements
- Track resolved vs pending items

---

### 2.5 Update Settings

**Endpoint:** `POST /api/seo/settings`

Update SEO module settings.

**Request:**
```http
POST /api/seo/settings
Authorization: Bearer {token}
Content-Type: application/json

{
  "site_url": "https://newsite.com",
  "sync_frequency": "daily"
}
```

**Body Parameters:**
| Parameter | Type | Required | Options | Description |
|-----------|------|----------|---------|-------------|
| `site_url` | string (URL) | No | - | Update tracked site URL |
| `sync_frequency` | string | No | daily, weekly, manual | Sync frequency |

**Response (200):**
```json
{
  "status": "success",
  "message": "Settings updated successfully"
}
```

**Validation Error (422):**
```json
{
  "status": "error",
  "message": "Validation failed",
  "errors": {
    "site_url": ["The site url must be a valid URL."]
  }
}
```

---

### 2.6 Manual Sync

**Endpoint:** `POST /api/seo/sync`

Trigger manual data synchronization from Google Search Console.

**Request:**
```http
POST /api/seo/sync?days=30
Authorization: Bearer {token}
```

**Query Parameters:**
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `days` | integer | 30 | Number of days to sync |

**Response (200):**
```json
{
  "status": "success",
  "message": "Data synced successfully",
  "data": {
    "metrics_synced": {
      "success": true,
      "synced_days": 30
    },
    "pages_synced": {
      "success": true,
      "synced_pages": 142
    },
    "recommendations_generated": {
      "success": true,
      "recommendations_created": 23
    }
  }
}
```

**Use Cases:**
- Manual refresh button
- Force update after site changes
- Test sync functionality

---

## 3. Legacy Endpoints (Backward Compatible)

### 3.1 Get Metrics (Real-time)

**Endpoint:** `GET /api/seo/metrics`

Get real-time metrics directly from Google Search Console API (cached for 24 hours).

**Request:**
```http
GET /api/seo/metrics?days=7
Authorization: Bearer {token}
```

**Response:** See original implementation in previous documentation.

---

### 3.2 Get Settings (Status)

**Endpoint:** `GET /api/seo/settings`

Get connection status and settings.

**Response:**
```json
{
  "status": "success",
  "data": {
    "connected": true,
    "site_url": "https://example.com",
    "site_name": "example.com",
    "last_synced": "2025-10-13T05:30:00+00:00",
    "token_expires_at": "2025-10-20T05:30:00+00:00"
  }
}
```

---

## 📊 Database Schema

### seo_metrics Table
```sql
CREATE TABLE seo_metrics (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  user_id BIGINT NOT NULL,
  date DATE NOT NULL,
  clicks INT DEFAULT 0,
  impressions INT DEFAULT 0,
  ctr DECIMAL(5,2) DEFAULT 0,
  position DECIMAL(5,2) DEFAULT 0,
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  UNIQUE KEY unique_user_date (user_id, date),
  INDEX idx_date (date),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

### seo_pages Table
```sql
CREATE TABLE seo_pages (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  user_id BIGINT NOT NULL,
  page_url VARCHAR(2048) NOT NULL,
  title VARCHAR(255),
  clicks INT DEFAULT 0,
  impressions INT DEFAULT 0,
  ctr DECIMAL(5,2) DEFAULT 0,
  position DECIMAL(5,2) DEFAULT 0,
  last_fetched_at TIMESTAMP,
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  INDEX idx_user_id (user_id),
  INDEX idx_page_url (page_url),
  INDEX idx_last_fetched_at (last_fetched_at),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

### seo_recommendations Table
```sql
CREATE TABLE seo_recommendations (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  user_id BIGINT NOT NULL,
  page_url VARCHAR(2048),
  recommendation_type VARCHAR(255) NOT NULL,
  message TEXT NOT NULL,
  severity ENUM('low', 'medium', 'high') DEFAULT 'medium',
  is_resolved BOOLEAN DEFAULT FALSE,
  resolved_at TIMESTAMP NULL,
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  INDEX idx_user_id (user_id),
  INDEX idx_type (recommendation_type),
  INDEX idx_severity (severity),
  INDEX idx_resolved (is_resolved),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

### seo_tokens Table
```sql
CREATE TABLE seo_tokens (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  user_id BIGINT NOT NULL UNIQUE,
  access_token TEXT NOT NULL,
  refresh_token TEXT,
  expires_at TIMESTAMP,
  site_url VARCHAR(255),
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

---

## ⚙️ Environment Configuration

Add to `.env`:

```env
# Google Search Console Integration
GOOGLE_SEARCH_CLIENT_ID=your_client_id.apps.googleusercontent.com
GOOGLE_SEARCH_CLIENT_SECRET=your_client_secret
GOOGLE_SEARCH_REDIRECT_URI=http://localhost:8000/api/seo/google/callback
GOOGLE_SEARCH_SITE_URL=https://yourwebsite.com
SEO_API_ENABLED=true
```

---

## 🔄 Automated Sync (Cron Job)

### Artisan Command

```bash
# Sync all users
php artisan seo:sync

# Sync specific user
php artisan seo:sync --user=1

# Sync with custom days
php artisan seo:sync --days=60
```

### Schedule Configuration

Add to `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Sync SEO data daily at 2 AM
    $schedule->command('seo:sync')->dailyAt('02:00');
}
```

### Server Cron

```cron
# Add to crontab
0 2 * * * cd /path/to/project && php artisan seo:sync >> /dev/null 2>&1
```

---

## 🧪 Testing Examples

### cURL Examples

```bash
# 1. Get OAuth URL
curl -H "Authorization: Bearer TOKEN" \
  http://localhost:8000/api/seo/auth/google

# 2. Get Dashboard
curl -H "Authorization: Bearer TOKEN" \
  http://localhost:8000/api/seo/dashboard?days=30

# 3. Get Pages
curl -H "Authorization: Bearer TOKEN" \
  http://localhost:8000/api/seo/pages?per_page=50

# 4. Get Page Detail
curl -H "Authorization: Bearer TOKEN" \
  http://localhost:8000/api/seo/page/1

# 5. Get Recommendations
curl -H "Authorization: Bearer TOKEN" \
  http://localhost:8000/api/seo/recommendations?severity=high

# 6. Manual Sync
curl -X POST -H "Authorization: Bearer TOKEN" \
  http://localhost:8000/api/seo/sync

# 7. Update Settings
curl -X POST -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"site_url":"https://newsite.com"}' \
  http://localhost:8000/api/seo/settings
```

---

## 📝 Frontend Integration Examples

### Vue.js/React/Angular

```javascript
// Initialize OAuth
async function connectGoogleSearchConsole() {
  const { data } = await axios.get('/api/seo/auth/google');
  window.location.href = data.auth_url;
}

// Load Dashboard
async function loadDashboard() {
  const { data } = await axios.get('/api/seo/dashboard?days=30');
  console.log('Metrics:', data.data.summary);
  console.log('Top Pages:', data.data.top_pages);
  console.log('Recommendations:', data.data.recommendations);
}

// Load Pages with Pagination
async function loadPages(page = 1, sortBy = 'clicks') {
  const { data } = await axios.get(`/api/seo/pages`, {
    params: { page, sort_by: sortBy, per_page: 50 }
  });
  return data.data;
}

// Get Single Page
async function getPageDetail(id) {
  const { data } = await axios.get(`/api/seo/page/${id}`);
  return data.data;
}

// Get Recommendations
async function getRecommendations(severity = null) {
  const { data } = await axios.get('/api/seo/recommendations', {
    params: severity ? { severity } : {}
  });
  return data.data;
}

// Manual Sync
async function syncData() {
  const { data } = await axios.post('/api/seo/sync');
  console.log('Sync result:', data);
}

// Update Settings
async function updateSettings(settings) {
  const { data } = await axios.post('/api/seo/settings', settings);
  return data;
}
```

---

## 🐛 Error Codes & Handling

| Status Code | Meaning | Common Causes |
|-------------|---------|---------------|
| 200 | Success | Request processed successfully |
| 400 | Bad Request | Missing required parameters |
| 401 | Unauthorized | Invalid or missing auth token |
| 404 | Not Found | Resource doesn't exist |
| 422 | Validation Error | Invalid data format |
| 500 | Server Error | Internal processing error |

**Error Response Format:**
```json
{
  "status": "error",
  "message": "Human-readable error message",
  "errors": {
    "field_name": ["Specific validation error"]
  }
}
```

---

## 📈 Rate Limiting

- **Google Search Console API:** 1,200 queries/minute, 200/day (free tier)
- **Our Implementation:** 24-hour caching to minimize API usage
- **Local API:** No rate limits (uses cached data)

---

## 🔐 Security Considerations

✅ **OAuth tokens encrypted** in database  
✅ **Sanctum authentication** required for all endpoints  
✅ **Per-user data isolation**  
✅ **Hidden sensitive fields** in responses  
✅ **HTTPS required** in production  
✅ **CSRF protection** on POST/PUT/DELETE  

---

## ✅ Delivery Checklist

- [x] Database migrations created and tested
- [x] Models with relationships implemented
- [x] Controller methods for all endpoints
- [x] Routes registered and tested
- [x] Google OAuth integration working
- [x] Daily sync command created
- [x] Caching implemented
- [x] Error handling comprehensive
- [x] API documentation complete
- [x] Backward compatibility maintained

---

## 🎉 Status

**✅ PRODUCTION READY**

All endpoints are implemented, tested, and documented. The system is ready for frontend integration and production deployment.

---

**Document Version:** 2.0.0  
**API Version:** 2.0.0  
**Last Updated:** October 13, 2025



