# SEO Tools / Google Search Console Integration - Setup Guide

## 🎯 Overview

This module provides a complete integration with **Google Search Console API** (Webmasters API) for your Laravel backend, featuring:

- ✅ Google OAuth 2.0 authentication
- ✅ Real-time SEO metrics (impressions, clicks, CTR, position)
- ✅ Top pages and queries analysis
- ✅ SEO recommendations engine
- ✅ Daily caching to minimize API quota usage
- ✅ Token refresh handling
- ✅ Production-ready error handling

---

## 📋 Prerequisites

1. **Google Cloud Console Project** with Search Console API enabled
2. **OAuth 2.0 credentials** (Client ID and Client Secret)
3. **Laravel 10+** with PHP 8.1+
4. **MySQL database** for token storage

---

## 🚀 Installation & Setup

### Step 1: Google Cloud Console Setup

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select an existing one
3. Enable the **Google Search Console API**:
   - Navigate to **APIs & Services** → **Library**
   - Search for "Google Search Console API"
   - Click **Enable**

4. Create OAuth 2.0 credentials:
   - Go to **APIs & Services** → **Credentials**
   - Click **Create Credentials** → **OAuth 2.0 Client ID**
   - Application type: **Web application**
   - Authorized redirect URIs:
     ```
     http://localhost:8000/api/seo/oauth/callback
     https://yourdomain.com/api/seo/oauth/callback
     ```
   - Click **Create**
   - Download the JSON file or copy the **Client ID** and **Client Secret**

### Step 2: Environment Configuration

Add the following to your `.env` file:

```env
# Google Search Console API Configuration
GOOGLE_CLIENT_ID=your_client_id_here
GOOGLE_CLIENT_SECRET=your_client_secret_here
GOOGLE_REDIRECT_URI=http://localhost:8000/api/seo/oauth/callback
```

**Production Example:**
```env
GOOGLE_CLIENT_ID=123456789-abcdefghijklmnop.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=GOCSPX-aBcDeFgHiJkLmNoPqRsTuVwXyZ
GOOGLE_REDIRECT_URI=https://yourdomain.com/api/seo/oauth/callback
```

### Step 3: Alternative - JSON File Configuration

If you prefer using a JSON file, place your Google credentials at:
```
storage/app/google/client_secret.json
```

**File structure:**
```json
{
  "web": {
    "client_id": "your_client_id.apps.googleusercontent.com",
    "client_secret": "your_client_secret",
    "redirect_uris": ["http://localhost:8000/api/seo/oauth/callback"],
    "auth_uri": "https://accounts.google.com/o/oauth2/auth",
    "token_uri": "https://oauth2.googleapis.com/token"
  }
}
```

### Step 4: Run Migrations

```bash
php artisan migrate
```

This creates/updates the `user_seo_sites` table with OAuth token storage fields.

---

## 📡 API Endpoints

All endpoints require authentication (`auth:sanctum` middleware).

### 1. **OAuth Flow**

#### Initiate OAuth
```http
GET /api/seo/oauth/redirect
```

**Response:**
```json
{
  "status": "success",
  "auth_url": "https://accounts.google.com/o/oauth2/auth?client_id=..."
}
```

**Frontend Usage:**
```javascript
// Get auth URL
const response = await fetch('/api/seo/oauth/redirect');
const { auth_url } = await response.json();

// Redirect user to Google
window.location.href = auth_url;
```

#### Handle OAuth Callback
```http
GET /api/seo/oauth/callback?code={auth_code}&site_url={site_url}
```

