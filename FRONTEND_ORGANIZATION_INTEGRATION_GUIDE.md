# Frontend Organization Name Integration Guide

## 🎯 **Objective**
Display the user's organization name (e.g., "TCS", "Wipro") dynamically in the frontend header instead of static text.

## ✅ **Backend Status - READY**
The backend is **100% ready** and requires **NO changes**:

- ✅ **User Model**: Has `organization_name` field
- ✅ **API Endpoint**: `GET /api/users/me` returns user data including `organization_name`
- ✅ **UserResource**: Includes `organization_name` in API response
- ✅ **Database**: `organization_name` column exists in `users` table
- ✅ **Authentication**: Endpoint is properly authenticated

## 🔧 **Frontend Implementation Steps**

### **Step 1: API Integration**

#### **API Endpoint**
```
GET /api/users/me
```

#### **Headers Required**
```
Authorization: Bearer {your_token}
Accept: application/json
```

#### **Example Response**
```json
{
  "data": {
    "id": 1,
    "name": "Amrutha",
    "email": "amrutha@example.com",
    "organization_name": "TCS",
    "status": "active",
    "roles": [
      {
        "id": 1,
        "name": "admin"
      }
    ],
    "created_at": "2025-08-19T14:54:26.000000Z",
    "updated_at": "2025-08-19T14:54:26.000000Z"
  }
}
```

### **Step 2: Frontend Code Implementation**

#### **Option A: React/Vue Component (Recommended)**

```javascript
// UserService.js or similar
class UserService {
  static async getCurrentUser() {
    try {
      const response = await fetch('/api/users/me', {
        method: 'GET',
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('token')}`,
          'Accept': 'application/json',
          'Content-Type': 'application/json'
        }
      });
      
      if (!response.ok) {
        throw new Error('Failed to fetch user data');
      }
      
      const data = await response.json();
      return data.data;
    } catch (error) {
      console.error('Error fetching user data:', error);
      throw error;
    }
  }
}

// Header Component
import { useState, useEffect } from 'react';

const HeaderComponent = () => {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchUser = async () => {
      try {
        const userData = await UserService.getCurrentUser();
        setUser(userData);
      } catch (error) {
        console.error('Failed to load user data:', error);
        // Fallback to static text or default
        setUser({ organization_name: 'RC' });
      } finally {
        setLoading(false);
      }
    };

    fetchUser();
  }, []);

  if (loading) {
    return <span>Loading...</span>;
  }

  return (
    <div data-testid="header-company">
      <span className="hidden sm:inline">
        {user?.organization_name || 'RC'}
      </span>
      <span className="sm:hidden">RC</span>
    </div>
  );
};
```

#### **Option B: Vanilla JavaScript**

```javascript
// Fetch user data and update header
async function updateOrganizationName() {
  try {
    const response = await fetch('/api/users/me', {
      method: 'GET',
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('token')}`,
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      }
    });

    if (response.ok) {
      const data = await response.json();
      const organizationName = data.data.organization_name;
      
      // Update the header element
      const headerElement = document.querySelector('[data-testid="header-company"] .hidden.sm\\:inline');
      if (headerElement && organizationName) {
        headerElement.textContent = organizationName;
      }
    }
  } catch (error) {
    console.error('Failed to fetch organization name:', error);
    // Keep existing static text as fallback
  }
}

// Call on page load
document.addEventListener('DOMContentLoaded', updateOrganizationName);
```

#### **Option C: jQuery (if using jQuery)**

```javascript
$(document).ready(function() {
  $.ajax({
    url: '/api/users/me',
    method: 'GET',
    headers: {
      'Authorization': 'Bearer ' + localStorage.getItem('token'),
      'Accept': 'application/json'
    },
    success: function(response) {
      const organizationName = response.data.organization_name;
      if (organizationName) {
        $('[data-testid="header-company"] .hidden.sm\\:inline').text(organizationName);
      }
    },
    error: function(xhr, status, error) {
      console.error('Failed to fetch organization name:', error);
      // Keep existing static text as fallback
    }
  });
});
```

### **Step 3: HTML Structure Update**

#### **Current HTML (from your image)**
```html
<div data-testid="header-company">
  <span class="hidden sm:inline">reliancecorporation.co.za PK</span>
  <span class="sm:hidden">RC</span>
</div>
```

#### **Updated HTML (Dynamic)**
```html
<div data-testid="header-company">
  <span class="hidden sm:inline" id="organization-name">Loading...</span>
  <span class="sm:hidden">RC</span>
</div>
```

### **Step 4: Error Handling & Fallbacks**

```javascript
// Robust implementation with fallbacks
async function updateOrganizationName() {
  try {
    const response = await fetch('/api/users/me', {
      method: 'GET',
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('token')}`,
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      }
    });

    if (response.ok) {
      const data = await response.json();
      const organizationName = data.data?.organization_name;
      
      // Update header with organization name or fallback
      const headerElement = document.querySelector('[data-testid="header-company"] .hidden.sm\\:inline');
      if (headerElement) {
        headerElement.textContent = organizationName || 'RC';
      }
    } else {
      // API error - use fallback
      setFallbackText();
    }
  } catch (error) {
    console.error('Failed to fetch organization name:', error);
    // Network error - use fallback
    setFallbackText();
  }
}

