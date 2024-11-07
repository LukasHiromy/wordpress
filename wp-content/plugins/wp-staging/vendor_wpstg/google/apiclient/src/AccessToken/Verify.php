<?php

/*
 * Copyright 2008 Google Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
namespace WPStaging\Vendor\Google\AccessToken;

use WPStaging\Vendor\Firebase\JWT\ExpiredException as ExpiredExceptionV3;
use WPStaging\Vendor\Firebase\JWT\SignatureInvalidException;
use WPStaging\Vendor\GuzzleHttp\Client;
use WPStaging\Vendor\GuzzleHttp\ClientInterface;
use WPStaging\Vendor\phpseclib3\Crypt\PublicKeyLoader;
use WPStaging\Vendor\phpseclib3\Crypt\RSA\PublicKey;
use WPStaging\Vendor\Psr\Cache\CacheItemPoolInterface;
use WPStaging\Vendor\Google\Auth\Cache\MemoryCacheItemPool;
use WPStaging\Vendor\Google\Exception as GoogleException;
use WPStaging\Vendor\Stash\Driver\FileSystem;
use WPStaging\Vendor\Stash\Pool;
use DateTime;
use DomainException;
use Exception;
use WPStaging\Vendor\ExpiredException;
// Firebase v2
use LogicException;
/**
 * Wrapper around Google Access Tokens which provides convenience functions
 *
 */
