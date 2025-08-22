<?php

use App\Models\User;
use App\Services\OAuth\FlickrServer;
use League\OAuth1\Client\Credentials\ClientCredentials;
use Tests\TestCase;

class FlickrOAuthTest extends TestCase
{
    public function test_flickr_oauth_server_can_be_instantiated()
    {
        $server = new FlickrServer(
            config('services.flickr.client_id'),
            config('services.flickr.client_secret'),
            config('services.flickr.callback_url')
        );
        
        $this->assertInstanceOf(FlickrServer::class, $server);
        $this->assertEquals('https://www.flickr.com/services/oauth/request_token', $server->urlTemporaryCredentials());
        $this->assertEquals('https://www.flickr.com/services/oauth/authorize', $server->urlAuthorization());
        $this->assertEquals('https://www.flickr.com/services/oauth/access_token', $server->urlTokenCredentials());
    }

    public function test_flickr_oauth_routes_are_accessible()
    {
        $user = User::factory()->create();
        
        $this->actingAs($user);
        
        // Test authorize route
        $response = $this->get(route('settings.import.flickr.authorize'));
        $this->assertEquals(302, $response->status()); // Should redirect to Flickr
        
        // Test callback route
        $response = $this->get(route('settings.import.flickr.callback'));
        $this->assertEquals(302, $response->status()); // Should redirect back to index
        
        // Test disconnect route
        $response = $this->post(route('settings.import.flickr.disconnect'));
        $this->assertEquals(302, $response->status()); // Should redirect back to index
    }

    public function test_flickr_oauth_credentials_are_configured()
    {
        $this->assertEquals('3c41cc8de5c3d33ea2433a17ed61bebf', config('services.flickr.client_id'));
        $this->assertEquals('a9773d44336e6279', config('services.flickr.client_secret'));
        $this->assertEquals('http://localhost:8000/settings/import/flickr/callback', config('services.flickr.callback_url'));
    }
}
