# 🎯 Freeze Audience Feature - Campaign Automation

## 🚨 **Problem Solved**

**Before:** Dynamic segments could change campaign audience after scheduling
- User schedules campaign with "VIP Customers" segment (350 contacts)
- Later, 50 new contacts added to "VIP Customers" segment  
- Campaign runs with 400 contacts instead of original 350
- **Result**: Campaign audience changed unexpectedly!

**After:** Audience is frozen at the moment of scheduling/sending
- User schedules campaign with "VIP Customers" segment (350 contacts)
- Audience is immediately frozen and saved to `campaign_recipients`
- Even if segment grows to 400 contacts later, campaign still sends to original 350
- **Result**: Campaign audience is locked and predictable!

## ✅ **Solution Implementation**

### **1. Audience Freezing Process**

When user clicks "Send" or "Schedule":

```php
// In CampaignsController@send
$this->freezeCampaignAudience($campaign);
```

**What happens:**
1. **Resolve Contacts**: Query the current segment/contacts at this exact moment
2. **Freeze Snapshot**: Save all contacts to `campaign_recipients` table
3. **Store Metadata**: Save frozen contact IDs and timestamp in `campaign.settings`
4. **Update Count**: Set `campaign.total_recipients` to frozen count

### **2. Database Changes**

**`campaign_recipients` table stores the frozen audience:**
```sql
campaign_recipients:
- campaign_id (which campaign)
- contact_id (which contact - nullable for backward compatibility)
- email (contact email)
- name (contact name)
- status (pending/sent/bounced)
- tenant_id (multi-tenancy)
- created_at/updated_at
```

**`campaigns.settings` stores metadata:**
```json
{
  "recipient_mode": "segment",
  "segment_id": 5,
  "frozen_contact_ids": [1, 2, 3, 4, 5],
  "frozen_at": "2025-09-11T09:10:00.000Z",
  "original_segment_id": 5
}
```

### **3. Job Flow Changes**

**Before (Problematic):**
```
Send → HydrateCampaignRecipients → SendCampaignEmails
     ↑ (Dynamic query - can change!)
```

**After (Fixed):**
```
Send → freezeCampaignAudience() → SendCampaignEmails
     ↑ (Static snapshot - never changes!)
```

## 🔧 **Technical Details**

### **Freeze Method Logic**

```php
private function freezeCampaignAudience(Campaign $campaign): void
{
    // 1. Get campaign settings
    $settings = $campaign->settings ?? [];
    $mode = $settings['recipient_mode'] ?? null;
    $contactIds = $settings['recipient_contact_ids'] ?? [];
    $segmentId = $settings['segment_id'] ?? null;

    // 2. Clear existing recipients (clean slate)
    DB::table('campaign_recipients')->where('campaign_id', $campaign->id)->delete();

    // 3. Resolve contacts based on mode
    if ($mode === 'segment' && $segmentId) {
        // Dynamic segment - resolve NOW and freeze
        $query->whereIn('id', function ($q) use ($segmentId) {
            $q->select('contact_id')->from('list_members')->where('list_id', $segmentId);
        });
        
        // Store resolved IDs for traceability
        $resolvedContactIds = $query->pluck('id')->toArray();
        $campaign->update([
            'settings' => array_merge($settings, [
                'frozen_contact_ids' => $resolvedContactIds,
                'frozen_at' => now()->toISOString(),
                'original_segment_id' => $segmentId
            ])
        ]);
        
    } elseif (in_array($mode, ['manual', 'static'], true) && !empty($contactIds)) {
        // Manual selection - already static
        $query->whereIn('id', $contactIds);
        
        $campaign->update([
            'settings' => array_merge($settings, [
                'frozen_contact_ids' => $contactIds,
                'frozen_at' => now()->toISOString()
            ])
        ]);
    }

    // 4. Bulk insert frozen audience
    $query->chunkById(500, function ($contacts) use ($campaign, $now) {
        // Insert into campaign_recipients...
    });

    // 5. Update total count
    $totalRecipients = DB::table('campaign_recipients')->where('campaign_id', $campaign->id)->count();
    $campaign->update(['total_recipients' => $totalRecipients]);
}
```

### **Backward Compatibility**

- `HydrateCampaignRecipients` job is kept but deprecated (does nothing)
- `campaign_recipients.contact_id` column is nullable for existing data
- All existing campaigns continue to work

## 📊 **Benefits**

### **1. Predictable Campaigns**
- Manager can say "Campaign X was sent to exactly 350 people"
- Audience count never changes after scheduling
- Reliable reporting and analytics

### **2. Audit Trail**
- `frozen_contact_ids` shows exactly who was targeted
- `frozen_at` timestamp shows when audience was locked
- `original_segment_id` shows which segment was used

### **3. Performance**
- No dynamic queries during sending
- Pre-resolved contacts in `campaign_recipients`
- Faster email sending process

### **4. Data Integrity**
- Campaign audience is immutable after freezing
- No race conditions with segment changes
- Consistent multi-tenant isolation

## 🧪 **Testing Scenarios**

### **Test 1: Dynamic Segment Freezing**
1. Create segment "VIP Customers" with 100 contacts
2. Schedule campaign for 2 minutes later
3. Add 50 new contacts to "VIP Customers" segment
4. Wait for campaign to send
5. **Expected**: Campaign sends to original 100 contacts only

### **Test 2: Manual Selection Freezing**
1. Manually select 25 specific contacts
2. Schedule campaign
3. **Expected**: Campaign sends to exactly those 25 contacts

### **Test 3: Immediate Send Freezing**
1. Create campaign with segment
2. Click "Send Now" (not schedule)
3. **Expected**: Audience frozen immediately, emails sent

## 🚀 **Production Ready**

- ✅ Multi-tenant safe
- ✅ Backward compatible
- ✅ Performance optimized (chunked inserts)
- ✅ Comprehensive logging
- ✅ Error handling
- ✅ Database transactions

## 📝 **API Response Changes**

Campaign send response now includes frozen audience info:

```json
{
  "data": {
    "id": 123,
    "status": "scheduled",
    "total_recipients": 350,
    "settings": {
      "recipient_mode": "segment",
      "segment_id": 5,
      "frozen_contact_ids": [1, 2, 3, ...],
      "frozen_at": "2025-09-11T09:10:00.000Z",
      "original_segment_id": 5
    }
  },
  "message": "Campaign scheduled successfully"
}
```

---

## 🎯 **Summary**

The "Freeze Audience" feature ensures that **campaign audiences are locked at the moment of scheduling/sending**, preventing dynamic segments from changing the target audience later. This provides predictable, reliable campaign delivery with full audit trails.

**Key Benefits:**
- ✅ **Predictable**: Audience count never changes after scheduling
- ✅ **Reliable**: No race conditions with segment updates  
- ✅ **Auditable**: Full traceability of who was targeted
- ✅ **Performant**: Pre-resolved contacts for faster sending
- ✅ **Compatible**: Works with existing campaigns and data
