<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class FooterController extends Controller
{
    /**
     * Return footer content for a specific type
     */
    public function content(Request $request, string $type): View
    {
        $allowedTypes = ['about', 'privacy', 'terms', 'contact'];
        
        if (!in_array($type, $allowedTypes)) {
            abort(404);
        }
        
        return view("components.footer.content.{$type}");
    }
}
