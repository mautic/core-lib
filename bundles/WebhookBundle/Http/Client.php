<?php

/*
* @copyright   2019 Mautic, Inc. All rights reserved
* @author      Mautic, Inc.
*
* @link        https://mautic.com
*
* @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
*/

namespace Mautic\WebhookBundle\Http;

use Http\Adapter\Guzzle6\Client as GuzzleClient;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Psr\Http\Message\ResponseInterface;

class Client
{
    /**
     * @var CoreParametersHelper
     */
    private $coreParametersHelper;

    /**
     * @var RequestFactory
     */
    private $requestFactory;

    /**
     * @var GuzzleClient
     */
    private $httpClient;

    /**
     * @param CoreParametersHelper $coreParametersHelper
     * @param RequestFactory       $requestFactory
     * @param GuzzleClient         $httpClient
     */
    public function __construct(
        CoreParametersHelper $coreParametersHelper,
        RequestFactory $requestFactory,
        GuzzleClient $httpClient
    ) {
        $this->coreParametersHelper = $coreParametersHelper;
        $this->requestFactory       = $requestFactory;
        $this->httpClient           = $httpClient;
    }

    /**
     * @param string   $url
     * @param array    $payload
     * @param int|null $timeout
     * @param int|null $secret
     *
     * @return ResponseInterface
     */
    public function post($url, array $payload, $timeout = null, $secret = null)
    {
        if (is_array($payload)) {
            $payload = json_encode($payload);
        }

        // generate a base64 encoded HMAC-SHA256 signature of the payload
        $signature = base64_encode(hash_hmac('sha256', $payload, $secret, true));

        // Set up custom headers
        $headers = [
            'Content-Type'      => 'application/json',
            'Webhook-Signature' => $signature,
            'X-Origin-Base-URL' => $this->coreParametersHelper->getParameter('site_url'),
        ];

        $request = $this->requestFactory->create(
            'POST',
            $url,
            $headers,
            $payload
        );

        return $this->httpClient->sendRequest($request);
    }
}
