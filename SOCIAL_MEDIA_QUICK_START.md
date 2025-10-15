# Social Media Management Tool - Quick Start Guide

## 🚀 Quick Start (5 Minutes)

### Step 1: Run Migrations
```bash
php artisan migrate
```

This will create 4 new tables:
- `social_accounts` - OAuth connections
- `social_media_analytics` - Analytics data
- `listening_keywords` - Listening keywords
- `listening_mentions` - Captured mentions

### Step 2: Configure Environment

Update your `.env` file with social media credentials:

```env
# Frontend URL (for OAuth callbacks)
FRONTEND_URL=http://localhost:5173

# Facebook
FACEBOOK_APP_ID=your_app_id_here
FACEBOOK_APP_SECRET=your_app_secret_here

# Instagram  
INSTAGRAM_CLIENT_ID=your_client_id_here
INSTAGRAM_CLIENT_SECRET=your_client_secret_here

# Twitter/X
TWITTER_CLIENT_ID=your_client_id_here
TWITTER_CLIENT_SECRET=your_client_secret_here
TWITTER_BEARER_TOKEN=your_bearer_token_here

# LinkedIn
LINKEDIN_CLIENT_ID=your_client_id_here
LINKEDIN_CLIENT_SECRET=your_client_secret_here
```

### Step 3: Test the API

#### Register a User
```bash
POST http://localhost:8000/api/auth/register
Content-Type: application/json

{
  "name": "Test User",
  "email": "test@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "organization_name": "Test Company"
}
```

#### Connect Social Account
```bash
POST http://localhost:8000/api/social/connect/facebook
Authorization: Bearer {your_token}
Content-Type: application/json

{
  "redirect_uri": "http://localhost:5173/social-media/callback"
}
```

Response will include `auth_url` - redirect user to this URL.

#### Create a Scheduled Post
```bash
POST http://localhost:8000/api/social/schedule-post
Authorization: Bearer {your_token}
Content-Type: application/json

{
  "title": "My First Post",
  "content": "Testing the social media API!",
  "platform": "facebook",
  "scheduled_at": "2025-10-15 14:00:00",
  "hashtags": ["#test", "#api"]
}
```

### Step 4: Set Up Scheduler

Add to crontab:
```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

This enables:
- Auto-publishing scheduled posts (every 5 minutes)
- Daily analytics sync

### Step 5: Start Queue Worker

```bash
php artisan queue:work
```

Or use supervisor for production.

---

## 📍 Key API Endpoints

### Authentication
```
POST   /api/auth/register
POST   /api/auth/login
GET    /api/auth/me
POST   /api/auth/logout
```

### Social Media - OAuth
```
POST   /api/social/connect/{platform}
POST   /api/social/callback/{platform}
DELETE /api/social/disconnect/{platform}
GET    /api/social/accounts
```

### Social Media - Posts
```
POST   /api/social/schedule-post
POST   /api/social/posts/{id}/publish
GET    /api/social/posts
GET    /api/social/posts/{id}
PUT    /api/social/posts/{id}
DELETE /api/social/posts/{id}
```

### Analytics
```
GET    /api/social/analytics/{platform}
GET    /api/social/posts/{id}/metrics
GET    /api/social/dashboard
```

### Social Listening
```
GET    /api/social/listen
POST   /api/social/listen
POST   /api/social/listen/{id}/search
GET    /api/social/listen/{id}/mentions
GET    /api/social/listen/sentiment
```

---

## 🧪 Testing Without Real Credentials

The system includes demo/mock data for testing without real OAuth credentials:

1. **Analytics** - Returns simulated metrics
2. **Listening** - Returns mock sentiment data
3. **Platform Info** - Shows all platforms even without connections

To test with real platforms, you'll need to:
1. Create developer apps on each platform
2. Add credentials to `.env`
3. Configure OAuth redirect URIs

---

## 📱 Platform-Specific Setup

### Facebook
1. Go to https://developers.facebook.com
2. Create a new app
3. Add "Facebook Login" product
4. Set redirect URI: `{FRONTEND_URL}/social-media/callback`
5. Copy App ID and App Secret to `.env`

### Instagram
1. Use Facebook Developer Console
2. Add "Instagram Graph API" to your app
3. Same credentials as Facebook

### Twitter/X
1. Go to https://developer.twitter.com
2. Create a new project and app
3. Enable OAuth 2.0
4. Set redirect URI
5. Copy Client ID and Client Secret

### LinkedIn
1. Go to https://www.linkedin.com/developers
2. Create a new app
3. Add "Sign In with LinkedIn" product
4. Set redirect URI
5. Copy Client ID and Client Secret

---

## 🔍 Testing Commands

### Manual Publish Test
```bash
php artisan social-media:publish-scheduled --limit=10
```

### Manual Analytics Sync
```bash
php artisan social-media:sync-analytics --user=1
```

### Check Scheduled Jobs
```bash
php artisan schedule:list
```

---

## 🐛 Common Issues

### Issue: "Platform service not found"
**Solution:** Make sure platform name is lowercase: `facebook`, `twitter`, `instagram`, `linkedin`

### Issue: "Account not connected"
**Solution:** Complete OAuth flow first via `/api/social/connect/{platform}`

### Issue: "Token expired"
**Solution:** Use `/api/social/refresh-token/{platform}` or reconnect

### Issue: "Scheduler not running"
**Solution:** Make sure cron job is configured and Laravel is in the correct path

### Issue: "Posts not publishing"
**Solution:** 
1. Check queue worker is running
2. Check `scheduled_at` time has passed
3. Check logs: `storage/logs/laravel.log`

---

## 📊 Monitoring

### Check Logs
```bash
tail -f storage/logs/laravel.log
```

### Database Queries
```sql
-- Check scheduled posts
SELECT * FROM social_media_posts WHERE status = 'scheduled';

-- Check connected accounts
SELECT * FROM social_accounts WHERE is_active = 1;

-- Check listening keywords
SELECT * FROM listening_keywords WHERE is_active = 1;
```

---

## 🎯 Next Steps

1. ✅ Run migrations
2. ✅ Add credentials to `.env`
3. ✅ Test authentication endpoints
4. ✅ Test OAuth connection flow
5. ✅ Create a test post
6. ✅ Set up scheduler
7. ✅ Start queue worker
8. ✅ Monitor logs

---

## 📖 Full Documentation

- **Complete API Docs:** `SOCIAL_MEDIA_API_DOCUMENTATION.md`
- **Implementation Summary:** `SOCIAL_MEDIA_MODULE_COMPLETE.md`
- **This Guide:** `SOCIAL_MEDIA_QUICK_START.md`

---

## 💡 Tips

1. **Start with one platform** (e.g., Facebook) to test the flow
2. **Use Postman/Insomnia** for testing API endpoints
3. **Check Laravel logs** for detailed error messages
4. **Test OAuth flow** in a real browser first
5. **Use demo data** to test UI without real credentials
6. **Monitor rate limits** when making frequent API calls

---

## ✅ You're Ready!

The Social Media Management Tool backend is now fully set up and ready to use. Start by registering a user, connecting a social account, and creating your first post!

For any issues, check the logs or refer to the complete documentation.

Happy coding! 🚀


