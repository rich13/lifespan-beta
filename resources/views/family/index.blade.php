@extends('layouts.app')

@section('page_title')
    Family
@endsection

@section('content')
<div class="container-fluid">
    @if($message)
        <div class="alert alert-warning" role="alert">
            <div class="d-flex">
                <div class="flex-shrink-0">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                </div>
                <div class="ms-3">
                    <h4 class="alert-heading">No Personal Span Found</h4>
                    <p class="mb-0">{{ $message }}</p>
                </div>
            </div>
        </div>
    @endif

    @if(!$span)
        <div class="alert alert-info" role="alert">
            <div class="d-flex">
                <div class="flex-shrink-0">
                    <i class="bi bi-info-circle-fill"></i>
                </div>
                <div class="ms-3">
                    <h4 class="alert-heading">No Personal Span Found</h4>
                    <p class="mb-0">You need to create a personal span to view your family tree. Please go to the Spans section and create a span for yourself first.</p>
                </div>
            </div>
        </div>
    @else
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
                <x-spans.partials.family-relationships :span="$span" :interactive="true" :columns="3" />
            </div>
        </div>
    @endif
</div>
@endsection 