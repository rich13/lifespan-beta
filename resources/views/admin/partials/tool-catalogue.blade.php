<div id="admin-tool-catalogue">
<nav class="admin-tool-nav d-flex flex-wrap gap-2 mb-4" aria-label="Admin tool groups">
    <button type="button" class="btn btn-sm admin-tool-nav-btn admin-tool-nav-btn--all active" data-group="all" aria-pressed="true">All</button>
    <button type="button" class="btn btn-sm admin-tool-nav-btn admin-tool-nav-btn--manage" data-group="manage" aria-pressed="false">Manage</button>
    <button type="button" class="btn btn-sm admin-tool-nav-btn admin-tool-nav-btn--users-access" data-group="users-access" aria-pressed="false">Users &amp; access</button>
    <button type="button" class="btn btn-sm admin-tool-nav-btn admin-tool-nav-btn--import-export" data-group="import-export" aria-pressed="false">Import &amp; export</button>
    <button type="button" class="btn btn-sm admin-tool-nav-btn admin-tool-nav-btn--data-quality" data-group="data-quality" aria-pressed="false">Data quality</button>
    <button type="button" class="btn btn-sm admin-tool-nav-btn admin-tool-nav-btn--configure" data-group="configure" aria-pressed="false">Configure</button>
    <button type="button" class="btn btn-sm admin-tool-nav-btn admin-tool-nav-btn--system" data-group="system" aria-pressed="false">System</button>
</nav>