function setFallbackText() {
  const headerElement = document.querySelector('[data-testid="header-company"] .hidden.sm\\:inline');
  if (headerElement) {
    headerElement.textContent = 'RC'; // Default fallback
  }
}
```

## 🎨 **Styling Considerations**

### **Responsive Design**
- **Desktop (sm and up)**: Show full organization name (e.g., "TCS", "Wipro")
- **Mobile (below sm)**: Show "RC" (unchanged)

### **CSS Classes**
```css
/* Ensure proper responsive behavior */
.hidden.sm\:inline {
  display: none;
}

@media (min-width: 640px) {
  .sm\:inline {
    display: inline;
  }
}

.sm\:hidden {
  display: inline;
}

@media (min-width: 640px) {
  .sm\:hidden {
    display: none;
  }
}
```

## 🧪 **Testing Scenarios**

### **Test Cases**
1. **Valid Organization Name**: User with `organization_name: "TCS"` → Display "TCS"
2. **Valid Organization Name**: User with `organization_name: "Wipro"` → Display "Wipro"
3. **Null Organization Name**: User with `organization_name: null` → Display "RC" (fallback)
4. **Empty Organization Name**: User with `organization_name: ""` → Display "RC" (fallback)
5. **API Error**: Network/authentication error → Display "RC" (fallback)
6. **Loading State**: Show "Loading..." while fetching → Then show organization name

### **Manual Testing**
```javascript
// Test different scenarios
const testCases = [
  { organization_name: "TCS", expected: "TCS" },
  { organization_name: "Wipro", expected: "Wipro" },
  { organization_name: null, expected: "RC" },
  { organization_name: "", expected: "RC" }
];

testCases.forEach(testCase => {
  console.log(`Testing: ${testCase.organization_name} → Expected: ${testCase.expected}`);
});
```

## 🚀 **Deployment Checklist**

### **Before Deployment**
- [ ] Test API endpoint `GET /api/users/me` returns correct data
- [ ] Verify authentication token is properly stored and sent
- [ ] Test error handling with network failures
- [ ] Test responsive design on mobile and desktop
- [ ] Verify fallback text displays correctly

### **After Deployment**
- [ ] Check browser console for any JavaScript errors
- [ ] Verify organization name displays correctly for different users
- [ ] Test on different screen sizes
- [ ] Confirm no breaking changes to existing functionality

## 📝 **Example Implementation Files**

### **Complete React Component**
```jsx
// components/OrganizationHeader.jsx
import React, { useState, useEffect } from 'react';

const OrganizationHeader = () => {
  const [organizationName, setOrganizationName] = useState('RC');
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchOrganizationName = async () => {
      try {
        const token = localStorage.getItem('token');
        const response = await fetch('/api/users/me', {
          headers: {
            'Authorization': `Bearer ${token}`,
            'Accept': 'application/json'
          }
        });

        if (response.ok) {
          const data = await response.json();
          const orgName = data.data?.organization_name;
          setOrganizationName(orgName || 'RC');
        }
      } catch (error) {
        console.error('Failed to fetch organization name:', error);
        setOrganizationName('RC');
      } finally {
        setLoading(false);
      }
    };

    fetchOrganizationName();
  }, []);

  return (
    <div data-testid="header-company">
      <span className="hidden sm:inline">
        {loading ? 'Loading...' : organizationName}
      </span>
      <span className="sm:hidden">RC</span>
    </div>
  );
};

export default OrganizationHeader;
```

## ✅ **Summary**

The backend is **100% ready** - no changes needed! The frontend just needs to:

1. **Call** `GET /api/users/me` to get user data
2. **Extract** `organization_name` from the response
3. **Display** it in the header element
4. **Handle** errors gracefully with fallbacks

**No backend modifications required** - everything is already set up and working! 🚀

