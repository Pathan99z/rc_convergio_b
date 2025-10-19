<?php

namespace Database\Seeders;

use App\Models\Help\Article;
use App\Models\Help\ArticleFeedback;
use App\Models\Help\Category;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class HelpDemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function () {
            // Get the first user as a default tenant
            $user = User::first();
            
            if (!$user) {
                $this->command->warn('No users found. Please create a user first.');
                return;
            }

            $tenantId = $user->tenant_id ?? $user->id;
            $this->seedTenantData($tenantId, $user->id);
            
            $this->command->info('Help center demo data seeded successfully for tenant: ' . $tenantId);
        });
    }

    /**
     * Seed data for a specific tenant.
     */
    private function seedTenantData(int $tenantId, int $userId): void
    {
        // Create categories
        $categories = $this->createCategories($tenantId, $userId);
        
        // Create articles
        $articles = $this->createArticles($tenantId, $userId, $categories);
        
        // Create sample feedback
        $this->createSampleFeedback($articles);
        
        // Create sample views
        $this->createSampleViews($articles);
    }

    /**
     * Create default categories.
     */
    private function createCategories(int $tenantId, int $userId): array
    {
        $categories = [
            [
                'name' => 'Getting Started',
                'slug' => 'getting-started',
                'description' => 'Learn the basics of using our platform',
            ],
            [
                'name' => 'Account & Billing',
                'slug' => 'account-billing',
                'description' => 'Manage your account settings and billing information',
            ],
            [
                'name' => 'Troubleshooting',
                'slug' => 'troubleshooting',
                'description' => 'Common issues and their solutions',
            ],
            [
                'name' => 'Integrations',
                'slug' => 'integrations',
                'description' => 'Connect with third-party services and tools',
            ],
        ];

        $createdCategories = [];
        foreach ($categories as $categoryData) {
        $category = \App\Models\Help\Category::create([
            'tenant_id' => $tenantId,
            'name' => $categoryData['name'],
            'slug' => $categoryData['slug'],
            'description' => $categoryData['description'],
            'created_by' => $userId,
        ]);
            $createdCategories[] = $category;
        }

        return $createdCategories;
    }

    /**
     * Create sample articles.
     */
    private function createArticles(int $tenantId, int $userId, array $categories): array
    {
        $articles = [
            // Getting Started
            [
                'category' => $categories[0],
                'title' => 'Welcome to RC Convergio',
                'slug' => 'welcome-to-rc-convergio',
                'summary' => 'Get started with RC Convergio CRM and learn the basics of managing your business relationships.',
                'content' => $this->getWelcomeContent(),
                'status' => 'published',
            ],
            [
                'category' => $categories[0],
                'title' => 'Creating Your First Contact',
                'slug' => 'creating-your-first-contact',
                'summary' => 'Learn how to add and manage contacts in your CRM system.',
                'content' => $this->getContactCreationContent(),
                'status' => 'published',
            ],
            [
                'category' => $categories[0],
                'title' => 'Setting Up Your Dashboard',
                'slug' => 'setting-up-your-dashboard',
                'summary' => 'Customize your dashboard to see the information that matters most to you.',
                'content' => $this->getDashboardContent(),
                'status' => 'published',
            ],

            // Account & Billing
            [
                'category' => $categories[1],
                'title' => 'Managing Your Subscription',
                'slug' => 'managing-your-subscription',
                'summary' => 'Learn how to upgrade, downgrade, or cancel your subscription.',
                'content' => $this->getSubscriptionContent(),
                'status' => 'published',
            ],
            [
                'category' => $categories[1],
                'title' => 'Billing and Payment Methods',
                'slug' => 'billing-and-payment-methods',
                'summary' => 'Update your payment information and view billing history.',
                'content' => $this->getBillingContent(),
                'status' => 'published',
            ],

            // Troubleshooting
            [
                'category' => $categories[2],
                'title' => 'Login Issues',
                'slug' => 'login-issues',
                'summary' => 'Having trouble logging in? Here are common solutions.',
                'content' => $this->getLoginIssuesContent(),
                'status' => 'published',
            ],
            [
                'category' => $categories[2],
                'title' => 'Email Notifications Not Working',
                'slug' => 'email-notifications-not-working',
                'summary' => 'Troubleshoot email notification problems.',
                'content' => $this->getEmailNotificationsContent(),
                'status' => 'published',
            ],

            // Integrations
            [
                'category' => $categories[3],
                'title' => 'Connecting Google Workspace',
                'slug' => 'connecting-google-workspace',
                'summary' => 'Integrate your Google Workspace with RC Convergio.',
                'content' => $this->getGoogleWorkspaceContent(),
                'status' => 'published',
            ],
            [
                'category' => $categories[3],
                'title' => 'API Documentation',
                'slug' => 'api-documentation',
                'summary' => 'Learn how to use our API to integrate with your existing systems.',
                'content' => $this->getApiDocumentationContent(),
                'status' => 'published',
            ],
        ];

        $createdArticles = [];
        foreach ($articles as $articleData) {
            $article = \App\Models\Help\Article::create([
                'tenant_id' => $tenantId,
                'category_id' => $articleData['category']->id,
                'title' => $articleData['title'],
                'slug' => $articleData['slug'],
                'summary' => $articleData['summary'],
                'content' => $articleData['content'],
                'status' => $articleData['status'],
                'published_at' => now(),
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
            $createdArticles[] = $article;
        }

        return $createdArticles;
    }

    /**
     * Create sample feedback.
     */
    private function createSampleFeedback(array $articles): void
    {
        foreach ($articles as $article) {
            // Create some helpful feedback
            for ($i = 0; $i < rand(5, 15); $i++) {
                \App\Models\Help\ArticleFeedback::create([
                    'tenant_id' => $article->tenant_id,
                    'article_id' => $article->id,
                    'feedback' => rand(0, 1) ? 'helpful' : 'not_helpful',
                    'contact_email' => 'user' . $i . '@example.com',
                    'contact_name' => 'User ' . $i,
                ]);
            }
        }
    }

    /**
     * Create sample views.
     */
    private function createSampleViews(array $articles): void
    {
        foreach ($articles as $article) {
            $viewCount = rand(10, 100);
            
            // Update the article's view count
            $article->update(['views' => $viewCount]);
            
            // Create some view records
            for ($i = 0; $i < min($viewCount, 20); $i++) {
                \App\Models\Help\ArticleView::create([
                    'tenant_id' => $article->tenant_id,
                    'article_id' => $article->id,
                    'ip_address' => '192.168.1.' . rand(1, 254),
                    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'viewed_at' => now()->subDays(rand(0, 30)),
                ]);
            }
        }
    }

    /**
     * Get welcome content.
     */
    private function getWelcomeContent(): string
    {
        return '<h2>Welcome to RC Convergio!</h2>
        <p>RC Convergio is a powerful CRM platform designed to help you manage your business relationships, track deals, and grow your business.</p>
        
        <h3>Key Features</h3>
        <ul>
            <li><strong>Contact Management:</strong> Store and organize all your customer information</li>
            <li><strong>Deal Tracking:</strong> Monitor your sales pipeline and close more deals</li>
            <li><strong>Task Management:</strong> Stay organized with built-in task tracking</li>
            <li><strong>Email Integration:</strong> Connect your email for seamless communication</li>
            <li><strong>Reporting:</strong> Get insights into your business performance</li>
        </ul>
        
        <h3>Getting Started</h3>
        <p>To get the most out of RC Convergio, we recommend:</p>
        <ol>
            <li>Import your existing contacts</li>
            <li>Set up your sales pipeline</li>
            <li>Configure your email integration</li>
            <li>Create your first deal</li>
        </ol>
        
        <p>Need help? Check out our other articles or contact our support team.</p>';
    }

    /**
     * Get contact creation content.
     */
    private function getContactCreationContent(): string
    {
        return '<h2>Creating Your First Contact</h2>
        <p>Contacts are the foundation of your CRM. Here\'s how to add your first contact:</p>
        
        <h3>Step 1: Navigate to Contacts</h3>
        <p>Click on the "Contacts" menu item in the main navigation.</p>
        
        <h3>Step 2: Add New Contact</h3>
        <p>Click the "Add Contact" button to open the contact creation form.</p>
        
        <h3>Step 3: Fill in Contact Information</h3>
        <ul>
            <li><strong>Name:</strong> Enter the contact\'s first and last name</li>
            <li><strong>Email:</strong> Add their email address</li>
            <li><strong>Phone:</strong> Include their phone number</li>
            <li><strong>Company:</strong> Associate them with a company</li>
            <li><strong>Notes:</strong> Add any additional information</li>
        </ul>
        
        <h3>Step 4: Save the Contact</h3>
        <p>Click "Save" to create the contact. You can now view, edit, or add them to deals.</p>
        
        <h3>Tips</h3>
        <ul>
            <li>Use consistent naming conventions</li>
            <li>Add as much information as possible</li>
            <li>Use tags to categorize contacts</li>
        </ul>';
    }

    /**
     * Get dashboard content.
     */
    private function getDashboardContent(): string
    {
        return '<h2>Setting Up Your Dashboard</h2>
        <p>Your dashboard is your command center. Here\'s how to customize it for your needs:</p>
        
        <h3>Default Widgets</h3>
        <p>Your dashboard comes with several default widgets:</p>
        <ul>
            <li><strong>Recent Activity:</strong> Shows your latest interactions</li>
            <li><strong>Deal Pipeline:</strong> Visual representation of your sales process</li>
            <li><strong>Upcoming Tasks:</strong> Tasks that need your attention</li>
            <li><strong>Quick Stats:</strong> Key metrics at a glance</li>
        </ul>
        
        <h3>Customizing Your Dashboard</h3>
        <p>You can customize your dashboard by:</p>
        <ol>
            <li>Clicking the "Customize" button</li>
            <li>Dragging widgets to rearrange them</li>
            <li>Adding or removing widgets</li>
            <li>Resizing widgets to fit your preferences</li>
        </ol>
        
        <h3>Available Widgets</h3>
        <ul>
            <li>Deal Pipeline Chart</li>
            <li>Revenue Trends</li>
            <li>Contact Growth</li>
            <li>Task Completion Rate</li>
            <li>Recent Emails</li>
            <li>Calendar Events</li>
        </ul>';
    }

    /**
     * Get subscription content.
     */
    private function getSubscriptionContent(): string
    {
        return '<h2>Managing Your Subscription</h2>
        <p>You can manage your subscription from your account settings:</p>
        
        <h3>Viewing Your Current Plan</h3>
        <p>To see your current subscription details:</p>
        <ol>
            <li>Go to Settings > Billing</li>
            <li>View your current plan and usage</li>
            <li>See your next billing date</li>
        </ol>
        
        <h3>Upgrading Your Plan</h3>
        <p>To upgrade to a higher plan:</p>
        <ol>
            <li>Go to Settings > Billing</li>
            <li>Click "Upgrade Plan"</li>
            <li>Select your new plan</li>
            <li>Confirm the changes</li>
        </ol>
        
        <h3>Downgrading Your Plan</h3>
        <p>To downgrade your plan:</p>
        <ol>
            <li>Go to Settings > Billing</li>
            <li>Click "Change Plan"</li>
            <li>Select a lower plan</li>
            <li>Changes take effect at your next billing cycle</li>
        </ol>
        
        <h3>Canceling Your Subscription</h3>
        <p>To cancel your subscription:</p>
        <ol>
            <li>Go to Settings > Billing</li>
            <li>Click "Cancel Subscription"</li>
            <li>Follow the cancellation process</li>
            <li>Your account will remain active until the end of your billing period</li>
        </ol>';
    }

    /**
     * Get billing content.
     */
    private function getBillingContent(): string
    {
        return '<h2>Billing and Payment Methods</h2>
        <p>Manage your billing information and payment methods:</p>
        
        <h3>Updating Payment Information</h3>
        <p>To update your payment method:</p>
        <ol>
            <li>Go to Settings > Billing</li>
            <li>Click "Update Payment Method"</li>
            <li>Enter your new card information</li>
            <li>Save the changes</li>
        </ol>
        
        <h3>Viewing Billing History</h3>
        <p>To view your past invoices:</p>
        <ol>
            <li>Go to Settings > Billing</li>
            <li>Click "Billing History"</li>
            <li>Download any invoice as PDF</li>
        </ol>
        
        <h3>Billing Address</h3>
        <p>To update your billing address:</p>
        <ol>
            <li>Go to Settings > Billing</li>
            <li>Click "Update Billing Address"</li>
            <li>Enter your new address</li>
            <li>Save the changes</li>
        </ol>
        
        <h3>Tax Information</h3>
        <p>For tax-exempt organizations:</p>
        <ol>
            <li>Go to Settings > Billing</li>
            <li>Click "Tax Information"</li>
            <li>Upload your tax-exempt certificate</li>
            <li>Our team will review and apply the exemption</li>
        </ol>';
    }

    /**
     * Get login issues content.
     */
    private function getLoginIssuesContent(): string
    {
        return '<h2>Login Issues</h2>
        <p>Having trouble logging in? Here are the most common solutions:</p>
        
        <h3>Forgot Password</h3>
        <p>If you\'ve forgotten your password:</p>
        <ol>
            <li>Go to the login page</li>
            <li>Click "Forgot Password"</li>
            <li>Enter your email address</li>
            <li>Check your email for reset instructions</li>
        </ol>
        
        <h3>Account Locked</h3>
        <p>If your account is locked due to too many failed attempts:</p>
        <ol>
            <li>Wait 15 minutes before trying again</li>
            <li>Use the "Forgot Password" feature</li>
            <li>Contact support if the issue persists</li>
        </ol>
        
        <h3>Two-Factor Authentication</h3>
        <p>If you\'re having trouble with 2FA:</p>
        <ol>
            <li>Make sure your device time is correct</li>
            <li>Check that you\'re entering the code correctly</li>
            <li>Use a backup code if available</li>
            <li>Contact support to disable 2FA if needed</li>
        </ol>
        
        <h3>Browser Issues</h3>
        <p>If you\'re experiencing browser-related issues:</p>
        <ul>
            <li>Clear your browser cache and cookies</li>
            <li>Try using a different browser</li>
            <li>Disable browser extensions temporarily</li>
            <li>Make sure JavaScript is enabled</li>
        </ul>';
    }

    /**
     * Get email notifications content.
     */
    private function getEmailNotificationsContent(): string
    {
        return '<h2>Email Notifications Not Working</h2>
        <p>If you\'re not receiving email notifications, try these solutions:</p>
        
        <h3>Check Your Spam Folder</h3>
        <p>Email notifications might be going to your spam folder:</p>
        <ol>
            <li>Check your spam/junk folder</li>
            <li>Mark our emails as "Not Spam"</li>
            <li>Add our email address to your contacts</li>
        </ol>
        
        <h3>Update Your Email Settings</h3>
        <p>Make sure your email preferences are correct:</p>
        <ol>
            <li>Go to Settings > Notifications</li>
            <li>Check your email notification preferences</li>
            <li>Verify your email address is correct</li>
            <li>Save any changes</li>
        </ol>
        
        <h3>Email Provider Issues</h3>
        <p>Some email providers block automated emails:</p>
        <ul>
            <li>Contact your IT department if using a corporate email</li>
            <li>Try using a personal email address (Gmail, Outlook, etc.)</li>
            <li>Check if your email provider has any restrictions</li>
        </ul>
        
        <h3>Still Not Working?</h3>
        <p>If you\'re still not receiving emails:</p>
        <ol>
            <li>Contact our support team</li>
            <li>Provide your email address and tenant ID</li>
            <li>We\'ll investigate the issue</li>
        </ol>';
    }

    /**
     * Get Google Workspace content.
     */
    private function getGoogleWorkspaceContent(): string
    {
        return '<h2>Connecting Google Workspace</h2>
        <p>Integrate your Google Workspace with RC Convergio for seamless email and calendar sync:</p>
        
        <h3>Prerequisites</h3>
        <ul>
            <li>Google Workspace admin access</li>
            <li>Domain verification</li>
            <li>API access enabled</li>
        </ul>
        
        <h3>Setup Process</h3>
        <ol>
            <li>Go to Settings > Integrations</li>
            <li>Click "Connect Google Workspace"</li>
            <li>Authorize the connection</li>
            <li>Configure sync settings</li>
            <li>Test the integration</li>
        </ol>
        
        <h3>What Gets Synced</h3>
        <ul>
            <li><strong>Emails:</strong> Two-way sync with Gmail</li>
            <li><strong>Calendar:</strong> Meeting scheduling and updates</li>
            <li><strong>Contacts:</strong> Google Contacts integration</li>
            <li><strong>Drive:</strong> Document attachments</li>
        </ul>
        
        <h3>Troubleshooting</h3>
        <p>Common issues and solutions:</p>
        <ul>
            <li><strong>Permission Denied:</strong> Check admin permissions</li>
            <li><strong>Sync Errors:</strong> Verify API quotas</li>
            <li><strong>Missing Data:</strong> Check sync frequency settings</li>
        </ul>';
    }

    /**
     * Get API documentation content.
     */
    private function getApiDocumentationContent(): string
    {
        return '<h2>API Documentation</h2>
        <p>Use our REST API to integrate RC Convergio with your existing systems:</p>
        
        <h3>Authentication</h3>
        <p>All API requests require authentication using API tokens:</p>
        <pre><code>Authorization: Bearer YOUR_API_TOKEN</code></pre>
        
        <h3>Base URL</h3>
        <p>All API endpoints are relative to:</p>
        <pre><code>https://your-domain.com/api/</code></pre>
        
        <h3>Common Endpoints</h3>
        <ul>
            <li><strong>GET /contacts</strong> - List contacts</li>
            <li><strong>POST /contacts</strong> - Create contact</li>
            <li><strong>GET /deals</strong> - List deals</li>
            <li><strong>POST /deals</strong> - Create deal</li>
            <li><strong>GET /tasks</strong> - List tasks</li>
        </ul>
        
        <h3>Rate Limits</h3>
        <p>API requests are limited to:</p>
        <ul>
            <li>1000 requests per hour per API token</li>
            <li>100 requests per minute per endpoint</li>
        </ul>
        
        <h3>Getting Help</h3>
        <p>For API support:</p>
        <ul>
            <li>Check our API documentation</li>
            <li>Contact our developer support team</li>
            <li>Join our developer community</li>
        </ul>';
    }
}
