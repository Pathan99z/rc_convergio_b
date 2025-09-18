<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the first user as tenant
        $user = User::first();
        if (!$user) {
            return;
        }

        $templates = [
            [
                'name' => 'Welcome Email Template',
                'subject' => 'Welcome to {{company}}, {{first_name}}!',
                'content' => '
                    <h2>Welcome to RC Convergio CRM, {{first_name}}!</h2>
                    <p>Thank you for joining us. We\'re excited to help you manage your business relationships more effectively.</p>
                    <p>Here\'s what you can do with RC Convergio:</p>
                    <ul>
                        <li>Manage your contacts and companies</li>
                        <li>Track deals and opportunities</li>
                        <li>Automate your marketing campaigns</li>
                        <li>Generate detailed reports</li>
                    </ul>
                    <p>If you have any questions, feel free to reach out to us.</p>
                    <p>Best regards,<br>The RC Convergio Team</p>
                ',
                'description' => 'Welcome email for new contacts',
                'type' => 'welcome',
                'tenant_id' => $user->tenant_id ?? $user->id,
                'created_by' => $user->id,
            ],
            [
                'name' => 'Follow-up Email Template',
                'subject' => 'Hi {{first_name}}, I see you\'re interested!',
                'content' => '
                    <h2>Hi {{first_name}},</h2>
                    <p>I noticed you\'ve been exploring our platform. That\'s great!</p>
                    <p>I\'d love to help you get the most out of RC Convergio CRM. Here are some resources that might be helpful:</p>
                    <ul>
                        <li><a href="#">Getting Started Guide</a></li>
                        <li><a href="#">Video Tutorials</a></li>
                        <li><a href="#">Best Practices</a></li>
                    </ul>
                    <p>Would you like to schedule a quick demo to see how RC Convergio can help your business?</p>
                    <p>Best regards,<br>Your RC Convergio Team</p>
                ',
                'description' => 'Follow-up email for engaged contacts',
                'type' => 'follow_up',
                'tenant_id' => $user->tenant_id ?? $user->id,
                'created_by' => $user->id,
            ],
            [
                'name' => 'Sales Email Template',
                'subject' => 'Ready to get started, {{first_name}}?',
                'content' => '
                    <h2>Hi {{first_name}},</h2>
                    <p>I see you\'re interested in our services. That\'s fantastic!</p>
                    <p>Based on your interest, I believe RC Convergio CRM would be a perfect fit for {{company}}.</p>
                    <p>Here\'s what we can offer:</p>
                    <ul>
                        <li>Unlimited contacts and companies</li>
                        <li>Advanced automation features</li>
                        <li>24/7 customer support</li>
                        <li>30-day free trial</li>
                    </ul>
                    <p><a href="#" style="background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Start Your Free Trial</a></p>
                    <p>Questions? Just reply to this email!</p>
                    <p>Best regards,<br>Your Sales Team</p>
                ',
                'description' => 'Sales email for interested prospects',
                'type' => 'sales',
                'tenant_id' => $user->tenant_id ?? $user->id,
                'created_by' => $user->id,
            ],
            [
                'name' => 'Thank You Email Template',
                'subject' => 'Thank you, {{first_name}}!',
                'content' => '
                    <h2>Thank you, {{first_name}}!</h2>
                    <p>We\'ve received your information and really appreciate your interest in RC Convergio CRM.</p>
                    <p>Our team will review your request and get back to you within 24 hours.</p>
                    <p>In the meantime, feel free to explore our resources:</p>
                    <ul>
                        <li><a href="#">Product Features</a></li>
                        <li><a href="#">Pricing Plans</a></li>
                        <li><a href="#">Customer Stories</a></li>
                    </ul>
                    <p>We look forward to helping you grow your business!</p>
                    <p>Best regards,<br>The RC Convergio Team</p>
                ',
                'description' => 'Thank you email for form submissions',
                'type' => 'thank_you',
                'tenant_id' => $user->tenant_id ?? $user->id,
                'created_by' => $user->id,
            ],
        ];

        foreach ($templates as $template) {
            EmailTemplate::create($template);
        }
    }
}