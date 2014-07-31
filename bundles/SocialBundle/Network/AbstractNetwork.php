<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic, NP. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.com
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\SocialBundle\Network;

use Mautic\CoreBundle\Factory\MauticFactory;
use Mautic\SocialBundle\Entity\SocialNetwork;

abstract class AbstractNetwork
{
    protected $factory;
    protected $entity;
    protected $settings;

    /**
     * @param MauticFactory $factory
     */
    public function __construct(MauticFactory $factory)
    {
        $this->factory = $factory;
    }

    /**
     * Determines what priority the network should have against the other networks
     *
     * @return mixed
     */
    public function getPriority()
    {
        return 9999;
    }


    /**
     * Returns the name of the social network that must match the name of the file
     *
     * @return string
     */
    abstract public function getName();

    /**
     * Returns the field the network needs in order to find the user
     *
     * @return mixed
     */
    abstract public function getIdentifierField();

    /**
     * Set the social network entity
     *
     * @param SocialNetwork $settings
     */
    public function setSettings(SocialNetwork $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Get the social network entity
     *
     * @return mixed
     */
    public function getSettings()
    {
        return $this->settings;
    }

    /**
     * Generate the oauth login URL
     *
     * @return string
     */
    public function getOAuthLoginUrl()
    {
        $callback = $this->factory->getRouter()->generate('mautic_social_callback',
            array('network' => $this->getName()),
            true //absolute
        );

        $state = uniqid();
        $url = $this->getAuthenticationUrl()
            . '?client_id={clientId}' //placeholder to be replaced by whatever is the field
            . '&response_type=code'
            . '&redirect_uri=' . $callback
            . '&state=' . $state; //set a state to protect against CSRF attacks
        $this->factory->getSession()->set($this->getName() . '_csrf_token', $state);

        return $url;
    }

    /**
     * Retrieves and stores tokens returned from oAuthLogin
     *
     * @return array
     */
    public function oAuthCallback($clientId = '', $clientSecret = '')
    {
        $request  = $this->factory->getRequest();
        $url      = $this->getAccessTokenUrl();
        $keys     = $this->settings->getApiKeys();
        $callback = $this->factory->getRouter()->generate('mautic_social_callback',
            array('network' => $this->getName()),
            true //absolute
        );

        if (!empty($clientId)) {
            //callback from JS
            $keys['clientId']     = $clientId;
            $keys['clientSecret'] = $clientSecret;
        }

        if (!$url || !isset($keys['clientId']) || !isset($keys['clientSecret'])) {
            return array(false, $this->factory->getTranslator()->trans('mautic.social.missingkeys'));
        }

        $url .= '?client_id='.$keys['clientId'];
        $url .= '&client_secret='.$keys['clientSecret'];
        $url .= '&grant_type=authorization_code';
        $url .= '&redirect_uri=' . $callback;
        $url .= '&code='.$request->get('code');

        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_URL, $url);
            $data = curl_exec($ch);
            curl_close($ch);
        } elseif (ini_get('allow_url_fopen')) {
            $data = @file_get_contents($url);
        }

        //parse the response
        $values = $this->parseCallbackResponse($data);

        //check to see if an entity exists
        $entity = $this->getSettings();
        if ($entity == null) {
            $entity = new SocialNetwork();
            $entity->setName($this->getName());
        }

        if (is_array($values) && isset($values['access_token'])) {
            $keys['access_token'] = $values['access_token'];

            if (isset($values['refresh_token'])) {
                $keys['refresh_token'] = $values['refresh_token'];
            }
            $error = false;
        } else {
            $error = $this->getErrorsFromResponse($values);
        }

        $entity->setApiKeys($keys);

        //save the data
        $em = $this->factory->getEntityManager();
        $em->persist($entity);
        $em->flush();

        return array($entity, $error);
    }

    /**
     * Extract the tokens returned by the oauth2 callback
     *
     * @param $data
     * @return mixed
     */
    protected function parseCallbackResponse($data)
    {
        return json_decode($data, true);
    }

    /**
     * Make a basic call using cURL to get the data
     *
     * @param $url
     * @return mixed
     */
    public function makeCall($url) {
        $referer  = $this->getRefererUrl();

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_REFERER, $referer);
        $data = @curl_exec($ch);
        curl_close($ch);

        $data = json_decode($data);
        return $data;
    }

    /**
     * Get a list of available fields from the social networking API
     *
     * @return array
     */
    public function getAvailableFields()
    {
        return array();
    }

    /**
     * Get a list of keys required to make an API call.  Examples are key, clientId, clientSecret
     *
     * @return array
     */
    public function getRequiredKeyFields()
    {
        return array();
    }

    /**
     * Get a list of supported features for this social network
     *
     * @return array
     */
    public function getSupportedFeatures()
    {
        return array();
    }

    /**
     * Get the type of authentication required for this API.  Values can be none, key, or oauth2
     *
     * @return string
     */
    public function getAuthenticationType()
    {
        return 'none';
    }

    /**
     * Get the URL required to obtain an oauth2 access token
     *
     * @return bool
     */
    public function getAccessTokenUrl()
    {
        return false;
    }

    /**
     * Get the authentication/login URL for oauth2 access
     *
     * @return string
     */
    protected function getAuthenticationUrl()
    {
        return '';
    }

    /**
     * Get a string formatted error from an API response
     *
     * @param $response
     * @return string
     */
    public function getErrorsFromResponse($response)
    {
        if (is_array($response)) {
            return implode(' ', $response);
        } else {
            $response;
        }
    }

    /**
     * Cleans the identifier for api calls
     *
     * @param $identifier
     * @return string
     */
    protected function cleanIdentifier($identifier)
    {
        if (is_array($identifier)) {
            foreach ($identifier as &$i) {
                $i = urlencode($i);
            }
        } else {
            $identifier = urlencode($identifier);
        }

        return $identifier;
    }

    /**
     * Gets the ID of the user for the network
     *
     * @param $identifier
     * @param $socialCache
     * @return mixed|null
     */
    public function getUserId($identifier, &$socialCache)
    {
        if (!empty($socialCache['id'])) {
            return $socialCache['id'];
        } else {
            return false;
        }
    }

    /**
     * Get an array of public activity
     *
     * @param $identifier
     * @param $socialCache
     * @return array
     */
    public function getPublicActivity($identifier, &$socialCache)
    {
        return array();
    }

    /**
     * Get an array of public data
     *
     * @param $identifier
     * @param $socialCache
     * @return array
     */
    public function getUserData($identifier, &$socialCache)
    {
        return array();
    }

    /**
     * Generates current URL to set as referer for curl calls
     *
     * @return string
     */
    protected function getRefererUrl()
    {
        return "http" . (($_SERVER['SERVER_PORT']==443) ? "s://" : "://") . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    }
}