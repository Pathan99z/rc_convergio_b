<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Journey Progress Tracker</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        .progress-step {
            position: relative;
        }
        .progress-line {
            position: absolute;
            left: 24px;
            top: 60px;
            bottom: -20px;
            width: 3px;
            background: linear-gradient(to bottom, #e5e7eb, #f3f4f6);
        }
        .progress-line.completed {
            background: linear-gradient(to bottom, #10b981, #34d399);
        }
        .progress-line.in-progress {
            background: linear-gradient(to bottom, #3b82f6, #e5e7eb);
        }
        .progress-dot {
            position: absolute;
            left: 16px;
            top: 24px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            border: 3px solid white;
            box-shadow: 0 0 0 3px currentColor;
            z-index: 10;
        }
        .progress-dot.completed {
            background: #10b981;
            color: #10b981;
        }
        .progress-dot.in-progress {
            background: #3b82f6;
            color: #3b82f6;
            animation: pulse 2s infinite;
        }
        .progress-dot.pending {
            background: #e5e7eb;
            color: #9ca3af;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .progress-bar {
            transition: width 0.5s ease-in-out;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-6xl mx-auto">
            <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                <!-- Header -->
                <div class="bg-gradient-to-r from-purple-600 to-indigo-600 px-6 py-4">
                    <h1 class="text-2xl font-bold text-white">Journey Progress Tracker</h1>
                    <p class="text-purple-100 mt-1">Track step-by-step journey progress with success/pending indicators</p>
                </div>

                <!-- Search Section -->
                <div class="p-6 border-b border-gray-200">
                    <div class="flex gap-4">
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Contact ID or Email</label>
                            <input type="text" id="contactInput" placeholder="Enter contact ID or email (e.g., 123 or ashok@reliance.in)" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500">
                        </div>
                        <div class="flex items-end">
                            <button onclick="loadJourneyProgress()" 
                                    class="px-6 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500">
                                Load Progress
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Contact Info Section -->
                <div id="contactInfo" class="hidden p-6 border-b border-gray-200 bg-gradient-to-r from-blue-50 to-indigo-50">
                    <!-- Contact details will be loaded here -->
                </div>

                <!-- Journey Progress Section -->
                <div id="journeyProgress" class="p-6">
                    <div class="text-center text-gray-500 py-8">
                        <i data-lucide="map" class="w-12 h-12 mx-auto mb-4 text-gray-400"></i>
                        <p>Enter a contact ID or email above to view their journey progress</p>
                        <p class="text-sm mt-2">Try: <span class="font-mono bg-gray-100 px-2 py-1 rounded">123</span> or <span class="font-mono bg-gray-100 px-2 py-1 rounded">ashok@reliance.in</span></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        const API_BASE = '/api/contacts';

        async function loadJourneyProgress() {
            const contactInput = document.getElementById('contactInput').value.trim();
            if (!contactInput) {
                alert('Please enter a contact ID or email');
                return;
            }

            try {
                showLoading();
                
                // Determine if input is email or ID
                let contactId = contactInput;
                if (contactInput.includes('@')) {
                    // It's an email, need to get contact by email first
                    const emailResponse = await fetch(`${API_BASE}/journey/${encodeURIComponent(contactInput)}`);
                    const emailData = await emailResponse.json();
                    
                    if (!emailData.success) {
                        showError(`Contact not found for email: ${contactInput}`);
                        return;
                    }
                    contactId = emailData.data.contact.id;
                }

                const response = await fetch(`${API_BASE}/${contactId}/journey-progress`);
                const data = await response.json();

                if (data.success) {
                    displayContactInfo(data.data.contact);
                    displayJourneyProgress(data.data.journeys, data.data.summary);
                } else {
                    showError(data.message || 'Failed to load journey progress');
                }
            } catch (error) {
                console.error('Error loading journey progress:', error);
                showError('Error loading journey progress. Please try again.');
            }
        }

        function showLoading() {
            document.getElementById('journeyProgress').innerHTML = `
                <div class="text-center py-8">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-purple-600 mx-auto mb-4"></div>
                    <p class="text-gray-600">Loading journey progress...</p>
                </div>
            `;
        }

        function showError(message) {
            document.getElementById('journeyProgress').innerHTML = `
                <div class="text-center py-8">
                    <i data-lucide="alert-circle" class="w-12 h-12 mx-auto mb-4 text-red-400"></i>
                    <p class="text-red-600">${message}</p>
                </div>
            `;
            lucide.createIcons();
        }

        function displayContactInfo(contact) {
            const contactInfoDiv = document.getElementById('contactInfo');
            contactInfoDiv.className = 'p-6 border-b border-gray-200 bg-gradient-to-r from-blue-50 to-indigo-50';
            
            contactInfoDiv.innerHTML = `
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-900">${contact.full_name}</h2>
                        <p class="text-gray-600">${contact.email}</p>
                        ${contact.company ? `<p class="text-sm text-gray-500">Company: ${contact.company.name} (${contact.company.industry})</p>` : ''}
                    </div>
                    <div class="text-right">
                        <div class="text-sm text-gray-500">Lead Score</div>
                        <div class="text-2xl font-bold text-indigo-600">${contact.lead_score || 0}</div>
                        <div class="text-sm text-gray-500">${contact.lifecycle_stage || 'Unknown'}</div>
                    </div>
                </div>
            `;
        }

        function displayJourneyProgress(journeys, summary) {
            const progressDiv = document.getElementById('journeyProgress');
            
            if (!journeys || journeys.length === 0) {
                progressDiv.innerHTML = `
                    <div class="text-center py-8">
                        <i data-lucide="map-pin-off" class="w-12 h-12 mx-auto mb-4 text-gray-400"></i>
                        <p class="text-gray-500">No active journeys found for this contact</p>
                    </div>
                `;
                lucide.createIcons();
                return;
            }

            let progressHtml = `
                <div class="mb-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Journey Progress Overview</h3>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                        <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                            <div class="text-2xl font-bold text-blue-600">${summary.total_journeys}</div>
                            <div class="text-sm text-blue-600">Total Journeys</div>
                        </div>
                        <div class="bg-green-50 p-4 rounded-lg border border-green-200">
                            <div class="text-2xl font-bold text-green-600">${summary.active_journeys}</div>
                            <div class="text-sm text-green-600">Active Journeys</div>
                        </div>
                        <div class="bg-purple-50 p-4 rounded-lg border border-purple-200">
                            <div class="text-2xl font-bold text-purple-600">${summary.completed_journeys}</div>
                            <div class="text-sm text-purple-600">Completed</div>
                        </div>
                        <div class="bg-orange-50 p-4 rounded-lg border border-orange-200">
                            <div class="text-2xl font-bold text-orange-600">${summary.average_progress}%</div>
                            <div class="text-sm text-orange-600">Avg Progress</div>
                        </div>
                    </div>
                </div>
            `;

            journeys.forEach((journey, journeyIndex) => {
                const statusColor = journey.status === 'completed' ? 'green' : 
                                  journey.status === 'running' ? 'blue' : 'gray';
                
                progressHtml += `
                    <div class="mb-8 bg-white border border-gray-200 rounded-lg overflow-hidden">
                        <div class="bg-${statusColor}-50 border-b border-${statusColor}-200 p-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h4 class="text-lg font-semibold text-${statusColor}-900">${journey.journey_name}</h4>
                                    <p class="text-${statusColor}-600 text-sm">${journey.journey_description}</p>
                                </div>
                                <div class="text-right">
                                    <div class="text-2xl font-bold text-${statusColor}-600">${journey.overall_progress_percentage}%</div>
                                    <div class="text-sm text-${statusColor}-600">Complete</div>
                                    <div class="text-xs text-${statusColor}-500">${journey.completed_steps_count} of ${journey.total_steps} steps</div>
                                </div>
                            </div>
                            <div class="mt-3">
                                <div class="w-full bg-${statusColor}-200 rounded-full h-2">
                                    <div class="bg-${statusColor}-500 h-2 rounded-full progress-bar" style="width: ${journey.overall_progress_percentage}%"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="p-6">
                            <div class="relative">
                `;

                journey.steps.forEach((step, stepIndex) => {
                    const isLast = stepIndex === journey.steps.length - 1;
                    const statusIcon = step.status === 'completed' ? 'check' : 
                                     step.status === 'in_progress' ? 'clock' : 'circle';
                    
                    progressHtml += `
                        <div class="progress-step mb-6 pl-16 relative">
                            ${!isLast ? `<div class="progress-line ${step.status}"></div>` : ''}
                            <div class="progress-dot ${step.status}"></div>
                            
                            <div class="bg-white border-2 border-${step.color}-200 rounded-lg p-4 shadow-sm ${step.is_current ? 'ring-2 ring-' + step.color + '-400' : ''}">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center mb-2">
                                            <i data-lucide="${step.icon}" class="w-5 h-5 text-${step.color}-500 mr-2"></i>
                                            <h5 class="font-semibold text-gray-900">${step.title}</h5>
                                            <span class="ml-2 px-2 py-1 text-xs rounded-full ${getStatusBadgeClass(step.status)}">
                                                ${step.status.replace('_', ' ').toUpperCase()}
                                            </span>
                                        </div>
                                        <p class="text-gray-600 text-sm mb-2">${step.description}</p>
                                        
                                        ${step.status === 'in_progress' && step.progress_percentage > 0 ? `
                                            <div class="mb-2">
                                                <div class="flex justify-between text-xs text-gray-500 mb-1">
                                                    <span>Progress</span>
                                                    <span>${step.progress_percentage}%</span>
                                                </div>
                                                <div class="w-full bg-gray-200 rounded-full h-1">
                                                    <div class="bg-${step.color}-500 h-1 rounded-full progress-bar" style="width: ${step.progress_percentage}%"></div>
                                                </div>
                                            </div>
                                        ` : ''}
                                        
                                        <div class="text-xs text-gray-500">
                                            Step ${step.order_no} of ${journey.total_steps}
                                            ${step.started_at ? ` • Started: ${formatDate(step.started_at)}` : ''}
                                            ${step.completed_at ? ` • Completed: ${formatDate(step.completed_at)}` : ''}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                });

                progressHtml += `
                            </div>
                        </div>
                        
                        <div class="bg-gray-50 px-6 py-4 border-t border-gray-200">
                            <div class="grid grid-cols-3 gap-4 text-sm">
                                <div>
                                    <span class="font-medium text-gray-900">Current Step:</span>
                                    <span class="text-gray-600">${journey.timeline_summary.current_step}</span>
                                </div>
                                <div>
                                    <span class="font-medium text-gray-900">Next Step:</span>
                                    <span class="text-gray-600">${journey.timeline_summary.next_step}</span>
                                </div>
                                <div>
                                    <span class="font-medium text-gray-900">Last Completed:</span>
                                    <span class="text-gray-600">${journey.timeline_summary.last_completed}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });

            progressDiv.innerHTML = progressHtml;
            lucide.createIcons();
        }

        function getStatusBadgeClass(status) {
            const classes = {
                'completed': 'bg-green-100 text-green-800',
                'in_progress': 'bg-blue-100 text-blue-800',
                'pending': 'bg-gray-100 text-gray-800',
                'failed': 'bg-red-100 text-red-800',
                'cancelled': 'bg-orange-100 text-orange-800',
            };
            return classes[status] || 'bg-gray-100 text-gray-800';
        }

        function formatDate(dateString) {
            if (!dateString) return 'N/A';
            return new Date(dateString).toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        // Allow Enter key to submit
        document.getElementById('contactInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                loadJourneyProgress();
            }
        });

        // Auto-load for ashok@reliance.in if specified in URL
        window.addEventListener('load', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const contact = urlParams.get('contact');
            if (contact) {
                document.getElementById('contactInput').value = contact;
                loadJourneyProgress();
            }
        });
    </script>
</body>
</html>

