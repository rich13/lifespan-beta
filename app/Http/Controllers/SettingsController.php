<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use App\Models\Span;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class SettingsController extends Controller
{
    /**
     * Show the main settings page
     */
    public function index(): View
    {
        $user = auth()->user();

        // Load only personalSpan; avoid loading createdSpans/updatedSpans to prevent
        // memory exhaustion for users with many spans (stats use count() queries).
        $user->load('personalSpan');

        // Get user statistics
        $stats = [
            'total_spans_created' => $user->createdSpans()->count(),
            'total_spans_updated' => $user->updatedSpans()->count(),
            'public_spans' => $user->createdSpans()->where('access_level', 'public')->count(),
            'private_spans' => $user->createdSpans()->where('access_level', 'private')->count(),
            'shared_spans' => $user->createdSpans()->where('access_level', 'shared')->count(),
        ];
        
        // Get recent activity
        $recentSpans = $user->createdSpans()
            ->with('type')
            ->orderBy('updated_at', 'desc')
            ->limit(5)
            ->get();
            
        // Get connection statistics if user has a personal span
        $connectionStats = [];
        if ($user->personalSpan) {
            $personalSpan = $user->personalSpan;
            
            $connectionStats = [
                'total_connections' => $personalSpan->connectionsAsSubject()->count() + $personalSpan->connectionsAsObject()->count(),
                'connections_as_subject' => $personalSpan->connectionsAsSubject()->count(),
                'connections_as_object' => $personalSpan->connectionsAsObject()->count(),
                'temporal_connections' => $personalSpan->connectionsAsSubject()
                    ->whereNotNull('connection_span_id')
                    ->whereHas('connectionSpan', function($query) {
                        $query->whereNotNull('start_year');
                    })
                    ->count(),
            ];
            
            // Get recent connections
            $recentConnections = $personalSpan->connectionsAsSubject()
                ->with(['child', 'type', 'connectionSpan'])
                ->orderBy('created_at', 'desc')
                ->limit(3)
                ->get();
        } else {
            $recentConnections = collect();
        }
        
        // Get account statistics
        $accountStats = [
            'member_since' => $user->created_at->diffForHumans(),
            'last_active' => $user->updated_at->diffForHumans(),
            'email_verified' => $user->email_verified_at ? $user->email_verified_at->diffForHumans() : 'Not verified',
        ];
        
        return view('settings.index', [
            'user' => $user,
            'stats' => $stats,
            'connectionStats' => $connectionStats,
            'accountStats' => $accountStats,
            'recentSpans' => $recentSpans,
            'recentConnections' => $recentConnections,
        ]);
    }

    /**
     * Show the import settings page
     */
    public function import(): View
    {
        return view('settings.import');
    }

    /**
     * Show the notifications settings page
     */
    public function notifications(): View
    {
        return view('settings.notifications');
    }

    /**
     * Show the groups settings page
     */
    public function groups(): View
    {
        $user = auth()->user();
        
        // Get groups the user is a member of
        $memberGroups = $user->groups()
            ->with(['owner.personalSpan', 'users.personalSpan'])
            ->orderBy('name')
            ->get();
            
        // Get groups the user owns
        $ownedGroups = $user->ownedGroups()
            ->with(['users.personalSpan'])
            ->orderBy('name')
            ->get();
        
        return view('settings.groups', [
            'user' => $user,
            'memberGroups' => $memberGroups,
            'ownedGroups' => $ownedGroups,
        ]);
    }

    /**
     * Show the spans settings page
     */
    public function spans(): View
    {
        $user = auth()->user();
        
        // Get user's personal span with connections
        $personalSpan = $user->personalSpan;
        
        if ($personalSpan) {
            // Get all connections where the user is the subject (parent)
            $connectionsAsSubject = $personalSpan->connectionsAsSubject()
                ->with(['child', 'type', 'connectionSpan'])
                ->get();
                
            // Get all connections where the user is the object (child)
            $connectionsAsObject = $personalSpan->connectionsAsObject()
                ->with(['parent', 'type', 'connectionSpan'])
                ->get();
                
            // Prepare data for D3 graph
            $graphData = $this->prepareGraphData($personalSpan, $connectionsAsSubject, $connectionsAsObject);
        } else {
            $graphData = ['nodes' => [], 'links' => []];
        }
        
        // Get user's groups for perspective switching
        $userGroups = $user->groups()->orderBy('name')->get();
        
        return view('settings.spans', [
            'user' => $user,
            'personalSpan' => $personalSpan,
            'graphData' => $graphData,
            'spansData' => $this->getSpansData($personalSpan, $connectionsAsSubject, $connectionsAsObject),
            'connectionsData' => $this->getConnectionsData($connectionsAsSubject, $connectionsAsObject),
            'userGroups' => $userGroups,
        ]);
    }

    /**
     * Prepare data for D3 force-directed graph
     */
    private function prepareGraphData($personalSpan, $connectionsAsSubject, $connectionsAsObject): array
    {
        $nodes = [];
        $links = [];
        $nodeIds = [];
        $predicateIds = [];
        
        // Add personal span as central node
        $nodes[] = [
            'id' => $personalSpan->id,
            'name' => $personalSpan->name,
            'type' => $personalSpan->type_id,
            'isPersonal' => true,
            'group' => 1 // Central group
        ];
        $nodeIds[$personalSpan->id] = true;
        
        // Group connections by predicate type
        $predicateGroups = [];
        
        // Process connections where user is subject (parent)
        foreach ($connectionsAsSubject as $connection) {
            $predicateName = $connection->type->forward_predicate;
            $predicateKey = 'forward_' . $predicateName;
            
            if (!isset($predicateGroups[$predicateKey])) {
                $predicateGroups[$predicateKey] = [
                    'name' => $predicateName,
                    'type' => 'predicate',
                    'connections' => []
                ];
            }
            
            $predicateGroups[$predicateKey]['connections'][] = [
                'connection' => $connection,
                'target' => $connection->child,
                'direction' => 'forward'
            ];
        }
        
        // Process connections where user is object (child)
        foreach ($connectionsAsObject as $connection) {
            $predicateName = $connection->type->inverse_predicate;
            $predicateKey = 'inverse_' . $predicateName;
            
            if (!isset($predicateGroups[$predicateKey])) {
                $predicateGroups[$predicateKey] = [
                    'name' => $predicateName,
                    'type' => 'predicate',
                    'connections' => []
                ];
            }
            
            $predicateGroups[$predicateKey]['connections'][] = [
                'connection' => $connection,
                'target' => $connection->parent,
                'direction' => 'inverse'
            ];
        }
        
        // Create nodes and links from grouped predicates
        foreach ($predicateGroups as $predicateKey => $predicateGroup) {
            $predicateId = 'pred_' . $predicateKey;
            
            // Add predicate node
            $nodes[] = [
                'id' => $predicateId,
                'name' => $predicateGroup['name'],
                'type' => 'predicate',
                'isPersonal' => false,
                'group' => 2, // Predicate group
                'connectionCount' => count($predicateGroup['connections'])
            ];
            $predicateIds[$predicateId] = true;
            
            // Add target nodes and links for each connection
            foreach ($predicateGroup['connections'] as $connectionData) {
                $target = $connectionData['target'];
                $connection = $connectionData['connection'];
                
                // Add target node if not already added
                if (!isset($nodeIds[$target->id])) {
                    $nodes[] = [
                        'id' => $target->id,
                        'name' => $target->name,
                        'type' => $target->type_id,
                        'isPersonal' => false,
                        'group' => 3 // Connected span group
                    ];
                    $nodeIds[$target->id] = true;
                }
                
                // Add links based on direction
                if ($connectionData['direction'] === 'forward') {
                    // personal span -> predicate -> target
                    $links[] = [
                        'source' => $personalSpan->id,
                        'target' => $predicateId,
                        'type' => 'subject_to_predicate',
                        'year' => $connection->connectionSpan?->start_year ?? null
                    ];
                    
                    $links[] = [
                        'source' => $predicateId,
                        'target' => $target->id,
                        'type' => 'predicate_to_object',
                        'year' => $connection->connectionSpan?->start_year ?? null
                    ];
                } else {
                    // target -> predicate -> personal span
                    $links[] = [
                        'source' => $target->id,
                        'target' => $predicateId,
                        'type' => 'subject_to_predicate',
                        'year' => $connection->connectionSpan?->start_year ?? null
                    ];
                    
                    $links[] = [
                        'source' => $predicateId,
                        'target' => $personalSpan->id,
                        'type' => 'predicate_to_object',
                        'year' => $connection->connectionSpan?->start_year ?? null
                    ];
                }
            }
        }
        
        return [
            'nodes' => $nodes,
            'links' => $links
        ];
    }

    /**
     * Get detailed data for all spans in the network
     */
    private function getSpansData($personalSpan, $connectionsAsSubject, $connectionsAsObject): array
    {
        $spansData = [];
        $user = auth()->user();
        
        // Add personal span data
        $spansData[$personalSpan->id] = [
            'id' => $personalSpan->id,
            'name' => $personalSpan->name,
            'type' => $personalSpan->type_id,
            'isPersonal' => true,
            'description' => $personalSpan->description,
            'start_year' => $personalSpan->start_year,
            'end_year' => $personalSpan->end_year,
            'created_at' => $personalSpan->created_at->format('M j, Y'),
            'updated_at' => $personalSpan->updated_at->format('M j, Y'),
            'permissions' => $this->getHumanReadablePermissions($personalSpan, $user),
        ];
        
        // Collect all connected spans
        $connectedSpans = collect();
        
        // Add spans from subject connections
        foreach ($connectionsAsSubject as $connection) {
            $connectedSpans->push($connection->child);
        }
        
        // Add spans from object connections
        foreach ($connectionsAsObject as $connection) {
            $connectedSpans->push($connection->parent);
        }
        
        // Remove duplicates and add to spans data
        $connectedSpans->unique('id')->each(function ($span) use (&$spansData, $user) {
            $spansData[$span->id] = [
                'id' => $span->id,
                'name' => $span->name,
                'type' => $span->type_id,
                'isPersonal' => false,
                'description' => $span->description,
                'start_year' => $span->start_year,
                'end_year' => $span->end_year,
                'created_at' => $span->created_at->format('M j, Y'),
                'updated_at' => $span->updated_at->format('M j, Y'),
                'permissions' => $this->getHumanReadablePermissions($span, $user),
            ];
        });
        
        return $spansData;
    }

    /**
     * Get human-readable permissions for a span
     */
    private function getHumanReadablePermissions($span, $user): string
    {
        if ($span->isPublic()) {
            return 'Public - Anyone can see this';
        }
        
        if ($span->isPrivate()) {
            if ($span->owner_id === $user->id) {
                return 'Private - Only you can see this';
            } else {
                return 'Private - Only the owner can see this';
            }
        }
        
        if ($span->isShared()) {
            // Check for group permissions
            $groupPermissions = $span->spanPermissions()
                ->whereNotNull('group_id')
                ->where('permission_type', 'view')
                ->with('group')
                ->get();
            
            if ($groupPermissions->isNotEmpty()) {
                $groupNames = $groupPermissions->pluck('group.name')->join(', ');
                return "Shared with group(s): {$groupNames}";
            }
            
            // Check for individual user permissions
            $userPermissions = $span->spanPermissions()
                ->whereNotNull('user_id')
                ->where('permission_type', 'view')
                ->count();
            
            if ($userPermissions > 0) {
                return "Shared with {$userPermissions} other user(s)";
            }
            
            return 'Shared - Access granted to specific users/groups';
        }
        
        return 'Unknown permissions';
    }

    /**
     * Get connections data organized by predicate
     */
    private function getConnectionsData($connectionsAsSubject, $connectionsAsObject): array
    {
        $connectionsData = [];
        
        // Group connections by predicate
        $predicateGroups = [];
        
        // Process subject connections
        foreach ($connectionsAsSubject as $connection) {
            $predicateKey = 'forward_' . $connection->type->forward_predicate;
            
            if (!isset($predicateGroups[$predicateKey])) {
                $predicateGroups[$predicateKey] = [
                    'name' => $connection->type->forward_predicate,
                    'connections' => []
                ];
            }
            
            $predicateGroups[$predicateKey]['connections'][] = [
                'id' => $connection->id,
                'type_id' => $connection->type_id,
                'parent' => [
                    'id' => $connection->parent->id,
                    'name' => $connection->parent->name,
                    'type_id' => $connection->parent->type_id,
                ],
                'child' => [
                    'id' => $connection->child->id,
                    'name' => $connection->child->name,
                    'type_id' => $connection->child->type_id,
                ],
                'type' => [
                    'forward_predicate' => $connection->type->forward_predicate,
                    'inverse_predicate' => $connection->type->inverse_predicate,
                ],
                'connectionSpan' => $connection->connectionSpan ? [
                    'start_year' => $connection->connectionSpan->start_year,
                    'end_year' => $connection->connectionSpan->end_year,
                    'description' => $connection->connectionSpan->description,
                ] : null,
                'direction' => 'forward'
            ];
        }
        
        // Process object connections
        foreach ($connectionsAsObject as $connection) {
            $predicateKey = 'inverse_' . $connection->type->inverse_predicate;
            
            if (!isset($predicateGroups[$predicateKey])) {
                $predicateGroups[$predicateKey] = [
                    'name' => $connection->type->inverse_predicate,
                    'connections' => []
                ];
            }
            
            $predicateGroups[$predicateKey]['connections'][] = [
                'id' => $connection->id,
                'type_id' => $connection->type_id,
                'parent' => [
                    'id' => $connection->parent->id,
                    'name' => $connection->parent->name,
                    'type_id' => $connection->parent->type_id,
                ],
                'child' => [
                    'id' => $connection->child->id,
                    'name' => $connection->child->name,
                    'type_id' => $connection->child->type_id,
                ],
                'type' => [
                    'forward_predicate' => $connection->type->forward_predicate,
                    'inverse_predicate' => $connection->type->inverse_predicate,
                ],
                'connectionSpan' => $connection->connectionSpan ? [
                    'start_year' => $connection->connectionSpan->start_year,
                    'end_year' => $connection->connectionSpan->end_year,
                    'description' => $connection->connectionSpan->description,
                ] : null,
                'direction' => 'inverse'
            ];
        }
        
        // Convert to array format for JavaScript
        foreach ($predicateGroups as $predicateKey => $predicateGroup) {
            $connectionsData[$predicateKey] = [
                'name' => $predicateGroup['name'],
                'connections' => $predicateGroup['connections']
            ];
        }
        
        return $connectionsData;
    }

    /**
     * Show the account settings page
     */
    public function account(): View
    {
        $user = auth()->user();
        
        // Get account statistics
        $accountStats = [
            'member_since' => $user->created_at->diffForHumans(),
            'last_active' => $user->updated_at->diffForHumans(),
            'email_verified' => $user->email_verified_at ? $user->email_verified_at->diffForHumans() : 'Not verified',
        ];
        
        return view('settings.account', [
            'user' => $user,
            'accountStats' => $accountStats,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function updateProfile(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        
        // Update email if changed
        if ($request->has('email') && $user->email !== $request->email) {
            $user->email = $request->email;
            $user->email_verified_at = null;
        }

        // Update name in personal span if changed
        if ($request->has('name') && $user->personalSpan && $user->personalSpan->name !== $request->name) {
            $user->personalSpan->name = $request->name;
            $user->personalSpan->save();
        }

        $user->save();

        return Redirect::route('settings.account')->with('status', 'profile-updated');
    }

    /**
     * Update the user's password.
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('status', 'password-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        // Prevent admin users from deleting their accounts
        if ($request->user()->is_admin) {
            abort(403, 'Admin accounts cannot be deleted.');
        }

        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();
        $systemUser = User::where('email', 'system@lifespan.app')->first();

        // Create system user if it doesn't exist
        if (!$systemUser) {
            $systemUser = User::create([
                'email' => 'system@lifespan.app',
                'password' => Hash::make(Str::random(32)),
                'is_admin' => true,
                'email_verified_at' => now(),
            ]);
        }

        // Get the personal span ID before deleting
        $personalSpanId = $user->personal_span_id;

        // First, nullify the personal_span_id reference
        if ($personalSpanId) {
            $user->personal_span_id = null;
            $user->save();
        }

        // Now we can safely delete the personal span
        if ($personalSpanId) {
            Span::where('id', $personalSpanId)->delete();
        }

        // Then transfer ownership of remaining spans to system user
        Span::where('owner_id', $user->id)->update(['owner_id' => $systemUser->id]);
        Span::where('updater_id', $user->id)->update(['updater_id' => $systemUser->id]);

        Auth::logout();

        // Now we can safely delete the user
        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
} 