class Verify
{
    const FEDERATED_SIGNON_CERT_URL = 'https://www.googleapis.com/oauth2/v3/certs';
    const OAUTH2_ISSUER = 'accounts.google.com';
    const OAUTH2_ISSUER_HTTPS = 'https://accounts.google.com';
    /**
     * @var ClientInterface The http client
     */
    private $http;
    /**
     * @var CacheItemPoolInterface cache class
     */
    private $cache;
    /**
     * Instantiates the class, but does not initiate the login flow, leaving it
     * to the discretion of the caller.
     */
    public function __construct(\WPStaging\Vendor\GuzzleHttp\ClientInterface $http = null, \WPStaging\Vendor\Psr\Cache\CacheItemPoolInterface $cache = null, $jwt = null)
    {
        if (null === $http) {
            $http = new \WPStaging\Vendor\GuzzleHttp\Client();
        }
        if (null === $cache) {
            $cache = new \WPStaging\Vendor\Google\Auth\Cache\MemoryCacheItemPool();
        }
        $this->http = $http;
        $this->cache = $cache;
        $this->jwt = $jwt ?: $this->getJwtService();
    }
    /**
     * Verifies an id token and returns the authenticated apiLoginTicket.
     * Throws an exception if the id token is not valid.
     * The audience parameter can be used to control which id tokens are
     * accepted.  By default, the id token must have been issued to this OAuth2 client.
     *
     * @param string $idToken the ID token in JWT format
     * @param string $audience Optional. The audience to verify against JWt "aud"
     * @return array the token payload, if successful
     */
    public function verifyIdToken($idToken, $audience = null)
    {
        if (empty($idToken)) {
            throw new \LogicException('id_token cannot be null');
        }
        // set phpseclib constants if applicable
        $this->setPhpsecConstants();
        // Check signature
        $certs = $this->getFederatedSignOnCerts();
        foreach ($certs as $cert) {
            try {
                $payload = $this->jwt->decode($idToken, $this->getPublicKey($cert), array('RS256'));
                if (\property_exists($payload, 'aud')) {
                    if ($audience && $payload->aud != $audience) {
                        return \false;
                    }
                }
                // support HTTP and HTTPS issuers
                // @see https://developers.google.com/identity/sign-in/web/backend-auth
                $issuers = array(self::OAUTH2_ISSUER, self::OAUTH2_ISSUER_HTTPS);
                if (!isset($payload->iss) || !\in_array($payload->iss, $issuers)) {
                    return \false;
                }
                return (array) $payload;
            } catch (\WPStaging\Vendor\ExpiredException $e) {
                return \false;
            } catch (\WPStaging\Vendor\Firebase\JWT\ExpiredException $e) {
                return \false;
            } catch (\WPStaging\Vendor\Firebase\JWT\SignatureInvalidException $e) {
                // continue
            } catch (\DomainException $e) {
                // continue
            }
        }
        return \false;
    }
    private function getCache()
    {
        return $this->cache;
    }
    /**
     * Retrieve and cache a certificates file.
     *
     * @param $url string location
     * @throws \Google\Exception
     * @return array certificates
     */
    private function retrieveCertsFromLocation($url)
    {
        // If we're retrieving a local file, just grab it.
        if (0 !== \strpos($url, 'http')) {
            if (!($file = \file_get_contents($url))) {
                throw new \WPStaging\Vendor\Google\Exception("Failed to retrieve verification certificates: '" . $url . "'.");
            }
            return \json_decode($file, \true);
        }
        $response = $this->http->get($url);
        if ($response->getStatusCode() == 200) {
            return \json_decode((string) $response->getBody(), \true);
        }
        throw new \WPStaging\Vendor\Google\Exception(\sprintf('Failed to retrieve verification certificates: "%s".', $response->getBody()->getContents()), $response->getStatusCode());
    }
    // Gets federated sign-on certificates to use for verifying identity tokens.
    // Returns certs as array structure, where keys are key ids, and values
    // are PEM encoded certificates.
    private function getFederatedSignOnCerts()
    {
        $certs = null;
        if ($cache = $this->getCache()) {
            $cacheItem = $cache->getItem('federated_signon_certs_v3');
            $certs = $cacheItem->get();
        }
        if (!$certs) {
            $certs = $this->retrieveCertsFromLocation(self::FEDERATED_SIGNON_CERT_URL);
            if ($cache) {
                $cacheItem->expiresAt(new \DateTime('+1 hour'));
                $cacheItem->set($certs);
                $cache->save($cacheItem);
            }
        }
        if (!isset($certs['keys'])) {
            throw new \WPStaging\Vendor\Google\AccessToken\InvalidArgumentException('federated sign-on certs expects "keys" to be set');
        }
        return $certs['keys'];
    }
    private function getJwtService()
    {
        $jwtClass = 'JWT';
        if (\class_exists('WPStaging\\Vendor\\Firebase\\JWT\\JWT')) {
            $jwtClass = 'WPStaging\\Vendor\\Firebase\\JWT\\JWT';
        }
        if (\property_exists($jwtClass, 'leeway') && $jwtClass::$leeway < 1) {
            // Ensures JWT leeway is at least 1
            // @see https://github.com/google/google-api-php-client/issues/827
            $jwtClass::$leeway = 1;
        }
        return new $jwtClass();
    }
    private function getPublicKey($cert)
    {
        $bigIntClass = $this->getBigIntClass();
        $modulus = new $bigIntClass($this->jwt->urlsafeB64Decode($cert['n']), 256);
        $exponent = new $bigIntClass($this->jwt->urlsafeB64Decode($cert['e']), 256);
        $component = array('n' => $modulus, 'e' => $exponent);
        if (\class_exists('WPStaging\\Vendor\\phpseclib3\\Crypt\\RSA\\PublicKey')) {
            /** @var PublicKey $loader */
            $loader = \WPStaging\Vendor\phpseclib3\Crypt\PublicKeyLoader::load($component);
            return $loader->toString('PKCS8');
        }
        $rsaClass = $this->getRsaClass();
        $rsa = new $rsaClass();
        $rsa->loadKey($component);
        return $rsa->getPublicKey();
    }
    private function getRsaClass()
    {
        if (\class_exists('WPStaging\\Vendor\\phpseclib3\\Crypt\\RSA')) {
            return 'WPStaging\\Vendor\\phpseclib3\\Crypt\\RSA';
        }
        if (\class_exists('WPStaging\\Vendor\\phpseclib\\Crypt\\RSA')) {
            return 'WPStaging\\Vendor\\phpseclib\\Crypt\\RSA';
        }
        return 'Crypt_RSA';
    }
    private function getBigIntClass()
    {
        if (\class_exists('WPStaging\\Vendor\\phpseclib3\\Math\\BigInteger')) {
            return 'WPStaging\\Vendor\\phpseclib3\\Math\\BigInteger';
        }
        if (\class_exists('WPStaging\\Vendor\\phpseclib\\Math\\BigInteger')) {
            return 'WPStaging\\Vendor\\phpseclib\\Math\\BigInteger';
        }
        return 'Math_BigInteger';
    }
    private function getOpenSslConstant()
    {
        if (\class_exists('WPStaging\\Vendor\\phpseclib3\\Crypt\\AES')) {
            return 'phpseclib3\\Crypt\\AES::ENGINE_OPENSSL';
        }
        if (\class_exists('WPStaging\\Vendor\\phpseclib\\Crypt\\RSA')) {
            return 'phpseclib\\Crypt\\RSA::MODE_OPENSSL';
        }
        if (\class_exists('WPStaging\\Vendor\\Crypt_RSA')) {
            return 'CRYPT_RSA_MODE_OPENSSL';
        }
        throw new \Exception('Cannot find RSA class');
    }
    /**
     * phpseclib calls "phpinfo" by default, which requires special
     * whitelisting in the AppEngine VM environment. This function
     * sets constants to bypass the need for phpseclib to check phpinfo
     *
     * @see phpseclib/Math/BigInteger
     * @see https://github.com/GoogleCloudPlatform/getting-started-php/issues/85
     */
    private function setPhpsecConstants()
    {
        if (\filter_var(\getenv('GAE_VM'), \FILTER_VALIDATE_BOOLEAN)) {
            if (!\defined('WPStaging\\Vendor\\MATH_BIGINTEGER_OPENSSL_ENABLED')) {
                \define('WPStaging\\Vendor\\MATH_BIGINTEGER_OPENSSL_ENABLED', \true);
            }
            if (!\defined('WPStaging\\Vendor\\CRYPT_RSA_MODE')) {
                \define('WPStaging\\Vendor\\CRYPT_RSA_MODE', \constant($this->getOpenSslConstant()));
            }
        }
    }
}
