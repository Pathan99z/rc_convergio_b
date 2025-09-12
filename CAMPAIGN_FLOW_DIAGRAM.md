# 📊 Campaign Automation Flow Diagram

## 🔄 **Complete Campaign Flow**

```
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│   User Creates  │    │   User Selects   │    │   User Clicks   │
│    Campaign     │───▶│   Recipients     │───▶│     "Send"      │
│                 │    │                  │    │                 │
└─────────────────┘    └──────────────────┘    └─────────────────┘
                                                         │
                                                         ▼
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│   Campaign      │    │   HydrateCampaign│    │   SendCampaign  │
│   Status:       │◀───│   Recipients     │───▶│   Emails        │
│   "sending"     │    │   Job (0.3s)     │    │   Job (3-8s)    │
└─────────────────┘    └──────────────────┘    └─────────────────┘
                                                         │
                                                         ▼
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│   Campaign      │    │   Recipients     │    │   Emails        │
│   Status:       │◀───│   Created        │◀───│   Sent          │
│   "sent"        │    │   (Database)     │    │   (SMTP)        │
└─────────────────┘    └──────────────────┘    └─────────────────┘
```

## 📋 **Recipient Selection Options**

### **Option 1: Manual Selection**
```
User selects specific contacts → Contact IDs: [109, 108, 205, 301]
```

### **Option 2: Dynamic Lists**
```
User selects "VIP Customers" list → System loads all contacts in that list
```

### **Option 3: Bulk Contacts**
```
User selects "All Active Contacts" → System loads 1000+ contacts automatically
```

## ⚡ **Processing Modes**

### **ASYNC Mode (Default)**
```
User clicks "Send" → Jobs queued → Background processing → Status updates
Timeline: 3-8 seconds for 2 recipients
```

### **SYNC Mode (Fallback)**
```
User clicks "Send" → Immediate processing → Instant completion
Timeline: 3-8 seconds for 2 recipients (same speed)
```

## 🎯 **Bulk Processing Example**

### **Scenario: Send to 500 contacts**
```
1. User selects "All Customers" list (500 contacts)
2. HydrateCampaignRecipients job runs (5 seconds)
   - Loads 500 contacts from database
   - Creates 500 recipient records
3. SendCampaignEmails job runs (10-20 minutes)
   - Processes 200 contacts at a time (chunked)
   - Sends emails via SMTP
   - Updates status for each recipient
4. Campaign status: "sent" (500 emails delivered)
```

## 🔧 **Technical Components**

### **Jobs:**
- `HydrateCampaignRecipients` - Loads contacts and creates recipient records
- `SendCampaignEmails` - Sends emails and updates status

### **Database Tables:**
- `campaigns` - Campaign information
- `campaign_recipients` - Individual recipient records
- `contacts` - Contact information
- `list_members` - Contact list memberships

### **Automatic Features:**
- Queue worker auto-start
- Inline execution fallback
- Error handling and retry
- Multi-tenant isolation
- Memory management