<div class="row admin-tool-groups">
    <x-admin.tool-section id="manage" icon="database" title="Manage">
        <x-admin.tool-card
            title="Manage Spans"
            :url="route('admin.spans.index')"
            icon="bar-chart-steps"
            button="Manage"
            :badge="number_format($stats['total_spans']) . ' spans'"
        >
            View, edit, and manage all spans in the system
        </x-admin.tool-card>

        <x-admin.tool-card
            title="Manage Connections"
            :url="route('admin.connections.index')"
            icon="arrow-left-right"
            button="Manage"
            :badge="number_format($stats['total_connections']) . ' connections'"
        >
            View and manage relationships between spans
        </x-admin.tool-card>

        <x-admin.tool-card
            title="Manage Places"
            :url="route('admin.places.index')"
            icon="geo-alt"
            button="Manage"
            :badge="number_format($stats['place_spans'] ?? 0) . ' places'"
        >
            Manage place spans, geospatial data, and auto-geocode unambiguous Nominatim matches
        </x-admin.tool-card>

        <x-admin.tool-card
            title="Manage Images"
            :url="route('admin.images.index')"
            icon="image"
            button="Manage"
            :badge="number_format($stats['photo_spans'] ?? 0) . ' photos'"
        >
            View and manage photo spans and their connections
        </x-admin.tool-card>

        <x-admin.tool-card
            title="Upload Photos"
            :url="route('settings.upload.photos.create')"
            icon="cloud-upload"
            button="Upload"
        >
            Upload and import photos into the system
        </x-admin.tool-card>

        <x-admin.tool-card
            title="Time series datasets"
            :url="route('admin.datasets.index')"
            icon="graph-up-arrow"
            button="Manage"
        >
            Import CSV metrics (OWID grapher format)
        </x-admin.tool-card>
    </x-admin.tool-section>

    <x-admin.tool-section id="users-access" icon="people" title="Users & access">
        <x-admin.tool-card
            title="Manage Users"
            :url="route('admin.users.index')"
            icon="people"
            button="Manage"
            :badge="number_format($stats['total_users']) . ' users'"
        >
            Manage user accounts and permissions
        </x-admin.tool-card>

        <x-admin.tool-card
            title="User Groups"
            :url="route('admin.groups.index')"
            icon="people"
            button="Manage Groups"
        >
            Manage user groups and memberships
        </x-admin.tool-card>

        <x-admin.tool-card
            title="Access Control"
            :url="route('admin.span-access.index')"
            icon="shield-lock"
            button="Manage"
            :badge="number_format($stats['public_spans']) . ' public'"
        >
            Manage span access levels and permissions
        </x-admin.tool-card>

        <x-admin.tool-card
            title="Person Subtypes"
            :url="route('admin.tools.manage-person-subtypes')"
            icon="person-badge"
            button="Manage Subtypes"
        >
            Categorise people as public figures or private individuals
        </x-admin.tool-card>
    </x-admin.tool-section>

    <x-admin.tool-section id="import-export" icon="arrow-left-right" title="Import & export">
        <x-admin.tool-card
            title="Data Import"
            :url="route('admin.data-import.index')"
            icon="upload"
            button="Import"
        >
            Import spans from YAML files or ZIP archives
        </x-admin.tool-card>

        <x-admin.tool-card
            title="Data Export"
            :url="route('admin.data-export.index')"
            icon="download"
            button="Export"
        >
            Export spans as YAML files for backup or sharing
        </x-admin.tool-card>

        <x-admin.tool-card
            title="YAML Import"
            :url="route('admin.import.index')"
            icon="file-earmark-text"
            button="Import"
        >
            Import data from YAML files
        </x-admin.tool-card>

        <x-admin.tool-card
            title="AI Generator"
            :url="route('admin.ai-yaml-generator.show')"
            icon="robot"
            button="Generate"
        >
            Generate YAML using AI
        </x-admin.tool-card>

        <x-admin.tool-card
            title="MusicBrainz Import"
            :url="route('admin.import.musicbrainz.index')"
            icon="music-note-list"
            button="Import"
        >
            Import official studio albums from MusicBrainz in the background
        </x-admin.tool-card>

        <x-admin.tool-card
            title="Film Import"
            :url="route('admin.import.film.index')"
            icon="film"
            button="Import"
        >
            Import film data from Wikidata
        </x-admin.tool-card>

        <x-admin.tool-card
            title="Book Import"
            :url="route('admin.import.book.index')"
            icon="book"
            button="Import"
        >
            Import book data from Wikidata
        </x-admin.tool-card>

        <x-admin.tool-card
            title="Desert Island Discs"
            :url="route('admin.import.simple-desert-island-discs.index')"
            icon="music-note-beamed"
            button="Import"
        >
            Import BBC Desert Island Discs episodes from Praful’s CSV, then enrich from Wikipedia and MusicBrainz
        </x-admin.tool-card>

        <x-admin.tool-card
            title="Parliament Explorer"
            :url="route('admin.import.parliament.index')"
            icon="building"
            button="Explore"
        >
            Explore UK Parliament member data
        </x-admin.tool-card>

        <x-admin.tool-card
            title="Prime Ministers"
            :url="route('admin.import.prime-ministers.index')"
            icon="person-badge"
            button="Import"
        >
            Import UK Prime Ministers
        </x-admin.tool-card>

        <x-admin.tool-card
            title="Science Museum Group"
            :url="route('admin.import.science-museum-group.index')"
            icon="museum"
            button="Import"
        >
            Import museum objects and creators
        </x-admin.tool-card>

        <x-admin.tool-card
            title="Wikimedia Commons"
            :url="route('admin.import.wikimedia-commons.index')"
            icon="images"
            button="Import"
        >
            Import images from Wikimedia Commons
        </x-admin.tool-card>

        <x-admin.tool-card
            title="Wikipedia Import"
            :url="route('admin.import.wikipedia.index')"
            icon="wikipedia"
            button="Import"
        >
            Bulk import descriptions and sources from Wikipedia
        </x-admin.tool-card>

        <x-admin.tool-card
            title="Plaque Import"
            :url="route('admin.import.blue-plaques.index')"
            icon="geo-alt-fill"
            button="Import"
        >
            Import commemorative plaques and memorials
        </x-admin.tool-card>

        <x-admin.tool-card
            title="Braggoscope Episodes"
            :url="route('admin.import.braggoscope.index')"
            icon="broadcast"
            button="Import"
        >
            Import In Our Time episodes from Braggoscope
        </x-admin.tool-card>

        <x-admin.tool-card
            title="OSM London data"
            :url="route('admin.osmdata.index')"
            icon="map"
            button="Open"
        >
            Import London boroughs, stations, and airports from OpenStreetMap
        </x-admin.tool-card>
    </x-admin.tool-section>

    <x-admin.tool-section id="data-quality" icon="wrench" title="Data quality">
        <x-admin.tool-card
            title="Span improvement"
            :url="route('admin.improvement.index')"
            icon="arrow-repeat"
            button="Open"
        >
            Improve new and existing spans from Wikipedia, MusicBrainz, and geocoding, with a hard stop and an AI token budget
        </x-admin.tool-card>

        <x-admin.tool-card
            title="Span Merge Tool"
            :url="route('admin.merge.index')"
            icon="diagram-3"
            button="Open Merge Tool"
        >
            Find and merge similar spans, including exact name duplicates and places that share the same OpenStreetMap identity
        </x-admin.tool-card>

        <x-admin.tool-card
            title="Data Fixer Tool"
            :url="route('admin.tools.fixer')"
            icon="wrench"
            button="Fix Data Issues"
        >
            Find and fix data quality issues such as invalid date ranges
        </x-admin.tool-card>

        <x-admin.tool-card
            title="Make Things Public"
            :url="route('admin.tools.make-things-public')"
            icon="globe"
            button="Open Tool"
        >
            Make all thing spans (books, albums, tracks) public by default
        </x-admin.tool-card>

        <x-admin.tool-card
            title="Fix Public Figure Connections"
            :url="route('admin.tools.fix-public-figure-connections')"
            icon="link-45deg"
            button="Fix Connections"
        >
            Ensure connections for public figures are public so timelines render correctly
        </x-admin.tool-card>

        <x-admin.tool-card
            title="Fix Private Individual Connections"
            :url="route('admin.tools.fix-private-individual-connections')"
            icon="shield-lock"
            button="Fix Connections"
        >
            Make private individuals and their connection spans private. Runs in the background with live progress
        </x-admin.tool-card>

        <x-admin.tool-card
            title="Plaque Residence Connections"
            :url="route('admin.tools.plaque-residence-connections')"
            icon="house-door"
            button="Scan Plaques"
        >
            Find plaques with a person and place, then create missing lived-in connections from the inscription
        </x-admin.tool-card>

        <x-admin.tool-card
            title="Family Connection Date Sync"
            :url="route('admin.tools.family-connection-date-sync')"
            icon="calendar-check"
            button="Sync Dates"
        >
            Sync start and end dates for family connections from birth and death dates
        </x-admin.tool-card>

        <x-admin.tool-card
            title="Fix Connection Slugs"
            :url="route('admin.tools.fix-connection-slugs')"
            icon="link-45deg"
            button="Fix Slugs"
        >
            Rename connections that still use generic "connection-between-spans" slugs
        </x-admin.tool-card>
    </x-admin.tool-section>

    <x-admin.tool-section id="configure" icon="gear" title="Configure">
        <x-admin.tool-card
            title="Span Types"
            :url="route('admin.span-types.index')"
            icon="ui-checks"
            button="Configure"
            :badge="count($spanTypeStats) . ' types'"
        >
            Configure span types and their properties
        </x-admin.tool-card>

        <x-admin.tool-card
            title="Connection Types"
            :url="route('admin.connection-types.index')"
            icon="sliders2"
            button="Configure"
            :badge="count($connectionTypeStats) . ' types'"
        >
            Configure connection types and constraints
        </x-admin.tool-card>
    </x-admin.tool-section>

    <x-admin.tool-section id="system" icon="cpu" title="System">
        <x-admin.tool-card
            title="Queue Workers"
            :url="route('admin.workers.index')"
            icon="cpu"
            button="Workers"
        >
            Monitor and control background workers
        </x-admin.tool-card>

        <x-admin.tool-card
            title="System History"
            :url="route('admin.system-history.index')"
            icon="clock-history"
            button="View History"
        >
            View versioning history across the system
        </x-admin.tool-card>

        <x-admin.tool-card
            title="Span Metrics"
            :url="route('admin.metrics.index')"
            icon="graph-up"
            button="View Metrics"
        >
            View span completeness scores and analytics
        </x-admin.tool-card>

        <x-admin.tool-card
            title="Network Explorer"
            :url="route('admin.visualizer.index')"
            icon="diagram-3"
            button="Explore"
        >
            Visualise network relationships
        </x-admin.tool-card>

        <x-admin.tool-card
            title="Slack Notifications"
            :url="route('admin.slack-notifications.index')"
            icon="slack"
            button="Manage"
        >
            Manage Slack integration and notifications
        </x-admin.tool-card>

        <x-admin.tool-card
            title="Wikipedia Cache Prewarm"
            :url="route('admin.tools.prewarm-wikipedia-cache')"
            icon="lightning-charge"
            button="Start Prewarm"
        >
            Pre-populate the Wikipedia "On This Day" cache for every day of the year
        </x-admin.tool-card>
    </x-admin.tool-section>
