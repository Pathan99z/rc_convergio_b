# 🎨 SEO Frontend Integration - Quick Reference

## ✅ Issue Fixed: `/api/seo/connect` Endpoint Added

Your frontend was calling `POST /api/seo/connect`, which didn't exist. This has now been **fixed and fully implemented**.

---

## 🔌 Available Endpoints

### 1. **Connect to Google Search Console**

**POST** `/api/seo/connect`

**What it does:** Returns the Google OAuth authorization URL

**Request:**
```javascript
const response = await axios.post('/api/seo/connect', {}, {
  headers: {
    'Authorization': 'Bearer YOUR_TOKEN'
  }
});
```

**Response (Success):**
```json
{
  "success": true,
  "status": "redirect_required",
  "auth_url": "https://accounts.google.com/o/oauth2/auth?client_id=...",
  "message": "Please authorize access to Google Search Console"
}
```

**Your Frontend Should:**
```javascript
// After receiving response
if (response.data.success && response.data.auth_url) {
  // Redirect user to Google OAuth
  window.location.href = response.data.auth_url;
}
```

---

### 2. **Check Connection Status**

**GET** `/api/seo/connect`

**What it does:** Checks if user has already connected Google Search Console

**Request:**
```javascript
const response = await axios.get('/api/seo/connect');
```

**Response (Connected):**
```json
{
  "success": true,
  "connected": true,
  "site_url": "https://yoursite.com",
  "expires_at": "2025-10-20T05:30:00+00:00",
  "is_expired": false,
  "message": "Connected to Google Search Console"
}
```

**Response (Not Connected):**
```json
{
  "success": true,
  "connected": false,
  "message": "Google Search Console not connected"
}
```

**Your Frontend Should:**
```javascript
// Check connection status on page load
const checkConnection = async () => {
  const { data } = await axios.get('/api/seo/connect');
  
  if (data.connected) {
    // User is connected - load dashboard data
    loadDashboardData();
  } else {
    // Show "Connect Google Search Console" button
    showConnectButton();
  }
};
```

---

## 🎯 Complete Frontend Flow

### Vue.js Example

```vue
<template>
  <div class="seo-settings">
    <h2>SEO Settings</h2>
    <p>Configure your SEO tools and integrations</p>

    <!-- Connection Status Card -->
    <div v-if="!loading" class="connection-card">
      <!-- Not Connected -->
      <div v-if="!isConnected" class="not-connected">
        <h3>Google Search Console</h3>
        <p>Connect to unlock full SEO features:</p>
        <ul>
          <li>Real-time search performance data</li>
          <li>Keyword rankings and trends</li>
          <li>Page-level analytics</li>
          <li>Automated SEO recommendations</li>
        </ul>
        <button 
          @click="connectGoogleSearchConsole" 
          :disabled="connecting"
          class="btn-primary">
          {{ connecting ? 'Connecting...' : 'Connect Google Search Console' }}
        </button>
      </div>

      <!-- Connected -->
      <div v-else class="connected">
        <h3>✓ Google Search Console Connected</h3>
        <p>Site: {{ connectionInfo.site_url }}</p>
        <p>Status: {{ connectionInfo.is_expired ? 'Expired' : 'Active' }}</p>
        <button v-if="connectionInfo.is_expired" @click="reconnect">
          Reconnect
        </button>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="loading">
      Checking connection status...
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';

const loading = ref(true);
const connecting = ref(false);
const isConnected = ref(false);
const connectionInfo = ref(null);

// Check connection status on mount
onMounted(async () => {
  await checkConnectionStatus();
});

// Check if already connected
async function checkConnectionStatus() {
  loading.value = true;
  try {
    const { data } = await axios.get('/api/seo/connect');
    
    isConnected.value = data.connected;
    if (data.connected) {
      connectionInfo.value = data;
    }
  } catch (error) {
    console.error('Failed to check connection:', error);
  } finally {
    loading.value = false;
  }
}

// Connect to Google Search Console
async function connectGoogleSearchConsole() {
  connecting.value = true;
  try {
    const { data } = await axios.post('/api/seo/connect');
    
    if (data.success && data.auth_url) {
      // Redirect to Google OAuth
      window.location.href = data.auth_url;
    } else {
      alert('Failed to initiate connection');
    }
  } catch (error) {
    console.error('Connection error:', error);
    alert('Failed to connect. Please try again.');
  } finally {
    connecting.value = false;
  }
}

// Reconnect if expired
async function reconnect() {
  await connectGoogleSearchConsole();
}
</script>

<style scoped>
.connection-card {
  border: 1px solid #e0e0e0;
  border-radius: 8px;
  padding: 24px;
  margin: 20px 0;
}

.not-connected {
  text-align: center;
}

.not-connected ul {
  text-align: left;
  display: inline-block;
  margin: 20px 0;
}

.connected {
  background: #f0f9ff;
  border-color: #3b82f6;
}

.btn-primary {
  background: #3b82f6;
  color: white;
  padding: 12px 24px;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-size: 16px;
}

.btn-primary:hover {
  background: #2563eb;
}

.btn-primary:disabled {
  background: #9ca3af;
  cursor: not-allowed;
}

.loading {
  text-align: center;
  padding: 40px;
  color: #6b7280;
}
</style>
```

