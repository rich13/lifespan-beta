@extends('layouts.app')

@section('page_title')
    <x-breadcrumb :items="[
        [
            'text' => 'Spans',
            'icon' => 'view',
            'icon_category' => 'action',
            'url' => route('spans.index')
        ],
        [
            'text' => $span->name,
            'icon' => 'person',
            'icon_category' => 'action',
            'url' => route('spans.show', $span)
        ],
        [
            'text' => 'Family',
            'icon' => 'people',
            'icon_category' => 'action'
        ]
    ]" />
@endsection

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            {{-- Family Timeline Component --}}
            @php
                // Collect all family members for the timeline
                $ancestors = $span->ancestors(3);
                $descendants = $span->descendants(2);
                $siblings = $span->siblings();
                $unclesAndAunts = $span->unclesAndAunts();
                $cousins = $span->cousins();
                $nephewsAndNieces = $span->nephewsAndNieces();
                $extraNephewsAndNieces = $span->extraNephewsAndNieces();
                
                // Combine all family members into a single collection
                $allFamilyMembers = collect();
                $allFamilyMembers->push($span); // Add current person first
                $allFamilyMembers = $allFamilyMembers
                    ->concat($ancestors->pluck('span'))
                    ->concat($descendants->pluck('span'))
                    ->concat($siblings)
                    ->concat($unclesAndAunts)
                    ->concat($cousins)
                    ->concat($nephewsAndNieces)
                    ->concat($extraNephewsAndNieces)
                    ->unique('id')
                    ->filter(function($member) {
                        return $member->start_year !== null;
                    })
                    ->sortBy('start_year');
            @endphp
            
            <x-spans.timeline-group :spans="$allFamilyMembers" />
            
            {{-- Main Family Relationships Component --}}
            <x-spans.partials.family-relationships :span="$span" :interactive="true" :columns="2" />
        </div>
    </div>
</div>
@endsection 