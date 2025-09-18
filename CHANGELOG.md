# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

### Enhancement
- **Campaigns Metrics API**: `/api/campaigns/metrics` now includes `total_campaigns` field
  - Added `total_campaigns` to the response data showing the total number of campaigns for the authenticated tenant
  - Maintains backward compatibility with existing fields (`delivered`, `opens`, `clicks`, `bounces`, `range`)
  - Improved tenant isolation for security (campaigns are now properly filtered by tenant_id)
  - Added comprehensive feature tests to verify the new field and tenant isolation

- **Email Performance Trends API**: Added `/api/campaigns/metrics/trends` for Email Performance Trends chart
  - New endpoint provides time-series data for email performance metrics
  - Supports both daily and weekly intervals via `interval` parameter
  - Configurable date range via `range` parameter (default: 30d)
  - Returns data grouped by date with metrics: `sent`, `delivered`, `opens`, `clicks`, `bounces`
  - Maintains tenant isolation for security
  - Includes comprehensive caching for performance
  - Added extensive feature tests covering all functionality

### Technical Details
- **File**: `app/Http/Controllers/Api/Dashboard/CampaignsController.php`
- **Methods**: 
  - `metrics()` - Enhanced with `total_campaigns` field
  - `trends()` - New method for time-series data
- **New Field**: `total_campaigns` - counts all campaigns for the authenticated tenant
- **New Endpoint**: `/api/campaigns/metrics/trends` - time-series email performance data
- **Security**: Added proper tenant filtering to prevent cross-tenant data access
- **Tests**: 
  - Added `tests/Feature/CampaignMetricsTest.php` with comprehensive test coverage
  - Added `tests/Feature/CampaignTrendsTest.php` with extensive trends testing

### Example Responses

**Campaigns Metrics (`/api/campaigns/metrics`):**
```json
{
  "success": true,
  "data": {
    "total_campaigns": 5,
    "delivered": 128,
    "opens": 0,
    "clicks": 0,
    "bounces": 4,
    "range": "14d"
  }
}
```

**Email Performance Trends (`/api/campaigns/metrics/trends`):**
```json
{
  "success": true,
  "data": [
    {
      "date": "2025-01-15",
      "sent": 20,
      "delivered": 18,
      "opens": 12,
      "clicks": 5,
      "bounces": 2
    },
    {
      "date": "2025-01-16", 
      "sent": 15,
      "delivered": 14,
      "opens": 8,
      "clicks": 3,
      "bounces": 1
    }
  ]
}
```