---

## 🔄 OAuth Callback Handling

After user authorizes on Google, they'll be redirected to:
```
http://localhost:8000/api/seo/google/callback?code=...&state=...
```

You need to handle this on your backend (already implemented) and then redirect back to your frontend with a success message.

**Optional: Add a callback page in your Vue.js app**

Create a page at `/marketing/seo/callback`:

```vue
<template>
  <div class="callback-page">
    <div v-if="status === 'processing'">
      <h2>Connecting to Google Search Console...</h2>
      <p>Please wait while we complete the authorization.</p>
    </div>
    
    <div v-if="status === 'success'">
      <h2>✓ Successfully Connected!</h2>
      <p>Redirecting to SEO dashboard...</p>
    </div>
    
    <div v-if="status === 'error'">
      <h2>Connection Failed</h2>
      <p>{{ errorMessage }}</p>
      <button @click="retry">Try Again</button>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';

const route = useRoute();
const router = useRouter();
const status = ref('processing');
const errorMessage = ref('');

onMounted(() => {
  // Check if there's a success/error parameter
  if (route.query.success) {
    status.value = 'success';
    // Redirect to dashboard after 2 seconds
    setTimeout(() => {
      router.push('/marketing/seo');
    }, 2000);
  } else if (route.query.error) {
    status.value = 'error';
    errorMessage.value = route.query.error;
  }
});

function retry() {
  router.push('/marketing/seo/settings');
}
</script>
```

---

## 🧪 Testing

### Test Connection Status
```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
  http://localhost:8000/api/seo/connect
```

**Expected:** Connection status with `connected: true/false`

### Test Initiate Connection
```bash
curl -X POST \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  http://localhost:8000/api/seo/connect
```

**Expected:** OAuth URL in response

---

## 🔧 Environment Setup Required

Make sure your `.env` has:

```env
GOOGLE_SEARCH_CLIENT_ID=your_client_id.apps.googleusercontent.com
GOOGLE_SEARCH_CLIENT_SECRET=your_secret
GOOGLE_SEARCH_REDIRECT_URI=http://localhost:8000/api/seo/google/callback
GOOGLE_SEARCH_SITE_URL=https://yourwebsite.com
SEO_API_ENABLED=true
```

**Get credentials:**
1. Go to https://console.cloud.google.com/
2. Enable "Google Search Console API"
3. Create OAuth 2.0 credentials
4. Add redirect URI: `http://localhost:8000/api/seo/google/callback`

---

## ✅ What's Fixed

✅ **POST `/api/seo/connect`** - Initiates OAuth flow  
✅ **GET `/api/seo/connect`** - Checks connection status  
✅ Response format matches frontend expectations  
✅ Proper error handling  
✅ Token expiration checking  

---

## 🎉 Ready to Test!

Your frontend should now work when you click the "Connect Google Search Console" button!

**Flow:**
1. User visits `/marketing/seo/settings`
2. Frontend calls `GET /api/seo/connect` to check status
3. Shows "Connect" button if not connected
4. User clicks button → Frontend calls `POST /api/seo/connect`
5. Frontend redirects to `auth_url` from response
6. User authorizes on Google
7. Google redirects back to `/api/seo/google/callback`
8. Backend stores tokens and redirects to your frontend
9. Frontend refreshes and shows connected status

---

**Status:** ✅ Ready for Integration  
**Last Updated:** October 13, 2025



