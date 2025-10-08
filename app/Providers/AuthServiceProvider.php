<?php

namespace App\Providers;

use App\Models\Contact;
use App\Models\Company;
use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\Stage;
use App\Models\Activity;
use App\Models\Task;
use App\Models\Campaign;
use App\Models\Form;
use App\Models\ContactList;
use App\Models\Event;
use App\Models\SequenceEnrollment;
use App\Models\User;
use App\Policies\ContactPolicy;
use App\Policies\CompanyPolicy;
use App\Policies\DealPolicy;
use App\Policies\PipelinePolicy;
use App\Policies\StagePolicy;
use App\Policies\ActivityPolicy;
use App\Policies\TaskPolicy;
use App\Policies\CampaignPolicy;
use App\Policies\FormPolicy;
use App\Policies\ContactListPolicy;
use App\Policies\EventPolicy;
use App\Policies\SequenceEnrollmentPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Auth\Notifications\ResetPassword;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Contact::class => ContactPolicy::class,
        Company::class => CompanyPolicy::class,
        Deal::class => DealPolicy::class,
        Pipeline::class => PipelinePolicy::class,
        Stage::class => StagePolicy::class,
        Activity::class => ActivityPolicy::class,
        Task::class => TaskPolicy::class,
        Campaign::class => CampaignPolicy::class,
        Form::class => FormPolicy::class,
        ContactList::class => ContactListPolicy::class,
        Event::class => EventPolicy::class,
        SequenceEnrollment::class => SequenceEnrollmentPolicy::class,
        User::class => UserPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        // Customize reset password URL to point to frontend
        ResetPassword::createUrlUsing(function ($user, string $token) {
            return config('app.frontend_url')."/reset-password?token=$token&email={$user->email}";
        });
    }
}


