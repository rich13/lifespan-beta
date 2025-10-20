<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use App\Models\Group;

class AssignUserToGroupByDomain
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(Registered $event): void
    {
        $user = $event->user;
        $email = $user->email;

        Log::info('Checking user email domain for group assignment', [
            'user_id' => $user->id,
            'email' => $email
        ]);

        // Check if email ends with unthinkabledigital.co.uk
        if (str_ends_with(strtolower($email), '@unthinkabledigital.co.uk')) {
            // Find the Unthinkable group
            $group = Group::where('name', 'Unthinkable')->first();

            if ($group) {
                // Add user to the group
                $group->addMember($user);

                Log::info('User automatically added to Unthinkable group', [
                    'user_id' => $user->id,
                    'email' => $email,
                    'group_id' => $group->id,
                    'group_name' => $group->name
                ]);
            } else {
                Log::warning('Unthinkable group not found for auto-assignment', [
                    'user_id' => $user->id,
                    'email' => $email
                ]);
            }
        } else {
            Log::info('Email domain does not match auto-assignment criteria', [
                'user_id' => $user->id,
                'email' => $email
            ]);
        }
    }
}

