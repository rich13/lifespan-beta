<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
if (typeof window.initPlaquesMap === 'undefined') {
    window.initPlaquesMap = function(options) {
        var map = L.map(options.elementId, {
            attributionControl: !options.detailPanel
        }).setView(options.centre, options.zoom);

        if (options.detailPanel) {
            L.control.attribution({ position: 'bottomleft' }).addTo(map);
        }

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        var markerLayer = L.layerGroup().addTo(map);
        var plaqueIcon = L.divIcon({
            className: 'plaque-map-marker',
            html: '<span class="plaque-map-marker-disc"></span>',
            iconSize: [18, 18],
            iconAnchor: [9, 9]
        });
        var featuredIcon = L.divIcon({
            className: 'plaque-map-marker plaque-map-marker--featured',
            html: '<span class="plaque-map-marker-disc"></span>',
            iconSize: [28, 28],
            iconAnchor: [14, 14]
        });
        var loadId = 0;
        var loadTimeout;
        var featuredLatLng = options.featuredCoords
            ? L.latLng(options.featuredCoords[0], options.featuredCoords[1])
            : null;
        var focusMarkers = Array.isArray(options.focusMarkers) ? options.focusMarkers : [];
        var focusLatLngs = [];
        var focusUrls = {};
        var showOtherPlaques = !options.otherPlaquesToggle;

        function escapeText(value) {
            return $('<div>').text(value || '').html();
        }

        function markerPathname(url) {
            try {
                return new URL(url, window.location.origin).pathname;
            } catch (e) {
                return url;
            }
        }

        function isCurrentPlaque(markerData) {
            if (!markerData.url) {
                return false;
            }
            var path = markerPathname(markerData.url);
            if (options.excludeCurrent && path === window.location.pathname) {
                return true;
            }
            return !!focusUrls[path];
        }

        function getPanelPadding() {
            var $panel = $('.plaque-detail-panel');
            if (!$panel.length) {
                return { topLeft: [20, 20], bottomRight: [20, 20] };
            }
            var size = map.getSize();
            var panelWidth = $panel.outerWidth() || 0;
            var panelHeight = $panel.outerHeight() || 0;
            var isBottomSheet = panelWidth >= size.x * 0.8;
            if (isBottomSheet) {
                return { topLeft: [20, 20], bottomRight: [20, panelHeight + 20] };
            }
            return { topLeft: [20, 20], bottomRight: [panelWidth + 20, 20] };
        }

        function keepFocusInView(animate) {
            if (!options.detailPanel) {
                return;
            }
            var padding = getPanelPadding();
            var fitOptions = {
                animate: !!animate,
                paddingTopLeft: padding.topLeft,
                paddingBottomRight: padding.bottomRight,
                maxZoom: options.zoom || 15
            };
            if (focusLatLngs.length > 1) {
                map.fitBounds(L.latLngBounds(focusLatLngs), fitOptions);
                return;
            }
            var single = focusLatLngs[0] || featuredLatLng;
            if (!single) {
                return;
            }
            map.setView(single, options.zoom || 15, { animate: !!animate });
            map.panInside(single, {
                animate: !!animate,
                paddingTopLeft: padding.topLeft,
                paddingBottomRight: padding.bottomRight
            });
        }

        function addFocusMarker(latLng, title, url) {
            var marker = L.marker(latLng, {
                icon: featuredIcon,
                zIndexOffset: 1000,
                keyboard: false,
                title: title || ''
            });
            if (title) {
                marker.bindTooltip(escapeText(title), {
                    direction: 'top',
                    opacity: 0.95
                });
            }
            if (url && markerPathname(url) !== window.location.pathname) {
                marker.on('click', function() {
                    window.location = url;
                });
            }
            marker.addTo(map);
            focusLatLngs.push(latLng);
        }

        function addFocusMarkers() {
            focusMarkers.forEach(function(markerData) {
                if (markerData.latitude == null || markerData.longitude == null) {
                    return;
                }
                if (markerData.url) {
                    focusUrls[markerPathname(markerData.url)] = true;
                }
                addFocusMarker(
                    L.latLng(markerData.latitude, markerData.longitude),
                    markerData.title || '',
                    markerData.url
                );
            });
            if (!focusLatLngs.length && featuredLatLng) {
                addFocusMarker(featuredLatLng, options.featuredTitle || '', null);
            }
        }

        function loadMarkers() {
            if (!showOtherPlaques) {
                markerLayer.clearLayers();
                return;
            }
            var id = ++loadId;
            var bounds = map.getBounds();
            $.get(options.markersUrl, {
                north: bounds.getNorth(),
                south: bounds.getSouth(),
                east: bounds.getEast(),
                west: bounds.getWest(),
                zoom: map.getZoom()
            }).done(function(data) {
                if (id !== loadId) {
                    return;
                }
                markerLayer.clearLayers();
                if (!data.markers) {
                    return;
                }
                data.markers.forEach(function(markerData) {
                    if (isCurrentPlaque(markerData)) {
                        return;
                    }
                    var title = markerData.person_name + ' ' + markerData.predicate + ' — ' + markerData.place_name;
                    var marker = L.marker([markerData.latitude, markerData.longitude], {
                        icon: plaqueIcon,
                        title: title
                    });
                    marker.bindTooltip(escapeText(title), { direction: 'top', opacity: 0.95 });
                    marker.on('click', function() {
                        window.location = markerData.url;
                    });
                    markerLayer.addLayer(marker);
                });
            });
        }

        function scheduleLoadMarkers() {
            if (!showOtherPlaques) {
                return;
            }
            clearTimeout(loadTimeout);
            loadTimeout = setTimeout(loadMarkers, 300);
        }

        function addOtherPlaquesToggle() {
            if (!options.otherPlaquesToggle) {
                return;
            }
            var OtherPlaquesControl = L.Control.extend({
                onAdd: function() {
                    var container = L.DomUtil.create('label', 'leaflet-control plaque-other-toggle');
                    var $container = $(container);
                    var $input = $('<input>', {
                        type: 'checkbox',
                        class: 'plaque-other-toggle-input',
                        checked: showOtherPlaques
                    });
                    $container.append($input);
                    $container.append(document.createTextNode(' Other plaques'));
                    $container.on('change', '.plaque-other-toggle-input', function() {
                        showOtherPlaques = $(this).is(':checked');
                        if (showOtherPlaques) {
                            loadMarkers();
                        } else {
                            loadId += 1;
                            markerLayer.clearLayers();
                        }
                    });
                    L.DomEvent.disableClickPropagation(container);
                    L.DomEvent.disableScrollPropagation(container);
                    return container;
                }
            });
            new OtherPlaquesControl({ position: 'topleft' }).addTo(map);
        }

        function resizeMap() {
            map.invalidateSize();
            keepFocusInView(false);
            scheduleLoadMarkers();
        }

        addFocusMarkers();
        addOtherPlaquesToggle();
        map.whenReady(function() {
            keepFocusInView(false);
        });
        map.on('moveend', scheduleLoadMarkers);
        $(window).on('resize', resizeMap);
        $('#sidebar-toggle').on('click', function() {
            setTimeout(resizeMap, 320);
        });
        resizeMap();

        return map;
    };
}
</script>
