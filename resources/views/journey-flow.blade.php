<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Journey Flow</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        .timeline-item {
            position: relative;
        }
        .timeline-line {
            position: absolute;
            left: 24px;
            top: 60px;
            bottom: -20px;
            width: 2px;
            background: linear-gradient(to bottom, #e5e7eb, #f3f4f6);
        }
        .timeline-dot {
            position: absolute;
            left: 16px;
            top: 24px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            border: 3px solid white;
            box-shadow: 0 0 0 2px currentColor;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                <!-- Header -->
                <div class="bg-gradient-to-r from-blue-600 to-purple-600 px-6 py-4">
                    <h1 class="text-2xl font-bold text-white">Contact Journey Flow</h1>
                    <p class="text-blue-100 mt-1">Track and analyze contact interactions and journey progress</p>
                </div>

                <!-- Search Section -->
                <div class="p-6 border-b border-gray-200">
                    <div class="flex gap-4">
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Contact ID</label>
                            <input type="number" id="contactId" placeholder="Enter contact ID" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div class="flex items-end">
                            <button onclick="loadJourneyFlow()" 
                                    class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                Load Journey
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Contact Info Section -->
                <div id="contactInfo" class="hidden p-6 border-b border-gray-200 bg-gray-50">
                    <!-- Contact details will be loaded here -->
                </div>

                <!-- Journey Timeline -->
                <div id="journeyTimeline" class="p-6">
                    <div class="text-center text-gray-500 py-8">
                        <i data-lucide="search" class="w-12 h-12 mx-auto mb-4 text-gray-400"></i>
                        <p>Enter a contact ID above to view their journey flow</p>
                    </div>
                </div>

                <!-- Metrics Section -->
                <div id="metricsSection" class="hidden p-6 border-t border-gray-200 bg-gray-50">
                    <!-- Metrics will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        const API_BASE = '/api/contacts';

        async function loadJourneyFlow() {
            const contactId = document.getElementById('contactId').value;
            if (!contactId) {
                alert('Please enter a contact ID');
                return;
            }

            try {
                showLoading();
                const response = await fetch(`${API_BASE}/${contactId}/journey-flow`);
                const data = await response.json();

                if (data.success) {
                    displayContactInfo(data.data.contact, data.data.company);
                    displayTimeline(data.data.timeline);
                    displayMetrics(data.data.metrics);
                } else {
                    showError(data.message || 'Failed to load journey flow');
                }
            } catch (error) {
                console.error('Error loading journey flow:', error);
                showError('Error loading journey flow. Please try again.');
            }
        }

        function showLoading() {
            document.getElementById('journeyTimeline').innerHTML = `
                <div class="text-center py-8">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
                    <p class="text-gray-600">Loading journey flow...</p>
                </div>
            `;
        }

        function showError(message) {
            document.getElementById('journeyTimeline').innerHTML = `
                <div class="text-center py-8">
                    <i data-lucide="alert-circle" class="w-12 h-12 mx-auto mb-4 text-red-400"></i>
                    <p class="text-red-600">${message}</p>
                </div>
            `;
            lucide.createIcons();
        }

        function displayContactInfo(contact, company) {
            const contactInfoDiv = document.getElementById('contactInfo');
            contactInfoDiv.className = 'p-6 border-b border-gray-200 bg-gray-50';
            
            contactInfoDiv.innerHTML = `
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-3">Contact Information</h3>
                        <div class="space-y-2">
                            <p><strong>Name:</strong> ${contact.full_name}</p>
                            <p><strong>Email:</strong> ${contact.email}</p>
                            <p><strong>Phone:</strong> ${contact.phone || 'Not provided'}</p>
                            <p><strong>Lifecycle Stage:</strong> <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-sm">${contact.lifecycle_stage || 'Unknown'}</span></p>
                            <p><strong>Lead Score:</strong> ${contact.lead_score || 'Not scored'}</p>
                            <p><strong>Source:</strong> ${contact.source || 'Unknown'}</p>
                        </div>
                    </div>
                    ${company ? `
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-3">Company Information</h3>
                        <div class="space-y-2">
                            <p><strong>Company:</strong> ${company.name}</p>
                            <p><strong>Industry:</strong> ${company.industry || 'Not specified'}</p>
                            <p><strong>Size:</strong> ${company.size || 'Not specified'}</p>
                            <p><strong>Website:</strong> ${company.website ? `<a href="${company.website}" target="_blank" class="text-blue-600 hover:underline">${company.website}</a>` : 'Not provided'}</p>
                            <p><strong>Revenue:</strong> ${company.annual_revenue ? '$' + company.annual_revenue.toLocaleString() : 'Not provided'}</p>
                        </div>
                    </div>
                    ` : `
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-3">Company Information</h3>
                        <p class="text-gray-500 italic">No company associated</p>
                    </div>
                    `}
                </div>
            `;
        }

        function displayTimeline(timeline) {
            const timelineDiv = document.getElementById('journeyTimeline');
            
            if (!timeline || timeline.length === 0) {
                timelineDiv.innerHTML = `
                    <div class="text-center py-8">
                        <i data-lucide="calendar-x" class="w-12 h-12 mx-auto mb-4 text-gray-400"></i>
                        <p class="text-gray-500">No journey events found for this contact</p>
                    </div>
                `;
                lucide.createIcons();
                return;
            }

            let timelineHtml = '<h3 class="text-lg font-semibold text-gray-900 mb-6">Journey Timeline</h3><div class="relative">';
            
            timeline.forEach((event, index) => {
                const isLast = index === timeline.length - 1;
                const date = new Date(event.date).toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });

                timelineHtml += `
                    <div class="timeline-item mb-6 pl-16 relative">
                        ${!isLast ? '<div class="timeline-line"></div>' : ''}
                        <div class="timeline-dot text-${event.color}-500"></div>
                        <div class="bg-white border border-gray-200 rounded-lg p-4 shadow-sm">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center mb-2">
                                        <i data-lucide="${event.icon}" class="w-5 h-5 text-${event.color}-500 mr-2"></i>
                                        <h4 class="font-semibold text-gray-900">${event.title}</h4>
                                    </div>
                                    <p class="text-gray-600 mb-2">${event.description}</p>
                                    <div class="text-sm text-gray-500">${date}</div>
                                    ${event.data ? `
                                        <div class="mt-3 pt-3 border-t border-gray-100">
                                            <div class="grid grid-cols-2 gap-2 text-sm">
                                                ${Object.entries(event.data).map(([key, value]) => {
                                                    if (value && typeof value !== 'object') {
                                                        return `<div><strong>${key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}:</strong> ${value}</div>`;
                                                    }
                                                    return '';
                                                }).join('')}
                                            </div>
                                        </div>
                                    ` : ''}
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            timelineHtml += '</div>';
            timelineDiv.innerHTML = timelineHtml;
            lucide.createIcons();
        }

        function displayMetrics(metrics) {
            const metricsDiv = document.getElementById('metricsSection');
            metricsDiv.className = 'p-6 border-t border-gray-200 bg-gray-50';
            
            metricsDiv.innerHTML = `
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Journey Metrics</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="bg-white p-4 rounded-lg border border-gray-200">
                        <div class="text-2xl font-bold text-blue-600">${metrics.total_timeline_events || 0}</div>
                        <div class="text-sm text-gray-600">Total Events</div>
                    </div>
                    <div class="bg-white p-4 rounded-lg border border-gray-200">
                        <div class="text-2xl font-bold text-green-600">${metrics.deals_count || 0}</div>
                        <div class="text-sm text-gray-600">Deals</div>
                    </div>
                    <div class="bg-white p-4 rounded-lg border border-gray-200">
                        <div class="text-2xl font-bold text-purple-600">${metrics.meetings_count || 0}</div>
                        <div class="text-sm text-gray-600">Meetings</div>
                    </div>
                    <div class="bg-white p-4 rounded-lg border border-gray-200">
                        <div class="text-2xl font-bold text-orange-600">${metrics.campaigns_received || 0}</div>
                        <div class="text-sm text-gray-600">Campaigns</div>
                    </div>
                    <div class="bg-white p-4 rounded-lg border border-gray-200">
                        <div class="text-2xl font-bold text-indigo-600">${metrics.email_open_rate || 0}%</div>
                        <div class="text-sm text-gray-600">Email Open Rate</div>
                    </div>
                    <div class="bg-white p-4 rounded-lg border border-gray-200">
                        <div class="text-2xl font-bold text-teal-600">${metrics.forms_submitted || 0}</div>
                        <div class="text-sm text-gray-600">Forms Submitted</div>
                    </div>
                    <div class="bg-white p-4 rounded-lg border border-gray-200">
                        <div class="text-2xl font-bold text-red-600">${metrics.events_attended || 0}</div>
                        <div class="text-sm text-gray-600">Events Attended</div>
                    </div>
                    <div class="bg-white p-4 rounded-lg border border-gray-200">
                        <div class="text-2xl font-bold text-pink-600">${metrics.active_journeys || 0}</div>
                        <div class="text-sm text-gray-600">Active Journeys</div>
                    </div>
                </div>
                ${metrics.deals_value ? `
                <div class="mt-4 bg-white p-4 rounded-lg border border-gray-200">
                    <div class="text-3xl font-bold text-green-600">$${metrics.deals_value.toLocaleString()}</div>
                    <div class="text-sm text-gray-600">Total Deal Value</div>
                </div>
                ` : ''}
            `;
        }

        // Allow Enter key to submit
        document.getElementById('contactId').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                loadJourneyFlow();
            }
        });
    </script>
</body>
</html>

