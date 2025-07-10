<!-- Google Analytics - Production Only -->
@if(app()->environment('production'))
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-R7VL06STVL"></script>
<script>
  // Define dataLayer and the gtag function.
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}

  // Set default consent to 'denied' as a placeholder
  // Determine actual values based on your own requirements
  gtag('consent', 'default', {
    'ad_storage': 'denied',
    'ad_user_data': 'denied',
    'ad_personalization': 'denied',
    'analytics_storage': 'denied'
  });

  gtag('js', new Date());
  gtag('config', 'G-R7VL06STVL');
</script>

<!-- Consent management functions -->
<script>
  function consentGrantedAnalytics() {
    gtag('consent', 'update', {
      'analytics_storage': 'granted'
    });
  }

  function consentGrantedAdStorage() {
    gtag('consent', 'update', {
      'ad_storage': 'granted',
      'ad_user_data': 'granted',
      'ad_personalization': 'granted'
    });
  }

  function consentDeniedAll() {
    gtag('consent', 'update', {
      'ad_storage': 'denied',
      'ad_user_data': 'denied',
      'ad_personalization': 'denied',
      'analytics_storage': 'denied'
    });
  }
</script>
@endif 