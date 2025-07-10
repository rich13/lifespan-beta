<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Lifespan') }} - @yield('error_title', 'Error')</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <!-- Favicon -->
    <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
    
    <style>
        body {
            background: #eee;
            min-height: 100vh;
            display: flex;
            align-items: center;
            /* Add relative positioning for canvas layering */
            position: relative;
            overflow: hidden;
        }
        #game-of-life-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            z-index: 0;
            opacity: 0.18;
            pointer-events: none;
        }
        .container {
            position: relative;
            z-index: 1;
        }
        
        .error-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: none;
            border-radius: 13px;
            box-shadow: 0 13px 35px rgba(0, 0, 0, 0.1);
        }
        
        .error-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        
        .error-code {
            font-size: 6rem;
            font-weight: 700;
            color: #6c757d;
            line-height: 1;
            margin-bottom: 0.5rem;
        }
        
        .error-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        
        .error-message {
            color: #6c757d;
            font-size: 1.1rem;
            line-height: 1.6;
        }
        
        .btn-home {
            background: #6c757d;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 25px;
            font-weight: 500;
            color: #000;
        }
        
        .btn-home:hover {
            color: #000;
        }
    
    </style>
</head>
<body>
    <canvas id="game-of-life-bg"></canvas>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card error-card text-center p-5">
                    <div class="error-icon text-@yield('error_color', 'danger')">
                        <i class="@yield('error_icon', 'bi-exclamation-triangle-fill')"></i>
                    </div>
                    
                    <div class="error-code">@yield('error_code', '500')</div>
                    
                    <h1 class="error-title">@yield('error_title', 'Server Error')</h1>
                    
                    <p class="error-message mb-4">
                        @yield('error_message', 'We encountered an error while processing your request.')
                    </p>
                    
                    @yield('error_details')
                    
                    <div class="d-flex flex-column flex-sm-row gap-3 justify-content-center">
                        <a href="{{ request()->getScheme() }}://{{ request()->getHttpHost() }}/" class="btn btn-primary">Home</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- D3.js for Game of Life -->
    <script src="https://d3js.org/d3.v7.min.js"></script>
    <script>
    // Game of Life in D3.js (rendered to canvas)
    (function() {
        const cellSize = 13;
        const canvas = document.getElementById('game-of-life-bg');
        const ctx = canvas.getContext('2d');
        let width = window.innerWidth;
        let height = window.innerHeight;
        let cols = Math.floor(width / cellSize);
        let rows = Math.floor(height / cellSize);
        
        function resizeCanvas() {
            width = window.innerWidth;
            height = window.innerHeight;
            canvas.width = width;
            canvas.height = height;
            cols = Math.floor(width / cellSize);
            rows = Math.floor(height / cellSize);
        }
        resizeCanvas();
        window.addEventListener('resize', resizeCanvas);

        // Create random initial state
        function randomGrid() {
            const grid = [];
            for (let y = 0; y < rows; y++) {
                const row = [];
                for (let x = 0; x < cols; x++) {
                    row.push(Math.random() > 0.75 ? 1 : 0);
                }
                grid.push(row);
            }
            return grid;
        }

        let grid = randomGrid();

        function drawGrid() {
            ctx.clearRect(0, 0, width, height);
            ctx.fillStyle = '#222';
            for (let y = 0; y < rows; y++) {
                for (let x = 0; x < cols; x++) {
                    if (grid[y][x]) {
                        ctx.fillRect(x * cellSize, y * cellSize, cellSize, cellSize);
                    }
                }
            }
        }

        function nextGen() {
            const newGrid = [];
            for (let y = 0; y < rows; y++) {
                const row = [];
                for (let x = 0; x < cols; x++) {
                    let live = 0;
                    for (let dy = -1; dy <= 1; dy++) {
                        for (let dx = -1; dx <= 1; dx++) {
                            if (dx === 0 && dy === 0) continue;
                            const ny = y + dy;
                            const nx = x + dx;
                            if (ny >= 0 && ny < rows && nx >= 0 && nx < cols) {
                                live += grid[ny][nx];
                            }
                        }
                    }
                    if (grid[y][x]) {
                        row.push(live === 2 || live === 3 ? 1 : 0);
                    } else {
                        row.push(live === 3 ? 1 : 0);
                    }
                }
                newGrid.push(row);
            }
            grid = newGrid;
        }

        function animate() {
            drawGrid();
            nextGen();
            requestAnimationFrame(animate);
        }
        animate();
    })();
    </script>
    <x-google-analytics />
    <x-consent-banner />
    @stack('scripts')
</body>
</html> 