</div>
</div>

<script>
$(document).ready(function() {
    var $sections = $('#admin-tool-catalogue .admin-tool-section');
    var $buttons = $('#admin-tool-catalogue .admin-tool-nav-btn');

    function showGroup(group) {
        var selected = group || 'all';
        $buttons.removeClass('active').attr('aria-pressed', 'false');
        $buttons.filter('[data-group="' + selected + '"]').addClass('active').attr('aria-pressed', 'true');

        if (selected === 'all') {
            $sections.removeClass('d-none');
        } else {
            $sections.each(function() {
                $(this).toggleClass('d-none', $(this).data('group') !== selected);
            });
        }

        if (window.history && window.history.replaceState) {
            var url = window.location.pathname + window.location.search;
            if (selected !== 'all') {
                url += '#' + selected;
            }
            window.history.replaceState(null, '', url);
        }

        var offset = $('#admin-tool-catalogue').offset();
        if (offset && $(window).scrollTop() > offset.top) {
            $('html, body').animate({ scrollTop: offset.top - 16 }, 150);
        }
    }

    $buttons.on('click', function() {
        showGroup($(this).data('group'));
    });

    var hash = (window.location.hash || '').replace('#', '');
    if (hash && $sections.filter('[data-group="' + hash + '"]').length) {
        showGroup(hash);
    }
});
</script>
