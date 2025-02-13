@extends('layouts.app')

@section('content')
@guest
<div class="container">
    <!-- Hero / Jumbotron Section -->
    <div class="row">
        <div class="col-md-12">
            <!-- You could also replicate a 'jumbotron' with utility classes since jumbotron is deprecated in BS5 -->
            <div class="text-center p-5 rounded">
                <h1 class="display-4">Lifespan &beta;</h1>
            </div>
        </div>
    </div>

    <!-- Features: 6 Cards (2 rows x 3 columns) -->
    <div class="row mt-5">
        <!-- Card 1 -->
        <div class="col-md-4 text-center mb-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <div class="mb-3">
                        <i class="bi bi-archive" style="font-size: 2rem;"></i>
                    </div>
                    <h3 class="card-title">All these moments</h3>
                    <p class="card-text">
                        will be lost in time<br>
                        like tears...in... rain
                    </p>
                    <p class="card-text"><a href="/spans/roy-batty">Roy Batty</a></p>
                    <p class="card-text">8 January 2016 - 10 November 2019</p>
                </div>
            </div>
        </div>

        <!-- Card 2 -->
        <div class="col-md-4 text-center mb-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <div class="mb-3">
                        <i class="bi bi-globe2" style="font-size: 2rem;"></i>
                    </div>
                    <h3 class="card-title">Roads?</h3>
                    <p class="card-text">
                        Where we're going<br>
                        we don't need... roads.
                    </p>
                    <p class="card-text"><a href="/spans/dr-emmett-brown">Dr. Emmett Brown</a></p>
                    <p class="card-text">11 August 1920 - unknown</p>
                </div>
            </div>
        </div>

        <!-- Card 3 -->
        <div class="col-md-4 text-center mb-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <div class="mb-3">
                        <i class="bi bi-search" style="font-size: 2rem;"></i>
                    </div>
                    <h3 class="card-title">All persons</h3>
                    <p class="card-text">
                        ...living or dead,<br>
                        are purely coincidental.
                    </p>
                    <p class="card-text"><a href="/spans/kurt-vonnegut">Kurt Vonnegut</a></p>
                    <p class="card-text">1922 - 2007</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Card 4 -->
        <div class="col-md-4 text-center mb-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <div class="mb-3">
                        <i class="bi bi-journal-text" style="font-size: 2rem;"></i>
                    </div>
                    <h3 class="card-title">This is the time</h3>
                    <p class="card-text">
                        and this is the record<br>
                        of the time.
                    </p>
                    <p class="card-text"><a href="/spans/laurie-anderson">Laurie Anderson</a></p>
                    <p class="card-text">1947 - present</p>
                </div>
            </div>
        </div>

        <!-- Card 5 -->
        <div class="col-md-4 text-center mb-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <div class="mb-3">
                        <i class="bi bi-people" style="font-size: 2rem;"></i>
                    </div>
                    <h3 class="card-title">Collaborate</h3>
                    <p class="card-text">
                        Map time together.<br>
                        Share family or group histories.
                    </p>
                </div>
            </div>
        </div>

        <!-- Card 6 -->
        <div class="col-md-4 text-center mb-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <div class="mb-3">
                        <i class="bi bi-safe2" style="font-size: 2rem;"></i>
                    </div>
                    <h3 class="card-title">Preserve</h3>
                    <p class="card-text">
                        Build a living archive.<br>
                        Keep memories alive for future generations.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Call to Action -->
    <div class="row mt-5 pt-5 text-center">
        <div class="col-md-12">
            <p class="lead">
                <a class="btn btn-primary btn-lg ms-2" href="{{ route('login') }}" role="button">It's time</a>
            </p>
        </div>
    </div>
</div>

@else
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <h2>Home</h2>
                <p>This is the magic page.</p>
                <!-- Timeline content will go here -->
            </div>
        </div>
    </div>
@endguest
@endsection 