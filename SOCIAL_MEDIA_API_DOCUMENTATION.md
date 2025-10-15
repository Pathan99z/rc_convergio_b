# Social Media Management Tool - API Documentation

## Overview

This is a comprehensive backend API for a Social Media Management Tool built with Laravel 10. It supports multi-platform social media posting, scheduling, analytics, and social listening.

## Features

✅ **Authentication** - JWT/Sanctum token-based authentication  
✅ **OAuth Integration** - Connect Facebook, Instagram, Twitter/X, LinkedIn accounts  
✅ **Post Management** - Create, schedule, and publish posts across platforms  
✅ **Analytics** - Track engagement metrics (likes, shares, followers, impressions)  
✅ **Social Listening** - Monitor keywords and mentions across platforms  
✅ **User Profiles** - Manage user account information  
✅ **Auto-Publishing** - Scheduled posts published automatically via Laravel Scheduler

---

## Base URL

```
http://localhost:8000/api
```

---

## Authentication Endpoints

### 1. Register New User
**POST** `/auth/register`

**Request Body:**
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "SecurePassword123",
  "password_confirmation": "SecurePassword123",
  "organization_name": "My Company"
}
```

**Response:**
```json
{
  "success": true,
  "access_token": "1|abcdefg...",
  "expires_at": "2025-10-15T12:00:00Z",
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "organization_name": "My Company"
  }
}
```

### 2. Login
**POST** `/auth/login`

**Request Body:**
```json
{
  "email": "john@example.com",
  "password": "SecurePassword123"
}
```

**Response:**
```json
{
  "success": true,
  "access_token": "1|abcdefg...",
  "expires_at": "2025-10-15T12:00:00Z",
  "user": { ... }
}
```

### 3. Get Authenticated User
**GET** `/auth/me`

**Headers:**
```
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "organization_name": "My Company"
  }
}
```

### 4. Logout
**POST** `/auth/logout`

**Headers:**
```
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "message": "Successfully logged out"
}
```

---

## Social Media Integration (OAuth)

### 1. Connect Social Account
**POST** `/social/connect/{platform}`

**Platforms:** `facebook`, `instagram`, `twitter`, `linkedin`

**Request Body:**
```json
{
  "redirect_uri": "http://localhost:5173/social-media/callback"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "auth_url": "https://facebook.com/oauth/authorize?...",
    "platform": "facebook"
  }
}
```

### 2. OAuth Callback (After User Authorization)
**POST** `/social/callback/{platform}`

**Request Body:**
```json
{
  "code": "authorization_code_from_platform",
  "state": "optional_state_parameter"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Facebook account connected successfully",
  "data": {
    "platform": "facebook",
    "username": "JohnDoe",
    "connected_at": "2025-10-14T10:00:00Z"
  }
}
```

### 3. Get Connected Accounts
**GET** `/social/accounts`

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "platform": "facebook",
      "username": "JohnDoe",
      "platform_user_id": "12345",
      "connected_at": "2025-10-14T10:00:00Z",
      "expires_at": "2025-12-14T10:00:00Z",
      "is_expired": false
    }
  ]
}
```

### 4. Disconnect Account
**DELETE** `/social/disconnect/{platform}`

**Response:**
```json
{
  "success": true,
  "message": "Facebook account disconnected successfully"
}
```

---

## Post Scheduling and Publishing

### 1. Schedule a Post
**POST** `/social/schedule-post`

**Request Body:**
```json
{
  "title": "My Awesome Post",
  "content": "Check out our latest product!",
  "platform": "facebook",
  "scheduled_at": "2025-10-15 14:00:00",
  "hashtags": ["#marketing", "#socialmedia"],
  "media_urls": ["https://example.com/image.jpg"],
  "mentions": ["@friend"],
  "location": "New York, NY"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Social media post created successfully",
  "data": {
    "id": 1,
    "title": "My Awesome Post",
    "content": "Check out our latest product!",
    "platform": "facebook",
    "status": "scheduled",
    "scheduled_at": "2025-10-15T14:00:00Z"
  }
}
```

