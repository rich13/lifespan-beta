<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Span;
use App\Models\User;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'admin']);
    }

    public function index(): View
    {
        $stats = [
            'total_spans' => Span::count(),
            'total_users' => User::count(),
            'public_spans' => DB::getDriverName() === 'pgsql'
                ? Span::whereRaw('(permissions & ?) > 0', [0004])->count()
                : Span::where('permissions', '&', 0004)->count(),
            'private_spans' => DB::getDriverName() === 'pgsql'
                ? Span::whereRaw('(permissions & ?) = 0', [0004])->count()
                : Span::where('permissions', '&', 0004, 0)->count(),
            'inherited_spans' => Span::where('permission_mode', 'inherit')->count(),
        ];

        return view('admin.dashboard', compact('stats'));
    }
} 