<?php
/*
 * Copyright 2015 Google Inc.
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

namespace Google\Auth;

use GuzzleHttp\Stream\Stream;
use GuzzleHttp\ClientInterface;

/**
 * DefaultCredentials is used to preload the credentials file, to determine
 * which type of credentials should be loaded.
 */
class DefaultCredentials extends CredentialsLoader
{

  /**
   * Create a new Credentials instance.
   *
   * @param string|array scope the scope of the access request, expressed
   *   either as an Array or as a space-delimited String.
   *
   * @param Stream jsonKeyStream read it to get the JSON credentials.
   *
   */
  public static function makeCredentials($scope, Stream $jsonKeyStream)
  {
    $jsonKey = json_decode($jsonKeyStream->getContents(), true);
    if (!array_key_exists('type', $jsonKey)) {
      throw new \InvalidArgumentException(
          'json key is missing the type field');
    }

    if ($jsonKey['type'] == 'service_account') {
      return new ServiceAccountCredentials($scope, $jsonKey);

    } else if ($jsonKey['type'] == 'authorized_user') {
      return new UserRefreshCredentials($scope, $jsonKey);

    } else {
      throw new \InvalidArgumentException(
          'invalid value in the type field');
    }
  }

}


/**
 * ApplicationDefaultCredentials obtains the default credentials for
 * authorizing a request to a Google service.
 *
 * Application Default Credentials are described here:
 * https://developers.google.com/accounts/docs/application-default-credentials
 *
 * This class implements the search for the application default credentials as
 * described in the link.
 *
 * It provides two factory methods:
 * - #get returns the computed credentials object
 * - #getFetcher returns an AuthTokenFetcher built from the credentials object
 *
 * This allows it to be used as follows with GuzzleHttp\Client:
 *
 *   use GuzzleHttp\Client;
 *   use Google\Auth\ApplicationDefaultCredentials;
 *
 *   $client = new Client([
 *      'base_url' => 'https://www.googleapis.com/taskqueue/v1beta2/projects/',
 *      'defaults' => ['auth' => 'google_auth']  // authorize all requests
 *   ]);
 *   $fetcher = ApplicationDefaultCredentials::getFetcher(
 *       'https://www.googleapis.com/auth/taskqueue');
 *   $client->getEmitter()->attach();
 *
 *   $res = $client->('myproject/taskqueues/myqueue');
 */
class ApplicationDefaultCredentials
{
  /**
   * Obtains an AuthTokenFetcher that uses the default FetchAuthTokenInterface
   * implementation to use in this environment.
   *
   * If supplied, $scope is used to in creating the credentials instance if
   * this does not fallback to the compute engine defaults.
   *
   * @param string|array scope the scope of the access request, expressed
   *   either as an Array or as a space-delimited String.
   * @param $client GuzzleHttp\ClientInterface optional client.
   * @param cacheConfig configuration for the cache when it's present
   * @param object $cache an implementation of CacheInterface
   *
   * @throws DomainException if no implementation can be obtained.
   */
  public static function getFetcher(
      $scope = null,
      ClientInterface $client = null,
      array $cacheConfig = null,
      CacheInterface $cache = null)
  {
    $creds = self::getCredentials($scope, $client);
    return new AuthTokenFetcher($creds, $cacheConfig, $cache);
  }

  /**
   * Obtains the default FetchAuthTokenInterface implementation to use
   * in this environment.
   *
   * If supplied, $scope is used to in creating the credentials instance if
   * this does not fallback to the Compute Engine defaults.
   *
   * @param string|array scope the scope of the access request, expressed
   *   either as an Array or as a space-delimited String.
   *
   * @param $client GuzzleHttp\ClientInterface optional client.
   * @throws DomainException if no implementation can be obtained.
   */
  public static function getCredentials($scope = null, $client = null)
  {
    $creds = DefaultCredentials::fromEnv($scope);
    if (!is_null($creds)) {
      return $creds;
    }
    $creds = DefaultCredentials::fromWellKnownFile($scope);
    if (!is_null($creds)) {
      return $creds;
    }
    if (!GCECredentials::onGce($client)) {
      throw new \DomainException(self::notFound());
    }
    return new GCECredentials();
  }

  private static function notFound()
  {
    $msg = 'Could not load the default credentials. Browse to ';
    $msg .= 'https://developers.google.com';
    $msg .= '/accounts/docs/application-default-credentials';
    $msg .= ' for more information' ;
    return $msg;
  }
}
