@if(app()->environment('production'))
<div id="consent-banner" class="position-fixed bottom-0 start-0 w-100 bg-dark text-white p-3" style="z-index: 9999; display: none;">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <p class="mb-2 mb-md-0">
                    Lifespan uses Google Analytics. Please click "Accept" so basics can be collected <i class="bi bi-emoji-smile"></i></p>
            </div>
            <div class="col-md-4 text-md-end">
                <button type="button" class="btn btn-outline-light btn-sm me-2" onclick="consentDeniedAll(); hideConsentBanner();">
                    No thanks
                </button>
                <button type="button" class="btn btn-success btn-sm" onclick="consentGrantedAnalytics(); hideConsentBanner();">
                    OK then
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Check if user has already made a consent choice
function checkConsentStatus() {
    const consentStatus = localStorage.getItem('ga-consent-status');
    if (!consentStatus) {
        // Show banner if no consent choice has been made
        showConsentBanner();
    }
}

function showConsentBanner() {
    document.getElementById('consent-banner').style.display = 'block';
}

function hideConsentBanner() {
    document.getElementById('consent-banner').style.display = 'none';
    localStorage.setItem('ga-consent-status', 'decided');
}

// Check consent status when page loads
document.addEventListener('DOMContentLoaded', function() {
    checkConsentStatus();
});
</script>
@endif 