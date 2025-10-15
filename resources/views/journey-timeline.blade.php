<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Professional Journey Timeline</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        .journey-phase {
            position: relative;
            margin-bottom: 2rem;
        }
        .phase-connector {
            position: absolute;
            left: 32px;
            top: 80px;
            bottom: -32px;
            width: 4px;
            background: linear-gradient(to bottom, #e5e7eb, #f3f4f6);
            z-index: 1;
        }
        .phase-connector.completed {
            background: linear-gradient(to bottom, #10b981, #34d399);
        }
        .phase-connector.in-progress {
            background: linear-gradient(to bottom, #3b82f6, #e5e7eb);
        }
        .step-timeline {
            position: relative;
            padding-left: 4rem;
        }
        .step-connector {
            position: absolute;
            left: 24px;
            top: 32px;
            bottom: -16px;
            width: 2px;
            background: #e5e7eb;
        }
        .step-connector.achieved {
            background: #10b981;
        }
        .step-dot {
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
        .step-dot.achieved {
            background: #10b981;
            color: #10b981;
        }
        .step-dot.pending {
            background: #e5e7eb;
            color: #9ca3af;
        }
        .phase-header {
            position: relative;
            z-index: 20;
        }
        .achievement-badge {
            animation: bounce 1s infinite;
        }
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
            40% { transform: translateY(-10px); }
            60% { transform: translateY(-5px); }
        }
        .progress-ring {
            transform: rotate(-90deg);
        }
        .progress-ring-circle {
            transition: stroke-dashoffset 0.35s;
            transform-origin: 50% 50%;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-6xl mx-auto">
            <div class="bg-white rounded-xl shadow-xl overflow-hidden">
                <!-- Header -->
                <div class="bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-600 px-8 py-6">
                    <h1 class="text-3xl font-bold text-white">Professional Journey Timeline</h1>
                    <p class="text-indigo-100 mt-2">Track customer journey with achieved milestones and pending steps</p>
                </div>

                <!-- Search Section -->
                <div class="p-6 border-b border-gray-200 bg-gray-50">
                    <div class="flex gap-4">
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Contact ID or Email</label>
                            <input type="text" id="contactInput" placeholder="Enter contact ID or email (e.g., 123 or ashok@reliance.in)" 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                        </div>
                        <div class="flex items-end">
                            <button onclick="loadJourneyTimeline()" 
                                    class="px-8 py-3 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors duration-200">
                                View Journey
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Contact Info Section -->
                <div id="contactInfo" class="hidden">
                    <!-- Contact details will be loaded here -->
                </div>

                <!-- Journey Timeline Section -->
                <div id="journeyTimeline" class="p-8">
                    <div class="text-center text-gray-500 py-12">
                        <i data-lucide="route" class="w-16 h-16 mx-auto mb-4 text-gray-400"></i>
                        <h3 class="text-xl font-semibold text-gray-700 mb-2">Professional Journey Visualization</h3>
                        <p class="text-gray-500 mb-4">Enter a contact ID or email above to view their professional journey timeline</p>
                        <div class="flex justify-center space-x-4 text-sm">
                            <span class="bg-green-100 text-green-800 px-3 py-1 rounded-full">✓ Achieved</span>
                            <span class="bg-gray-100 text-gray-600 px-3 py-1 rounded-full">⏳ Pending</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        const API_BASE = '/api/contacts';

        async function loadJourneyTimeline() {
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

                const response = await fetch(`${API_BASE}/${contactId}/journey-timeline`);
                const data = await response.json();

                if (data.success) {
                    displayContactInfo(data.data.contact, data.data.progress_summary);
                    displayJourneyTimeline(data.data.journey_phases, data.data.timeline_steps, data.data.recent_achievements);
                } else {
                    showError(data.message || 'Failed to load journey timeline');
                }
            } catch (error) {
                console.error('Error loading journey timeline:', error);
                showError('Error loading journey timeline. Please try again.');
            }
        }

        function showLoading() {
            document.getElementById('journeyTimeline').innerHTML = `
                <div class="text-center py-12">
                    <div class="animate-spin rounded-full h-16 w-16 border-b-2 border-indigo-600 mx-auto mb-4"></div>
                    <p class="text-gray-600 text-lg">Loading professional journey timeline...</p>
                </div>
            `;
        }

        function showError(message) {
            document.getElementById('journeyTimeline').innerHTML = `
                <div class="text-center py-12">
                    <i data-lucide="alert-circle" class="w-16 h-16 mx-auto mb-4 text-red-400"></i>
                    <h3 class="text-xl font-semibold text-red-600 mb-2">Error</h3>
                    <p class="text-red-600">${message}</p>
                </div>
            `;
            lucide.createIcons();
        }

        function displayContactInfo(contact, progressSummary) {
            const contactInfoDiv = document.getElementById('contactInfo');
            contactInfoDiv.className = 'p-6 border-b border-gray-200 bg-gradient-to-r from-blue-50 to-indigo-50';
            
            contactInfoDiv.innerHTML = `
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-6">
                        <div class="w-16 h-16 bg-indigo-100 rounded-full flex items-center justify-center">
                            <i data-lucide="user" class="w-8 h-8 text-indigo-600"></i>
                        </div>
                        <div>
                            <h2 class="text-2xl font-bold text-gray-900">${contact.full_name}</h2>
                            <p class="text-gray-600">${contact.email}</p>
                            ${contact.company ? `<p class="text-sm text-gray-500 flex items-center mt-1">
                                <i data-lucide="building" class="w-4 h-4 mr-1"></i>
                                ${contact.company.name} • ${contact.company.industry}
                            </p>` : ''}
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="flex items-center space-x-6">
                            <div class="text-center">
                                <div class="text-3xl font-bold text-indigo-600">${progressSummary.overall_progress_percentage}%</div>
                                <div class="text-sm text-gray-500">Journey Complete</div>
                            </div>
                            <div class="text-center">
                                <div class="text-2xl font-bold text-green-600">${progressSummary.achieved_steps}</div>
                                <div class="text-sm text-gray-500">Steps Achieved</div>
                            </div>
                            <div class="text-center">
                                <div class="text-2xl font-bold text-orange-600">${progressSummary.pending_steps}</div>
                                <div class="text-sm text-gray-500">Steps Pending</div>
                            </div>
                        </div>
                        <div class="mt-2 text-sm text-gray-600">
                            Current Phase: <span class="font-semibold text-indigo-600">${progressSummary.current_phase}</span>
                        </div>
                    </div>
                </div>
            `;
            lucide.createIcons();
        }

        function displayJourneyTimeline(journeyPhases, timelineSteps, recentAchievements) {
            const timelineDiv = document.getElementById('journeyTimeline');
            
            if (!journeyPhases || Object.keys(journeyPhases).length === 0) {
                timelineDiv.innerHTML = `
                    <div class="text-center py-12">
                        <i data-lucide="map-pin-off" class="w-16 h-16 mx-auto mb-4 text-gray-400"></i>
                        <p class="text-gray-500 text-lg">No journey phases found for this contact</p>
                    </div>
                `;
                lucide.createIcons();
                return;
            }

            let timelineHtml = `
                <div class="mb-8">
                    <h3 class="text-2xl font-bold text-gray-900 mb-2">Professional Journey Timeline</h3>
                    <p class="text-gray-600 mb-6">Track the complete customer journey from initial contact to successful onboarding</p>
                </div>
            `;

            // Recent Achievements Section
            if (recentAchievements && recentAchievements.length > 0) {
                timelineHtml += `
                    <div class="mb-8 bg-green-50 border border-green-200 rounded-xl p-6">
                        <h4 class="text-lg font-semibold text-green-800 mb-4 flex items-center">
                            <i data-lucide="trophy" class="w-5 h-5 mr-2"></i>
                            Recent Achievements
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                `;
                
                recentAchievements.forEach(achievement => {
                    timelineHtml += `
                        <div class="bg-white border border-green-200 rounded-lg p-4">
                            <div class="flex items-center mb-2">
                                <i data-lucide="${achievement.icon}" class="w-4 h-4 text-green-600 mr-2"></i>
                                <span class="font-medium text-green-800">${achievement.title}</span>
                            </div>
                            <p class="text-sm text-gray-600">${achievement.description}</p>
                            <p class="text-xs text-green-600 mt-1">${formatDate(achievement.achieved_at)}</p>
                        </div>
                    `;
                });

                timelineHtml += `
                        </div>
                    </div>
                `;
            }

            // Journey Phases
            const phaseEntries = Object.entries(journeyPhases);
            phaseEntries.forEach(([phaseName, phase], phaseIndex) => {
                const isLastPhase = phaseIndex === phaseEntries.length - 1;
                const phaseStatusColor = phase.status === 'completed' ? 'green' : 
                                       phase.status === 'in_progress' ? 'blue' : 'gray';

                timelineHtml += `
                    <div class="journey-phase">
                        ${!isLastPhase ? `<div class="phase-connector ${phase.status}"></div>` : ''}
                        
                        <!-- Phase Header -->
                        <div class="phase-header bg-white border-2 border-${phaseStatusColor}-200 rounded-xl p-6 mb-6">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-4">
                                    <div class="w-16 h-16 bg-${phaseStatusColor}-100 rounded-full flex items-center justify-center">
                                        <i data-lucide="${phase.icon}" class="w-8 h-8 text-${phaseStatusColor}-600"></i>
                                    </div>
                                    <div>
                                        <h4 class="text-xl font-bold text-gray-900">${phase.title}</h4>
                                        <p class="text-gray-600">${phase.description}</p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-3xl font-bold text-${phaseStatusColor}-600">${phase.completion_percentage}%</div>
                                    <div class="text-sm text-gray-500 capitalize">${phase.status.replace('_', ' ')}</div>
                                </div>
                            </div>
                            <div class="mt-4">
                                <div class="w-full bg-${phaseStatusColor}-200 rounded-full h-3">
                                    <div class="bg-${phaseStatusColor}-500 h-3 rounded-full transition-all duration-500" style="width: ${phase.completion_percentage}%"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Phase Steps -->
                        <div class="ml-8">
                `;

                phase.steps.forEach((step, stepIndex) => {
                    const isLastStep = stepIndex === phase.steps.length - 1;
                    const stepStatusIcon = step.status === 'achieved' ? 'check-circle' : 'circle';
                    const stepStatusColor = step.status === 'achieved' ? 'green' : 'gray';

                    timelineHtml += `
                        <div class="step-timeline mb-6">
                            ${!isLastStep ? `<div class="step-connector ${step.status}"></div>` : ''}
                            <div class="step-dot ${step.status}"></div>
                            
                            <div class="bg-white border border-gray-200 rounded-lg p-4 shadow-sm ${step.status === 'achieved' ? 'border-green-200 bg-green-50' : ''}">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center mb-2">
                                            <i data-lucide="${step.icon}" class="w-5 h-5 text-${step.color}-500 mr-2"></i>
                                            <h5 class="font-semibold text-gray-900">${step.title}</h5>
                                            <span class="ml-3 px-3 py-1 text-xs rounded-full ${getStatusBadgeClass(step.status)}">
                                                ${step.status === 'achieved' ? '✓ ACHIEVED' : '⏳ PENDING'}
                                            </span>
                                        </div>
                                        <p class="text-gray-600 text-sm mb-3">${step.description}</p>
                                        
                                        ${step.status === 'achieved' && step.interactions.length > 0 ? `
                                            <div class="bg-white border border-green-200 rounded-lg p-3 mt-3">
                                                <h6 class="font-medium text-green-800 mb-2">Achievement Details:</h6>
                                                ${step.interactions.map(interaction => `
                                                    <div class="text-sm text-gray-700 mb-1">
                                                        <strong>${interaction.message}</strong>
                                                        <p class="text-gray-600">${interaction.details}</p>
                                                        <p class="text-xs text-green-600 mt-1">${formatDate(interaction.created_at)}</p>
                                                    </div>
                                                `).join('')}
                                            </div>
                                        ` : ''}
                                        
                                        ${step.status === 'pending' ? `
                                            <div class="bg-gray-50 border border-gray-200 rounded-lg p-3 mt-3">
                                                <p class="text-sm text-gray-600 italic">This step has not been completed yet.</p>
                                            </div>
                                        ` : ''}
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                });

                timelineHtml += `
                        </div>
                    </div>
                `;
            });

            timelineDiv.innerHTML = timelineHtml;
            lucide.createIcons();
        }

        function getStatusBadgeClass(status) {
            return status === 'achieved' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600';
        }

        function formatDate(dateString) {
            if (!dateString) return 'N/A';
            return new Date(dateString).toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        // Allow Enter key to submit
        document.getElementById('contactInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                loadJourneyTimeline();
            }
        });

        // Auto-load for ashok@reliance.in if specified in URL
        window.addEventListener('load', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const contact = urlParams.get('contact');
            if (contact) {
                document.getElementById('contactInput').value = contact;
                loadJourneyTimeline();
            }
        });
    </script>
</body>
</html>