### 2. Publish Post Immediately
**POST** `/social/posts/{id}/publish`

**Response:**
```json
{
  "success": true,
  "message": "Post published successfully",
  "data": {
    "id": 1,
    "status": "published",
    "published_at": "2025-10-14T10:30:00Z",
    "external_post_id": "fb_123456"
  },
  "platform_url": "https://facebook.com/posts/123456"
}
```

### 3. Get All Posts
**GET** `/social/posts`

**Query Parameters:**
- `platform` - Filter by platform
- `status` - Filter by status (draft, scheduled, published, failed)
- `search` - Search in title/content
- `per_page` - Items per page (default: 15)
- `page` - Page number

**Response:**
```json
{
  "success": true,
  "data": [ ... ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 15,
    "total": 72
  }
}
```

### 4. Get Single Post
**GET** `/social/posts/{id}`

### 5. Update Post
**PUT** `/social/posts/{id}`

### 6. Delete Post
**DELETE** `/social/posts/{id}`

---

## Analytics Tracking

### 1. Get Platform Analytics
**GET** `/social/analytics/{platform}`

**Platforms:** `facebook`, `instagram`, `twitter`, `linkedin`, `all`

**Query Parameters:**
- `date_from` - Start date (YYYY-MM-DD)
- `date_to` - End date (YYYY-MM-DD)

**Response:**
```json
{
  "success": true,
  "data": {
    "platform": "facebook",
    "metrics": {
      "followers": 1520,
      "page_impressions": 45230,
      "page_engaged_users": 3420,
      "page_views": 12340
    },
    "last_updated": "2025-10-14T10:00:00Z"
  }
}
```

### 2. Get Post Metrics
**GET** `/social/posts/{id}/metrics`

**Response:**
```json
{
  "success": true,
  "data": {
    "post_id": 1,
    "platform": "facebook",
    "metrics": {
      "likes": 245,
      "comments": 32,
      "shares": 18,
      "reactions": 267,
      "impressions": 5420,
      "updated_at": "2025-10-14T10:00:00Z"
    }
  }
}
```

### 3. Get Social Media Dashboard
**GET** `/social/dashboard`

**Response:**
```json
{
  "success": true,
  "data": {
    "recent_posts": [ ... ],
    "platform_stats": {
      "facebook": 45,
      "twitter": 32,
      "instagram": 28
    },
    "status_stats": {
      "published": 85,
      "scheduled": 15,
      "draft": 5
    },
    "engagement_summary": {
      "total_likes": 2450,
      "total_comments": 340,
      "total_shares": 180,
      "average_engagement": 28.7
    },
    "total_posts": 105
  }
}
```

---

## Social Listening

### 1. Create Listening Keyword
**POST** `/social/listen`

**Request Body:**
```json
{
  "keyword": "my brand",
  "platforms": ["twitter", "facebook"],
  "settings": {
    "sentiment_analysis": true,
    "min_followers": 100
  }
}
```

**Response:**
```json
{
  "success": true,
  "message": "Listening keyword created successfully",
  "data": {
    "id": 1,
    "keyword": "my brand",
    "platforms": ["twitter", "facebook"],
    "is_active": true,
    "mention_count": 0
  }
}
```

### 2. Get All Listening Keywords
**GET** `/social/listen`

### 3. Search for Mentions
**POST** `/social/listen/{id}/search`

**Response:**
```json
{
  "success": true,
  "data": {
    "keyword": "my brand",
    "mentions": [
      {
        "platform": "twitter",
        "content": "Just discovered @mybrand! Amazing product!",
        "author_name": "Jane Smith",
        "author_handle": "@janesmith",
        "post_url": "https://twitter.com/user/status/123456",
        "sentiment": "positive",
        "engagement": {
          "likes": 15,
          "retweets": 3,
          "replies": 2
        },
        "mentioned_at": "2025-10-14T09:30:00Z"
      }
    ],
    "total_found": 1
  }
}
```

