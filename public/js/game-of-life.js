$(document).ready(function() {
    const canvas = document.getElementById('game-of-life-bg');
    if (!canvas) {
        return;
    }

    const ctx = canvas.getContext('2d');
    const cellSize = 3;
    let width = window.innerWidth;
    let height = window.innerHeight;
    let cols = Math.floor(width / cellSize);
    let rows = Math.floor(height / cellSize);
    let grid = [];

    function resizeCanvas() {
        width = window.innerWidth;
        height = window.innerHeight;
        canvas.width = width;
        canvas.height = height;
        cols = Math.floor(width / cellSize);
        rows = Math.floor(height / cellSize);
    }

    function randomGrid() {
        const nextGrid = [];
        for (let y = 0; y < rows; y++) {
            const row = [];
            for (let x = 0; x < cols; x++) {
                row.push(Math.random() > 0.75 ? 1 : 0);
            }
            nextGrid.push(row);
        }
        return nextGrid;
    }

    function drawGrid() {
        ctx.clearRect(0, 0, width, height);
        ctx.fillStyle = '#222';
        for (let y = 0; y < rows; y++) {
            for (let x = 0; x < cols; x++) {
                if (grid[y] && grid[y][x]) {
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
                        if (dx === 0 && dy === 0) {
                            continue;
                        }
                        const ny = y + dy;
                        const nx = x + dx;
                        if (ny >= 0 && ny < rows && nx >= 0 && nx < cols && grid[ny]) {
                            live += grid[ny][nx];
                        }
                    }
                }
                const current = grid[y] && grid[y][x] ? 1 : 0;
                if (current) {
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

    resizeCanvas();
    grid = randomGrid();
    $(window).on('resize', function() {
        resizeCanvas();
        grid = randomGrid();
    });
    animate();
});
