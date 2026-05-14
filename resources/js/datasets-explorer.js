/**
 * Dataset explorer: loads JSON points and draws a line chart with D3 (global).
 * Expects jQuery and d3 on window (see datasets/show blade).
 */
window.jQuery(function ($) {
    const $root = $('#dataset-explorer-root');
    if (!$root.length || typeof window.d3 === 'undefined') {
        return;
    }

    const pointsUrl = String($root.data('points-url') || '');
    const $chart = $('#dataset-chart');
    const $message = $('#dataset-chart-message');
    const $form = $('#dataset-range-form');

    if (!pointsUrl || !$chart.length) {
        return;
    }

    function showMessage(text, isError) {
        if (!$message.length) {
            return;
        }
        $message.removeClass('d-none text-danger text-muted');
        $message.addClass(isError ? 'text-danger' : 'text-muted');
        $message.text(text);
    }

    function renderChart(points, valueLabel, unit) {
        $chart.empty();
        if (!points.length) {
            showMessage('No observations in this range.', false);
            return;
        }
        showMessage('', false);
        $message.addClass('d-none');

        const d3 = window.d3;
        const margin = { top: 16, right: 24, bottom: 40, left: 56 };
        const width = Math.max(320, $chart.innerWidth() || 640);
        const height = 320;
        const innerW = width - margin.left - margin.right;
        const innerH = height - margin.top - margin.bottom;

        const svg = d3
            .select($chart.get(0))
            .append('svg')
            .attr('viewBox', `0 0 ${width} ${height}`)
            .attr('width', '100%')
            .attr('height', height);

        const g = svg.append('g').attr('transform', `translate(${margin.left},${margin.top})`);

        const x = d3
            .scaleLinear()
            .domain(d3.extent(points, (d) => d.t_mid))
            .range([0, innerW]);

        const y = d3
            .scaleLinear()
            .domain(d3.extent(points, (d) => d.value))
            .nice()
            .range([innerH, 0]);

        const line = d3
            .line()
            .x((d) => x(d.t_mid))
            .y((d) => y(d.value));

        g.append('g')
            .attr('transform', `translate(0,${innerH})`)
            .call(d3.axisBottom(x).ticks(8).tickFormat(d3.format('d')));

        const yLabel = unit ? `${valueLabel} (${unit})` : valueLabel;
        g.append('g').call(d3.axisLeft(y).ticks(6));
        g.append('text')
            .attr('transform', 'rotate(-90)')
            .attr('y', 0 - margin.left + 12)
            .attr('x', 0 - innerH / 2)
            .attr('dy', '0.8em')
            .style('text-anchor', 'middle')
            .attr('fill', 'currentColor')
            .attr('class', 'small')
            .text(yLabel);

        g.append('path')
            .datum(points)
            .attr('fill', 'none')
            .attr('stroke', 'var(--bs-primary, #0d6efd)')
            .attr('stroke-width', 2)
            .attr('d', line);
    }

    function loadChart() {
        const seriesKey = String($('#series-key-input').val() || '').trim();
        const fromVal = $('#from-year-input').val();
        const toVal = $('#to-year-input').val();

        if (!seriesKey) {
            showMessage('Enter a series code.', true);
            return;
        }

        const url = new URL(pointsUrl, window.location.origin);
        url.searchParams.set('series_key', seriesKey);
        if (fromVal !== '' && fromVal !== null) {
            url.searchParams.set('from', String(fromVal));
        }
        if (toVal !== '' && toVal !== null) {
            url.searchParams.set('to', String(toVal));
        }

        showMessage('Loading…', false);
        $message.removeClass('d-none');

        fetch(url.toString(), { headers: { Accept: 'application/json' } })
            .then((response) => {
                if (!response.ok) {
                    return response.json().then(
                        (body) => {
                            throw new Error(body.message || `Request failed (${response.status})`);
                        },
                        () => {
                            throw new Error(`Request failed (${response.status})`);
                        }
                    );
                }
                return response.json();
            })
            .then((data) => {
                const pts = Array.isArray(data.points) ? data.points : [];
                const meta = data.dataset || {};
                renderChart(pts, meta.value_label || 'Value', meta.unit || '');
            })
            .catch((err) => {
                showMessage(err.message || 'Could not load data.', true);
                $chart.empty();
            });
    }

    $form.on('submit', function (e) {
        e.preventDefault();
        loadChart();
    });

    loadChart();
});