**Parameters:**
- `code` (required): Authorization code from Google
- `site_url` (required): Website URL (e.g., https://example.com)

**Response:**
```json
{
  "status": "success",
  "message": "Successfully connected to Google Search Console",
  "site": {
    "id": 1,
    "user_id": 1,
    "site_url": "https://example.com",
    "site_name": "example.com",
    "is_connected": true,
    "last_synced": "2025-10-13T05:30:00.000000Z"
  }
}
```

---

### 2. **Get SEO Metrics**

```http
GET /api/seo/metrics?site_url={site_url}&days={days}
```

**Parameters:**
- `site_url` (optional): Filter by specific site
- `days` (optional, default: 7): Number of days to fetch

**Response:**
```json
{
  "status": "success",
  "data": {
    "totalClicks": 1234,
    "totalImpressions": 45678,
    "averageCTR": 2.70,
    "averagePosition": 12.5,
    "keywords": [
      {
        "keyword": "best seo tools",
        "clicks": 123,
        "impressions": 4567,
        "ctr": 2.69,
        "position": 8.3
      },
      ...
    ]
  }
}
```

**Caching:** Results are cached for 24 hours.

---

### 3. **Get Page Performance**

```http
GET /api/seo/pages?site_url={site_url}&days={days}
```

**Parameters:**
- `site_url` (optional): Filter by specific site
- `days` (optional, default: 7): Number of days to fetch

**Response:**
```json
{
  "status": "success",
  "data": [
    {
      "url": "https://example.com/blog/seo-guide",
      "clicks": 234,
      "impressions": 5678,
      "ctr": 4.12,
      "position": 6.7
    },
    ...
  ]
}
```

---

### 4. **Get SEO Recommendations**

```http
GET /api/seo/recommendations?site_url={site_url}
```

**Response:**
```json
{
  "status": "success",
  "data": [
    {
      "title": "Optimize title tags with target keywords",
      "priority": "High",
      "description": "Include primary keywords in page titles for better rankings"
    },
    {
      "title": "Add meta description to Homepage",
      "priority": "High",
      "page": "https://example.com/",
      "description": "Meta descriptions improve click-through rates from search results"
    },
    ...
  ]
}
```

---

### 5. **Get Settings / Connection Status**

```http
GET /api/seo/settings?site_url={site_url}
```

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

## 🔧 Frontend Integration Example

### Vue.js Component

```vue
<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';

const metrics = ref(null);
const pages = ref([]);
const recommendations = ref([]);
const settings = ref(null);
const loading = ref(false);

// Connect to Google Search Console
async function connectGSC() {
  try {
    const { data } = await axios.get('/api/seo/oauth/redirect');
    
    // Store site URL for callback
    localStorage.setItem('seo_site_url', 'https://example.com');
    
    // Redirect to Google OAuth
    window.location.href = data.auth_url;
  } catch (error) {
    console.error('Failed to initiate OAuth:', error);
  }
}

// Load SEO data
async function loadSEOData() {
  loading.value = true;
  try {
    const [metricsRes, pagesRes, recsRes, settingsRes] = await Promise.all([
      axios.get('/api/seo/metrics?days=7'),
      axios.get('/api/seo/pages?days=7'),
      axios.get('/api/seo/recommendations'),
      axios.get('/api/seo/settings')
    ]);
    
    metrics.value = metricsRes.data.data;
    pages.value = pagesRes.data.data;
    recommendations.value = recsRes.data.data;
    settings.value = settingsRes.data.data;
  } catch (error) {
    console.error('Failed to load SEO data:', error);
  } finally {
    loading.value = false;
  }
}

onMounted(() => {
  loadSEOData();
});
</script>

<template>
  <div class="seo-dashboard">
    <!-- Connection Status -->
    <div v-if="settings && !settings.connected" class="alert">
      <p>Connect to Google Search Console to view SEO data</p>
      <button @click="connectGSC">Connect Google Account</button>
    </div>

    <!-- Metrics -->
    <div v-if="metrics" class="metrics-grid">
      <div class="metric-card">
        <h3>Total Clicks</h3>
        <p class="metric-value">{{ metrics.totalClicks.toLocaleString() }}</p>
      </div>
      <div class="metric-card">
        <h3>Total Impressions</h3>
        <p class="metric-value">{{ metrics.totalImpressions.toLocaleString() }}</p>
      </div>
      <div class="metric-card">
        <h3>Average CTR</h3>
        <p class="metric-value">{{ metrics.averageCTR }}%</p>
      </div>
      <div class="metric-card">
        <h3>Average Position</h3>
        <p class="metric-value">{{ metrics.averagePosition }}</p>
      </div>
    </div>

    <!-- Top Keywords -->
    <div v-if="metrics" class="keywords-table">
      <h2>Top Keywords</h2>
      <table>
        <thead>
          <tr>
            <th>Keyword</th>
            <th>Clicks</th>
            <th>Impressions</th>
            <th>CTR</th>
            <th>Position</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="keyword in metrics.keywords" :key="keyword.keyword">
            <td>{{ keyword.keyword }}</td>
            <td>{{ keyword.clicks }}</td>
            <td>{{ keyword.impressions }}</td>
            <td>{{ keyword.ctr }}%</td>
            <td>{{ keyword.position }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Top Pages -->
    <div v-if="pages.length" class="pages-table">
      <h2>Top Pages</h2>
      <table>
        <thead>
          <tr>
            <th>Page</th>
            <th>Clicks</th>
            <th>Impressions</th>
            <th>CTR</th>
            <th>Position</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="page in pages" :key="page.url">
            <td><a :href="page.url" target="_blank">{{ page.url }}</a></td>
            <td>{{ page.clicks }}</td>
            <td>{{ page.impressions }}</td>
            <td>{{ page.ctr }}%</td>
            <td>{{ page.position }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Recommendations -->
    <div v-if="recommendations.length" class="recommendations">
      <h2>SEO Recommendations</h2>
      <div v-for="(rec, index) in recommendations" :key="index" class="recommendation-card">
        <span :class="['priority', rec.priority.toLowerCase()]">{{ rec.priority }}</span>
        <h3>{{ rec.title }}</h3>
        <p>{{ rec.description }}</p>
        <a v-if="rec.page" :href="rec.page" target="_blank">View Page</a>
      </div>
    </div>
  </div>
</template>
```

---

## 🧪 Testing

### Manual Testing Steps

1. **Start Laravel server:**
   ```bash
   php artisan serve
   ```

2. **Test OAuth redirect:**
   ```bash
   curl -H "Authorization: Bearer YOUR_TOKEN" \
     http://localhost:8000/api/seo/oauth/redirect
   ```

3. **Visit the auth URL** in a browser and authorize

4. **After callback, test metrics:**
   ```bash
   curl -H "Authorization: Bearer YOUR_TOKEN" \
     http://localhost:8000/api/seo/metrics
   ```

5. **Test pages endpoint:**
   ```bash
   curl -H "Authorization: Bearer YOUR_TOKEN" \
     http://localhost:8000/api/seo/pages?days=30
   ```

---

## 🔐 Security Best Practices

1. **Always use HTTPS** in production for OAuth callbacks
2. **Store tokens securely** - they're hidden in API responses
3. **Set proper CORS headers** for your frontend domain
4. **Validate redirect URIs** match your registered URIs
5. **Use environment variables** for credentials (never commit to git)

---

## 📊 API Quota Management

Google Search Console API has the following limits:
- **1,200 queries per minute**
- **200 queries per day** (free tier)

**Our caching strategy:**
- Metrics cached for 24 hours
- Pages cached for 24 hours
- Clear cache on demand with `GoogleSearchConsoleService::clearCache($site)`

---

## 🐛 Troubleshooting

### Error: "No access token available"
**Solution:** User needs to reconnect via OAuth flow

### Error: "Token expired and no refresh token available"
**Solution:** Re-authenticate (this shouldn't happen with our auto-refresh)

### Error: "Google OAuth callback error"
**Solutions:**
- Check redirect URI matches Google Cloud Console
- Verify API is enabled in Google Cloud Console
- Check credentials are correct in `.env`

### Error: "Failed to fetch SEO metrics"
**Solutions:**
- Verify site is added to Google Search Console
- Check user has permission to view the site
- Ensure site URL format matches exactly (http vs https, www vs non-www)

---

## 🔄 Token Refresh

The system automatically refreshes expired tokens. To manually clear cache and force fresh data:

```php
use App\Services\GoogleSearchConsoleService;

$service = app(GoogleSearchConsoleService::class);
$service->clearCache($site);
```

---

## 📝 Database Schema

**user_seo_sites table:**
```sql
- id (bigint, primary key)
- user_id (bigint, foreign key)
- site_url (string)
- site_name (string, nullable)
- is_connected (boolean, default: false)
- gsc_property (string, nullable)
- google_access_token (text, nullable, hidden)
- google_refresh_token (text, nullable, hidden)
- google_token_expires_at (timestamp, nullable)
- last_synced (timestamp, nullable)
- crawl_data (json, nullable)
- created_at (timestamp)
- updated_at (timestamp)
- UNIQUE(user_id, site_url)
```

---

## 🎉 Features Summary

✅ **Production-ready** - Error handling, logging, validation
✅ **Secure** - OAuth 2.0, token encryption, hidden sensitive data
✅ **Performant** - 24-hour caching, automatic token refresh
✅ **Scalable** - Multi-site support per user
✅ **Developer-friendly** - Clean API, comprehensive docs
✅ **Backward compatible** - Doesn't break existing functionality

---

## 📚 Additional Resources

- [Google Search Console API Documentation](https://developers.google.com/webmaster-tools/search-console-api-original)
- [OAuth 2.0 Flow](https://developers.google.com/identity/protocols/oauth2)
- [Laravel Sanctum Authentication](https://laravel.com/docs/sanctum)

---

## 🆘 Support

For issues or questions:
1. Check the troubleshooting section above
2. Review Laravel logs: `storage/logs/laravel.log`
3. Enable debug mode: `APP_DEBUG=true` in `.env`
4. Check Google Cloud Console API quotas

---

**Last Updated:** October 13, 2025
**Version:** 1.0.0
**Status:** ✅ Production Ready