### 4. Get Mentions for Keyword
**GET** `/social/listen/{id}/mentions`

**Query Parameters:**
- `platform` - Filter by platform
- `sentiment` - Filter by sentiment (positive, neutral, negative)
- `is_read` - Filter by read status
- `limit` - Max results (default: 50)

### 5. Get Sentiment Summary
**GET** `/social/listen/sentiment`

**Response:**
```json
{
  "success": true,
  "data": {
    "total": 150,
    "positive": 95,
    "positive_percentage": 63.33,
    "neutral": 42,
    "neutral_percentage": 28.00,
    "negative": 13,
    "negative_percentage": 8.67
  }
}
```

---

## User Profile

### 1. Get User Profile
**GET** `/user/profile`

### 2. Update User Profile
**PUT** `/user/profile`

**Request Body:**
```json
{
  "name": "John Doe Updated",
  "email": "newemail@example.com",
  "organization_name": "New Company Name",
  "password": "NewPassword123",
  "password_confirmation": "NewPassword123"
}
```

---

## Scheduler Commands

The following Laravel commands are scheduled to run automatically:

### Publish Scheduled Posts
```bash
php artisan social-media:publish-scheduled
```
Runs every 5 minutes to publish scheduled posts that are due.

### Sync Analytics
```bash
php artisan social-media:sync-analytics
```
Runs daily to fetch and store analytics from all connected platforms.

---

## Rate Limiting

API endpoints are rate-limited per platform to avoid hitting third-party API limits:

- **Facebook:** 200 requests/hour
- **Instagram:** 200 requests/hour
- **Twitter:** 180 requests/15 minutes
- **LinkedIn:** 100 requests/day

---

## Error Responses

All error responses follow this format:

```json
{
  "success": false,
  "message": "Error message here",
  "error": "Detailed error (only in debug mode)"
}
```

**Common HTTP Status Codes:**
- `200` - Success
- `201` - Created
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `422` - Validation Error
- `500` - Internal Server Error

---

## Environment Configuration

Required environment variables in `.env`:

```env
# Frontend URL
FRONTEND_URL=http://localhost:5173

# Instagram
INSTAGRAM_CLIENT_ID=your_client_id
INSTAGRAM_CLIENT_SECRET=your_client_secret

# Facebook
FACEBOOK_APP_ID=your_app_id
FACEBOOK_APP_SECRET=your_app_secret

# Twitter
TWITTER_CLIENT_ID=your_client_id
TWITTER_CLIENT_SECRET=your_client_secret
TWITTER_BEARER_TOKEN=your_bearer_token

# LinkedIn
LINKEDIN_CLIENT_ID=your_client_id
LINKEDIN_CLIENT_SECRET=your_client_secret
```

---

## Setup Instructions

1. **Install Dependencies**
```bash
composer install
```

2. **Configure Environment**
```bash
cp .env.example .env
php artisan key:generate
```

3. **Run Migrations**
```bash
php artisan migrate
```

4. **Start Queue Worker**
```bash
php artisan queue:work
```

5. **Configure Scheduler (crontab)**
```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

---

## Platform API Documentation Links

- **Facebook:** https://developers.facebook.com/docs/graph-api/
- **Instagram:** https://developers.facebook.com/docs/instagram-api/
- **Twitter:** https://developer.twitter.com/en/docs/twitter-api
- **LinkedIn:** https://docs.microsoft.com/en-us/linkedin/

---

## Security Notes

- All OAuth tokens are encrypted before storage
- JWT tokens expire after 60 minutes (configurable)
- Rate limiting is enforced on all endpoints
- Input validation and sanitization on all requests
- CORS configured for frontend domain only

---

## Support

For issues or questions, please check the main README.md or contact the development team.


