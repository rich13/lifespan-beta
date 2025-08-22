<?php

namespace App\Services\OAuth;

use League\OAuth1\Client\Server\Server;
use League\OAuth1\Client\Credentials\ClientCredentials;
use League\OAuth1\Client\Credentials\CredentialsInterface;
use League\OAuth1\Client\Credentials\User;

class FlickrServer extends Server
{
    public function __construct($clientId, $clientSecret, $callbackUri = null)
    {
        $clientCredentials = new ClientCredentials();
        $clientCredentials->setIdentifier($clientId);
        $clientCredentials->setSecret($clientSecret);
        
        if ($callbackUri) {
            $clientCredentials->setCallbackUri($callbackUri);
        }
        
        parent::__construct($clientCredentials);
    }
    
    /**
     * Get the URL for retrieving temporary credentials.
     */
    public function urlTemporaryCredentials()
    {
        return 'https://www.flickr.com/services/oauth/request_token';
    }
    
    /**
     * Get the URL for redirecting the resource owner to authorize the client.
     */
    public function urlAuthorization()
    {
        return 'https://www.flickr.com/services/oauth/authorize';
    }
    
    /**
     * Get the authorization URL with proper parameters for Flickr.
     */
    public function getAuthorizationUrl($temporaryIdentifier, array $options = [])
    {
        $url = $this->urlAuthorization();
        $params = [
            'oauth_token' => $temporaryIdentifier,
            'perms' => 'read' // Specify permission level: read, write, or delete
        ];
        
        return $url . '?' . http_build_query($params);
    }
    
    /**
     * Get the URL for retrieving token credentials.
     */
    public function urlTokenCredentials()
    {
        return 'https://www.flickr.com/services/oauth/access_token';
    }
    
    /**
     * Get the URL for retrieving user details.
     */
    public function urlUserDetails()
    {
        return $this->urlResourceOwnerDetails;
    }
    
    /**
     * Parse the user details from the response.
     */
    public function userDetails($data, CredentialsInterface $credentials)
    {
        // Parse Flickr user data
        $user = new User();
        $user->uid = $data['user']['nsid'] ?? null;
        $user->nickname = $data['user']['username']['_content'] ?? null;
        $user->name = $data['user']['realname']['_content'] ?? null;
        $user->location = $data['user']['location']['_content'] ?? null;
        
        return $user;
    }
    
    /**
     * Get the user's unique identifier.
     */
    public function userUid($data, CredentialsInterface $credentials)
    {
        return $data['user']['nsid'] ?? null;
    }
    
    /**
     * Get the user's email address.
     */
    public function userEmail($data, CredentialsInterface $credentials)
    {
        // Flickr doesn't provide email via OAuth
        return null;
    }
    
    /**
     * Get the user's screen name.
     */
    public function userScreenName($data, CredentialsInterface $credentials)
    {
        return $data['user']['username']['_content'] ?? null;
    }
    

}
