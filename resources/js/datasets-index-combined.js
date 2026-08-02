/**
 * /datasets index: one chart per dataset, shared X (year) domain so timelines line up.
 * Each chart keeps its own Y scale and series code. Uses per-dataset points JSON (D3 + jQuery).
 */
function runDatasetsIndexCharts($) {
    if (typeof window.d3 === 'undefined') {
        // eslint-disable-next-line no-console
        console.error('[datasets-index] window.d3 is undefined');
        return;
    }

    const $root = $('.js-datasets-index-root').first();
    if (!$root.length) {
        return;
    }
    if ($root.data('lifespanDatasetsIndexInit')) {
        return;
    }

    const d3 = window.d3;
    const $form = $root.find('.js-datasets-index-form');
    const $globalMessage = $root.find('.js-datasets-index-message');
    const $panels = $root.find('.js-dataset-panel');

    if (!$panels.length) {
        return;
    }

    $root.data('lifespanDatasetsIndexInit', true);

    function toFiniteNumber(value) {
        const n = Number(value);
        return Number.isFinite(n) ? n : NaN;
    }

    function sanitisePoints(points) {
        if (!Array.isArray(points)) {
            return [];
        }
        return points
            .map((p) => {
                const tMid = toFiniteNumber(p && p.t_mid);
                const val = toFiniteNumber(p && p.value);
                if (!Number.isFinite(tMid) || !Number.isFinite(val)) {
                    return null;
                }
                return { t_mid: tMid, value: val };
            })
            .filter(Boolean);
    }

    function showGlobalMessage(text, isError) {
        if (!$globalMessage.length) {
            return;
        }
        if (!text) {
            $globalMessage.addClass('d-none').text('');
            return;
        }
        $globalMessage.removeClass('d-none text-danger text-muted');
        $globalMessage.addClass(isError ? 'text-danger' : 'text-muted');
        $globalMessage.text(text);
    }

    function showPanelMessage($panel, text, isError) {
        const $message = $panel.find('.js-dataset-panel-message');
        if (!$message.length) {
            return;
        }
        if (!text) {
            $message.addClass('d-none').text('');
            return;
        }
        $message.removeClass('d-none text-danger text-muted');
        $message.addClass(isError ? 'text-danger' : 'text-muted');
        $message.text(text);
    }

    function sharedXDomain() {
        const fromRaw = $form.find('.js-index-from-year').val();
        const toRaw = $form.find('.js-index-to-year').val();
        let from = toFiniteNumber(fromRaw);
        let to = toFiniteNumber(toRaw);
        if (!Number.isFinite(from)) {
            from = 1900;
        }
        if (!Number.isFinite(to)) {
            to = new Date().getFullYear();
        }
        if (from > to) {
            const swap = from;
            from = to;
            to = swap;
        }
        // Cover calendar-year observations stored as [Y, Y+1) with t_mid ≈ Y+0.5
        return [from, to + 1];
    }

    function renderPanelChart($panel, payload, xDomain) {
        const $chart = $panel.find('.js-dataset-panel-chart');
        $chart.empty();

        if (!payload) {
            showPanelMessage($panel, 'Could not load this dataset.', true);
            return;
        }

        const points = sanitisePoints(payload.points);
        if (!points.length) {
            showPanelMessage($panel, 'No observations in this year range.', false);
            return;
        }

        showPanelMessage($panel, '', false);

        const margin = { top: 12, right: 24, bottom: 36, left: 56 };
        const width = Math.max(320, $chart.innerWidth() || 700);
        const height = 220;
        const innerW = width - margin.left - margin.right;
        const innerH = height - margin.top - margin.bottom;

        const svg = d3
            .select($chart.get(0))
            .append('svg')
            .attr('viewBox', `0 0 ${width} ${height}`)
            .attr('width', '100%')
            .attr('height', height);

        const g = svg.append('g').attr('transform', `translate(${margin.left},${margin.top})`);

        const x = d3.scaleLinear().domain(xDomain).range([0, innerW]);

        let yLo = d3.min(points, (d) => d.value);
        let yHi = d3.max(points, (d) => d.value);
        if (!Number.isFinite(yLo) || !Number.isFinite(yHi)) {
            showPanelMessage($panel, 'Could not derive a Y scale.', true);
            return;
        }
        if (yLo === yHi) {
            const pad = Math.abs(yLo) * 0.05 || 0.5;
            yLo -= pad;
            yHi += pad;
        }
        const y = d3.scaleLinear().domain([yLo, yHi]).nice().range([innerH, 0]);

        const lineGen = d3
            .line()
            .defined((d) => Number.isFinite(d.value))
            .x((d) => x(d.t_mid))
            .y((d) => y(d.value));

        g.append('g')
            .attr('transform', `translate(0,${innerH})`)
            .call(d3.axisBottom(x).ticks(8).tickFormat(d3.format('d')));

        g.append('g').call(d3.axisLeft(y).ticks(5));

        const meta = payload.dataset || {};
        const valueLabel = meta.value_label || 'Value';
        const unit = meta.unit ? ` (${meta.unit})` : '';
        g.append('text')
            .attr('transform', 'rotate(-90)')
            .attr('y', 0 - margin.left + 12)
            .attr('x', 0 - innerH / 2)
            .attr('dy', '0.8em')
            .style('text-anchor', 'middle')
            .attr('fill', 'currentColor')
            .attr('class', 'small')
            .text(valueLabel + unit);

        g.append('path')
            .datum(points)
            .attr('fill', 'none')
            .attr('stroke', 'var(--bs-primary, #0d6efd)')
            .attr('stroke-width', 2)
            .attr('d', lineGen);
    }

    function fetchPanel($panel, xDomain, fromVal, toVal) {
        const pointsUrl = String($panel.data('points-url') || '');
        const seriesKey = String($panel.find('.js-panel-series-key').val() || '').trim();

        if (!pointsUrl) {
            showPanelMessage($panel, 'Missing points URL.', true);
            return Promise.resolve();
        }
        if (!seriesKey) {
            showPanelMessage($panel, 'Enter a series code for this dataset.', true);
            $panel.find('.js-dataset-panel-chart').empty();
            return Promise.resolve();
        }

        const url = new URL(pointsUrl, window.location.origin);
        url.searchParams.set('series_key', seriesKey);
        if (fromVal !== '' && fromVal !== null) {
            url.searchParams.set('from', String(fromVal));
        }
        if (toVal !== '' && toVal !== null) {
            url.searchParams.set('to', String(toVal));
        }

        showPanelMessage($panel, 'Loading…', false);
        $panel.find('.js-dataset-panel-chart').empty();

        return fetch(url.toString(), { headers: { Accept: 'application/json' } })
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
                renderPanelChart($panel, data, xDomain);
            })
            .catch((err) => {
                showPanelMessage($panel, err.message || 'Could not load this dataset.', true);
                $panel.find('.js-dataset-panel-chart').empty();
            });
    }

    function loadCharts() {
        const xDomain = sharedXDomain();
        const fromVal = $form.find('.js-index-from-year').val();
        const toVal = $form.find('.js-index-to-year').val();

        showGlobalMessage('Loading…', false);

        const jobs = [];
        $panels.each(function () {
            jobs.push(fetchPanel($(this), xDomain, fromVal, toVal));
        });

        Promise.all(jobs).then(() => {
            showGlobalMessage('', false);
        });
    }

    $form.on('submit', function (e) {
        e.preventDefault();
        loadCharts();
    });

    $root.on('change', '.js-panel-series-key', function () {
        const $panel = $(this).closest('.js-dataset-panel');
        const xDomain = sharedXDomain();
        const fromVal = $form.find('.js-index-from-year').val();
        const toVal = $form.find('.js-index-to-year').val();
        fetchPanel($panel, xDomain, fromVal, toVal);
    });

    loadCharts();
}

function scheduleDatasetsIndexCharts() {
    if (typeof window.jQuery === 'function') {
        window.jQuery(runDatasetsIndexCharts);
        return;
    }
    window.addEventListener('DOMContentLoaded', function () {
        if (typeof window.jQuery === 'function') {
            window.jQuery(runDatasetsIndexCharts);
        }
    });
}

scheduleDatasetsIndexCharts();
