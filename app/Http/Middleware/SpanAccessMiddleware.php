<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use App\Models\Span;
use Illuminate\Support\Str;

class SpanAccessMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $routeName = $request->route()->getName();
        
        // For index route
        if ($routeName === 'spans.index') {
            // For unauthenticated users, only show public spans
            if (!Auth::check()) {
                $request->merge(['access_filter' => 'public_only']);
            } else {
                $user = Auth::user();
                if (!$user->getEffectiveAdminStatus()) {
                    $request->merge(['access_filter' => 'user_accessible']);
                }
            }
            return $next($request);
        }

        // For show route (including JSON variant)
        if (in_array($routeName, ['spans.show', 'spans.show.json'])) {
            $span = $request->route('span') ?? $request->route('subject');
            
            // Handle case when span doesn't exist
            if (!$span) {
                return abort(404, 'Span not found');
            }
            
            // For unauthenticated users, redirect to login for non-public spans
            if (!Auth::check()) {
                if ($span->access_level !== 'public') {
                    if ($routeName === 'spans.show.json') {
                        return response()->json(['error' => 'Unauthorized'], 401);
                    }
                    return redirect()->route('login');
                }
                return $next($request);
            }

            // For authenticated users
            $user = Auth::user();
            
            // Admin can access all spans
            if ($user->getEffectiveAdminStatus()) {
                return $next($request);
            }

            // Owner can access their spans
            if ($span->owner_id === $user->id) {
                return $next($request);
            }

            // Fallback: Users can access spans they last updated
            // This covers spans created through various import paths where owner_id might be NULL or incorrect
            if ($span->updater_id === $user->id) {
                return $next($request);
            }

            // Special handling for connection spans
            // Users can access connection spans if they can access both the connected spans
            if ($span->type_id === 'connection') {
                // Find the connection record for this span
                $connection = \App\Models\Connection::where('connection_span_id', $span->id)->first();
                
                if ($connection) {
                    $parent = $connection->parent;
                    $child = $connection->child;
                    
                    $canAccessParent = $parent ? $parent->hasPermission($user, 'view') : false;
                    $canAccessChild = $child ? $child->hasPermission($user, 'view') : false;
                    
                    if ($parent && $child && $canAccessParent && $canAccessChild) {
                        return $next($request);
                    }
                }
            }

            // Public spans are visible to everyone
            if ($span->access_level === 'public') {
                return $next($request);
            }

            // For shared spans, check permissions
            if ($span->access_level === 'shared') {
                // Check for user-based permissions
                if ($span->spanPermissions()->where('user_id', $user->id)->exists()) {
                    return $next($request);
                }
                
                // Check for group-based permissions
                if ($span->spanPermissions()
                    ->where('permission_type', 'view')
                    ->whereNotNull('group_id')
                    ->whereHas('group', function ($query) use ($user) {
                        $query->whereHas('users', function ($userQuery) use ($user) {
                            $userQuery->where('user_id', $user->id);
                        });
                    })
                    ->exists()) {
                    return $next($request);
                }
                
                return abort(403, 'You do not have permission to view this span.');
            }

            // Private spans are only accessible to owner and admin
            return abort(403, 'This span is private.');
        }

        // For connection type routes and history routes (same logic as show route)
        if (in_array($routeName, ['spans.connection-types.index', 'spans.connection-types.show', 'spans.connection', 'spans.connection.json', 'spans.connection.by-id', 'spans.connection.by-id.json', 'spans.connections', 'spans.history', 'spans.history.version'])) {
            // For the specific connection route, we need to check both subject and object
            if (in_array($routeName, ['spans.connection', 'spans.connection.json', 'spans.connection.by-id', 'spans.connection.by-id.json'])) {
                $subject = $request->route('subject');
                $object = $request->route('object');
                
                // Handle case when spans don't exist
                if (!$subject || !$object) {
                    return abort(404, 'Span not found');
                }
                
                // For unauthenticated users, both spans must be public
                if (!Auth::check()) {
                    if ($subject->access_level !== 'public' || $object->access_level !== 'public') {
                        if (in_array($routeName, ['spans.connection.json', 'spans.connection.by-id.json'])) {
                            return response()->json(['error' => 'Unauthorized'], 401);
                        }
                        return redirect()->route('login');
                    }
                    return $next($request);
                }

                // For authenticated users
                $user = Auth::user();
                
                // Admin can access all spans
                if ($user->getEffectiveAdminStatus()) {
                    return $next($request);
                }

                // Check if user can access both spans
                $canAccessSubject = $subject->access_level === 'public' || 
                                  $subject->owner_id === $user->id ||
                                  ($subject->access_level === 'shared' && (
                                      $subject->spanPermissions()->where('user_id', $user->id)->exists() ||
                                      $subject->spanPermissions()
                                          ->where('permission_type', 'view')
                                          ->whereNotNull('group_id')
                                          ->whereHas('group', function ($query) use ($user) {
                                              $query->whereHas('users', function ($userQuery) use ($user) {
                                                  $userQuery->where('user_id', $user->id);
                                              });
                                          })
                                          ->exists()
                                  ));
                
                $canAccessObject = $object->access_level === 'public' || 
                                 $object->owner_id === $user->id ||
                                 ($object->access_level === 'shared' && (
                                     $object->spanPermissions()->where('user_id', $user->id)->exists() ||
                                     $object->spanPermissions()
                                         ->where('permission_type', 'view')
                                         ->whereNotNull('group_id')
                                         ->whereHas('group', function ($query) use ($user) {
                                             $query->whereHas('users', function ($userQuery) use ($user) {
                                                 $userQuery->where('user_id', $user->id);
                                             });
                                         })
                                         ->exists()
                                 ));
                
                if (!$canAccessSubject || !$canAccessObject) {
                    return abort(403, 'You do not have permission to view this connection.');
                }
                
                return $next($request);
            }
            
            // For the connections listing route, we need to check the subject
            if ($routeName === 'spans.connections') {
                $subject = $request->route('subject');
                
                // Handle case when span doesn't exist
                if (!$subject) {
                    return abort(404, 'Span not found');
                }
                
                // For unauthenticated users, redirect to login for non-public spans
                if (!Auth::check()) {
                    if ($subject->access_level !== 'public') {
                        return redirect()->route('login');
                    }
                    return $next($request);
                }

                // For authenticated users
                $user = Auth::user();
                
                // Admin can access all spans
                if ($user->getEffectiveAdminStatus()) {
                    return $next($request);
                }

                // Owner can access their spans
                if ($subject->owner_id === $user->id) {
                    return $next($request);
                }

                // Fallback: Users can access spans they last updated
                if ($subject->updater_id === $user->id) {
                    return $next($request);
                }

                // Public spans are visible to everyone
                if ($subject->access_level === 'public') {
                    return $next($request);
                }

                // For shared spans, check permissions
                if ($subject->access_level === 'shared') {
                    // Check for user-based permissions
                    if ($subject->spanPermissions()->where('user_id', $user->id)->exists()) {
                        return $next($request);
                    }
                    
                    // Check for group-based permissions
                    if ($subject->spanPermissions()
                        ->where('permission_type', 'view')
                        ->whereNotNull('group_id')
                        ->whereHas('group', function ($query) use ($user) {
                            $query->whereHas('users', function ($userQuery) use ($user) {
                                $userQuery->where('user_id', $user->id);
                            });
                        })
                        ->exists()) {
                        return $next($request);
                    }
                    
                    return abort(403, 'You do not have permission to view this span.');
                }

                // Private spans are only accessible to owner and admin
                return abort(403, 'This span is private.');
            }
            
            // For other connection type routes, use the span parameter
            $span = $request->route('span');
            
            // Handle case when span doesn't exist
            if (!$span) {
                return abort(404, 'Span not found');
            }
            
            // For unauthenticated users, redirect to login for non-public spans
            if (!Auth::check()) {
                if ($span->access_level !== 'public') {
                    return redirect()->route('login');
                }
                return $next($request);
            }

            // For authenticated users
            $user = Auth::user();
            
            // Admin can access all spans
            if ($user->getEffectiveAdminStatus()) {
                return $next($request);
            }

            // Owner can access their spans
            if ($span->owner_id === $user->id) {
                return $next($request);
            }

            // Fallback: Users can access spans they last updated
            // This covers spans created through various import paths where owner_id might be NULL or incorrect
            if ($span->updater_id === $user->id) {
                return $next($request);
            }

            // Special handling for connection spans
            // Users can access connection spans if they can access both the connected spans
            if ($span->type_id === 'connection') {
                // Find the connection record for this span
                $connection = \App\Models\Connection::where('connection_span_id', $span->id)->first();
                
                if ($connection) {
                    $parent = $connection->parent;
                    $child = $connection->child;
                    
                    $canAccessParent = $parent ? $parent->hasPermission($user, 'view') : false;
                    $canAccessChild = $child ? $child->hasPermission($user, 'view') : false;
                    
                    if ($parent && $child && $canAccessParent && $canAccessChild) {
                        return $next($request);
                    }
                }
            }

            // Public spans are visible to everyone
            if ($span->access_level === 'public') {
                return $next($request);
            }

            // For shared spans, check permissions
            if ($span->access_level === 'shared') {
                // Check for user-based permissions
                if ($span->spanPermissions()->where('user_id', $user->id)->exists()) {
                    return $next($request);
                }
                
                // Check for group-based permissions
                if ($span->spanPermissions()
                    ->where('permission_type', 'view')
                    ->whereNotNull('group_id')
                    ->whereHas('group', function ($query) use ($user) {
                        $query->whereHas('users', function ($userQuery) use ($user) {
                            $userQuery->where('user_id', $user->id);
                        });
                    })
                    ->exists()) {
                    return $next($request);
                }
                
                return abort(403, 'You do not have permission to view this span.');
            }

            // Private spans are only accessible to owner and admin
            return abort(403, 'This span is private.');
        }

        // For other routes that require authentication
        if (in_array($routeName, ['spans.edit', 'spans.update', 'spans.destroy', 'spans.create', 'spans.store'])) {
            if (!Auth::check()) {
                return redirect()->route('login');
            }
            return $next($request);
        }

        return $next($request);
    }
} 