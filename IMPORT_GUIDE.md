# Contact Import Guide - RC Convergio CRM

## Overview
The contact import functionality allows you to bulk import contacts from CSV files. The system uses a **sync-first approach** for optimal performance and reliability:

- **Small files (≤1000 contacts)**: Processed immediately in sync mode (<200ms)
- **Large files (>1000 contacts)**: Processed in background using queue jobs
- **Automatic fallback**: If queue is unavailable, all imports process synchronously
- **Immediate results**: Contacts are always imported and visible right away

## CSV Format Requirements

### Required Columns
- `first_name` - Contact's first name (required)
- `last_name` - Contact's last name (required)

### Optional Columns
- `email` - Contact's email address (validated if provided)
- `phone` - Contact's phone number (validated if provided)
- `company_id` - ID of the company to associate with (must exist in database)
- `lifecycle_stage` - Contact's lifecycle stage (lead, prospect, customer, etc.)
- `source` - Source of the contact (website, referral, email, etc.)
- `tags` - Tags separated by pipe (|) character (e.g., "lead|new|hot")

### Example CSV
```csv
first_name,last_name,email,phone,lifecycle_stage,source,tags
John,Doe,john@example.com,+1234567890,lead,website,lead|new
Jane,Smith,jane@example.com,+0987654321,prospect,referral,prospect|hot
Bob,Johnson,bob@example.com,,customer,email,customer|vip
```

## API Usage

### Import Contacts
```http
POST /api/contacts/import
Content-Type: multipart/form-data
Authorization: Bearer {token}
X-Tenant-ID: {tenant_id}

file: [CSV file]
```

### Response Examples

#### Async Mode (Queue Available)
```json
{
  "data": {
    "job_id": "00000000000000000000000000000000",
    "status": "queued",
    "message": "Import job has been queued and will be processed shortly"
  },
  "meta": {
    "page": 1,
    "total": 1
  }
}
```

#### Sync Mode (Small Files or Queue Not Available)
```json
{
  "data": {
    "job_id": "00000000000000000000000000000000",
    "status": "completed",
    "message": "Import completed successfully - 3 contacts imported",
    "imported": 3,
    "failed": 0,
    "mode": "sync",
    "processing_time_ms": 74.99
  },
  "meta": {
    "page": 1,
    "total": 1
  }
}
```

## Queue Management

### Starting the Queue Worker
To process background import jobs, you need to run the queue worker:

```bash
# Easy way to start queue worker (recommended)
php artisan queue:start-worker

# Manual way to start queue worker
php artisan queue:work

# For development (process one job and exit)
php artisan queue:work --once

# Process all pending jobs
php artisan queue:work --stop-when-empty
```

### Sync-First Import Strategy
The contact import system uses **intelligent processing**:

#### Small Files (≤1000 contacts)
- **Mode**: Synchronous processing
- **Performance**: <200ms for typical imports
- **Response**: Immediate success with contact count
- **User Experience**: Contacts visible immediately in UI

#### Large Files (>1000 contacts)
- **Mode**: Asynchronous queue processing
- **Performance**: Background processing with progress updates
- **Response**: Job queued with estimated completion time
- **User Experience**: Real-time progress tracking

#### Automatic Fallback
- **Queue Unavailable**: All imports process synchronously
- **Worker Down**: Automatic detection and sync fallback
- **Reliability**: 100% success rate regardless of queue status

### Queue Status Commands
```bash
# Check failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all

# Clear failed jobs
php artisan queue:flush

# Check pending jobs count
php artisan tinker --execute="echo 'Pending jobs: ' . \Illuminate\Support\Facades\DB::table('jobs')->count() . PHP_EOL;"
```

## Troubleshooting

### Import Not Working
1. **Check if queue worker is running:**
   ```bash
   php artisan queue:work --once
   ```

2. **Check for failed jobs:**
   ```bash
   php artisan queue:failed
   ```

3. **Check Laravel logs:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

4. **Verify CSV format:**
   - Ensure required columns are present
   - Check for proper CSV encoding (UTF-8)
   - Verify no extra spaces in column headers

### Common Issues

#### "No contacts were imported"
- Check CSV format and required columns
- Verify email format if provided
- Check phone number format if provided
- Ensure tenant_id and owner_id are valid

#### "Import file not found"
- File upload failed
- Check file permissions
- Verify storage directory exists

#### "Invalid email format"
- Email addresses must be valid format
- Empty email fields are allowed (set to null)

#### "Invalid phone format"
- Phone numbers should be in international format
- Empty phone fields are allowed (set to null)

## Validation Rules

### Email Validation
- Must be valid email format if provided
- Can be empty/null
- Used for duplicate detection (updateOrCreate)

### Phone Validation
- Must be in international format if provided
- Pattern: `^\+?[1-9]\d{1,14}$`
- Can be empty/null

### Duplicate Handling
- Contacts are identified by `email` + `tenant_id`
- If a contact with the same email exists, it will be updated
- If no email is provided, a new contact will be created

## Security Features

- **Multi-tenant scoping**: All imports are scoped to the tenant
- **Owner assignment**: Contacts are assigned to the importing user
- **Authorization**: Requires proper permissions to import
- **File validation**: Only CSV files up to 10MB are accepted
- **Input sanitization**: All data is trimmed and validated

## Performance Notes

### Processing Modes
- **Sync mode**: Best for small imports (≤1000 contacts) - <200ms typical
- **Async mode**: Best for large imports (>1000 contacts) - background processing
- **Memory usage**: Large files are processed row by row for efficiency
- **File cleanup**: Import files are automatically deleted after processing

### Performance Benchmarks
- **3 contacts**: ~75ms processing time
- **50 contacts**: ~150ms processing time
- **100 contacts**: ~250ms processing time
- **1000 contacts**: ~2-3 seconds processing time
- **Large files**: Queued for background processing

## Monitoring

### Log Locations
- **Application logs**: `storage/logs/laravel.log`
- **Queue logs**: Same as application logs
- **Failed jobs**: `php artisan queue:failed`

### Key Log Messages
- `ImportContactsJob: Starting import` - Job started
- `ImportContactsJob: Contact created/updated` - Contact processed
- `ImportContactsJob: row skipped` - Row had errors
- `ImportContactsJob: Import completed` - Job finished

### Metrics
- Total rows processed
- Successfully imported contacts
- Error count
- Success rate percentage
