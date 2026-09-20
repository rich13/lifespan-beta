<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class NewSpanModalController extends Controller
{
    /**
     * Return the Create New Span modal fragment.
     * Loaded on demand so span types and modal HTML are not on every pageload.
     */
    public function show(): View
    {
        return view('components.modals.new-span-modal');
    }
}
