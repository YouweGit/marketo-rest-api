<?php
/*
 * This file is part of the Marketo REST API Client package.
 *
 * (c) 2014 Daniel Chesterton
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CSD\Marketo;

// Guzzle
use CommerceGuys\Guzzle\Plugin\Oauth2\Oauth2Plugin;
use Guzzle\Common\Collection;
use Guzzle\Service\Client as GuzzleClient;
use Guzzle\Service\Description\ServiceDescription;

// Response classes
use CSD\Marketo\Response\AddOrRemoveLeadsToListResponse;
use CSD\Marketo\Response\AssociateLeadResponse;
use CSD\Marketo\Response\CreateOrUpdateLeadsResponse;
use CSD\Marketo\Response\GetCampaignResponse;
use CSD\Marketo\Response\GetCampaignsResponse;
use CSD\Marketo\Response\GetCustomObjectsResponse;
use CSD\Marketo\Response\GetLeadChanges;
use CSD\Marketo\Response\GetLeadResponse;
use CSD\Marketo\Response\GetLeadPartitionsResponse;
use CSD\Marketo\Response\GetLeadsResponse;
use CSD\Marketo\Response\GetListResponse;
use CSD\Marketo\Response\GetListsResponse;
use CSD\Marketo\Response\GetPagingToken;
use CSD\Marketo\Response\IsMemberOfListResponse;

/**
 * Guzzle client for communicating with the Marketo.com REST API.
 *
 * @link http://developers.marketo.com/documentation/rest/
 *
 * @author Daniel Chesterton <daniel@chestertondevelopment.com>
 */
class Client extends GuzzleClient {

  /**
   * @var array
   */
  private $marketoObjects = [
    'Leads' => 'leads',
    'Companies' => 'companies',
    'Opportunities' => 'opportunities',
    'Opportunities Roles' => 'opportunities/roles',
    'Sales Persons' => 'salespersons',
  ];

  /**
   * {@inheritdoc}
   */
  public static function factory($config = []) {
    $default = [
      'url' => FALSE,
      'munchkin_id' => FALSE,
      'version' => 1,
      'bulk' => FALSE,
    ];

    $required = ['client_id', 'client_secret', 'version'];
    $config = Collection::fromConfig($config, $default, $required);

    $url = $config->get('url');

    if (!$url) {
      $munchkin = $config->get('munchkin_id');

      if (!$munchkin) {
        throw new \Exception('Must provide either a URL or Munchkin code.');
      }

      $url = sprintf('https://%s.mktorest.com', $munchkin);
    }

    $grantType = new Credentials($url, $config->get('client_id'), $config->get('client_secret'));
    $auth = new Oauth2Plugin($grantType);

    if ($config->get('bulk') === TRUE) {
      $restUrl = sprintf('%s/bulk/v%d', rtrim($url, '/'), $config->get('version'));
    }
    else {
      $restUrl = sprintf('%s/rest/v%d', rtrim($url, '/'), $config->get('version'));
    }

    $client = new self($restUrl, $config);
    $client->addSubscriber($auth);
    $client->setDescription(ServiceDescription::factory(__DIR__ . '/service.json'));
    $client->setDefaultOption('headers/Content-Type', 'application/json');

    return $client;
  }

  /**
   * Import Leads via file upload
   *
   * @param array $args - Must contain 'format' and 'file' keys
   *     e.g. array( 'format' => 'csv', 'file' => '/full/path/to/filename.csv'
   *
   * @return array
   *
   * @throws \Exception
   * @link http://developers.marketo.com/documentation/rest/import-lead/
   *
   */
  public function importLeadsCsv($args) {
    if (!is_readable($args['file'])) {
      throw new \Exception('Cannot read file: ' . $args['file']);
    }

    if (empty($args['format'])) {
      $args['format'] = 'csv';
    }

    return $this->getResult('importLeadsCsv', $args);
  }

  /**
   * Get status of an async Import Lead file upload
   *
   * @param int $batchId
   *
   * @return array
   * @throws \Exception
   *
   * @link http://developers.marketo.com/documentation/rest/get-import-lead-status/
   *
   */
  public function getBulkUploadStatus($batchId) {
    if (empty($batchId) || !is_int($batchId)) {
      throw new \Exception('Invalid $batchId provided in ' . __METHOD__);
    }

    return $this->getResult('getBulkUploadStatus', ['batchId' => $batchId]);
  }

  /**
   * Get failed lead results from an Import Lead file upload
   *
   * @param int $batchId
   *
   * @return \Guzzle\Http\Message\Response
   * @throws \Exception
   *
   * @link http://developers.marketo.com/documentation/rest/get-import-failure-file/
   *
   */
  public function getBulkUploadFailures($batchId) {
    if (empty($batchId) || !is_int($batchId)) {
      throw new \Exception('Invalid $batchId provided in ' . __METHOD__);
    }

    return $this->getResult('getBulkUploadFailures', ['batchId' => $batchId]);
  }

  /**
   * Get warnings from Import Lead file upload
   *
   * @param int $batchId
   *
   * @return \Guzzle\Http\Message\Response
   * @throws \Exception
   *
   * @link http://developers.marketo.com/documentation/rest/get-import-warning-file/
   *
   */
  public function getBulkUploadWarnings($batchId) {
    if (empty($batchId) || !is_int($batchId)) {
      throw new \Exception('Invalid $batchId provided in ' . __METHOD__);
    }

    return $this->getResult('getBulkUploadWarnings', ['batchId' => $batchId]);
  }

  /**
   * Calls the CreateOrUpdateLeads command with the given action.
   *
   * @param string $action
   * @param array $leads
   * @param string $lookupField
   * @param array $args
   * @param bool $returnRaw
   *
   * @return CreateOrUpdateLeadsResponse
   * @see Client::createLeads()
   * @see Client::createOrUpdateLeads()
   * @see Client::updateLeads()
   * @see Client::createDuplicateLeads()
   *
   * @link http://developers.marketo.com/documentation/rest/createupdate-leads/
   *
   */
  private function createOrUpdateLeadsCommand($action, $leads, $lookupField, $args, $returnRaw = FALSE) {
    $args['input'] = $leads;
    $args['action'] = $action;

    if (isset($lookupField)) {
      $args['lookupField'] = $lookupField;
    }

    return $this->getResult('createOrUpdateLeads', $args, FALSE, $returnRaw);
  }

  /**
   * Only update the given opportunity roles.
   *
   * @param array $opportunitiesRoles Array of arrays.
   * @param string $dedupeBy
   * @param array $args
   * @param bool|false $returnRaw
   *
   * @return GetLeadsResponse
   * @throws \Exception
   *
   * @link http://developers.marketo.com/rest-api/endpoint-reference/lead-database-endpoint-reference/#!/Opportunities/syncOpportunityRolesUsingPOST
   *
   */
  public function updateOpportunitiesRoles($opportunitiesRoles, $dedupeBy = 'dedupeFields', $args = [], $returnRaw = FALSE) {
    return $this->createOrUpdateObjects('Opportunities Roles', 'updateOnly', $opportunitiesRoles, $dedupeBy, $args, $returnRaw);
  }

  /**
   * Only create the given opportunity roles.
   *
   * @param array $opportunitiesRoles Array of arrays.
   * @param string $dedupeBy
   * @param array $args
   * @param bool|false $returnRaw
   *
   * @return GetLeadsResponse
   * @throws \Exception
   *
   * @link http://developers.marketo.com/rest-api/endpoint-reference/lead-database-endpoint-reference/#!/Opportunities/syncOpportunityRolesUsingPOST
   *
   */
  public function createOpportunitiesRoles($opportunitiesRoles, $dedupeBy = 'dedupeFields', $args = [], $returnRaw = FALSE) {
    return $this->createOrUpdateObjects('Opportunities Roles', 'createOnly', $opportunitiesRoles, $dedupeBy, $args, $returnRaw);
  }

  /**
   * Create or update the given opportunity roles.
   *
   * @param array $opportunitiesRoles Array of arrays.
   * @param string $dedupeBy
   * @param array $args
   * @param bool|false $returnRaw
   *
   * @return GetLeadsResponse
   * @throws \Exception
   *
   * @link http://developers.marketo.com/rest-api/endpoint-reference/lead-database-endpoint-reference/#!/Opportunities/syncOpportunityRolesUsingPOST
   *
   */
  public function createOrUpdateOpportunitiesRoles($opportunitiesRoles, $dedupeBy = 'dedupeFields', $args = [], $returnRaw = FALSE) {
    return $this->createOrUpdateObjects('Opportunities Roles', 'createOrUpdate', $opportunitiesRoles, $dedupeBy, $args, $returnRaw);
  }

  /**
   * Only update the given opportunities.
   *
   * @param array $opportunities Array of arrays.
   * @param string $dedupeBy
   * @param array $args
   * @param bool|false $returnRaw
   *
   * @return GetLeadsResponse
   * @throws \Exception
   *
   * @link http://developers.marketo.com/rest-api/endpoint-reference/lead-database-endpoint-reference/#!/Opportunities/syncOpportunitiesUsingPOST
   *
   */
  public function updateOpportunities($opportunities, $dedupeBy = 'dedupeFields', $args = [], $returnRaw = FALSE) {
    return $this->createOrUpdateObjects('Opportunities', 'updateOnly', $opportunities, $dedupeBy, $args, $returnRaw);
  }

  /**
   * Only create the given opportunities.
   *
   * @param array $opportunities Array of arrays.
   * @param string $dedupeBy
   * @param array $args
   * @param bool|false $returnRaw
   *
   * @return GetLeadsResponse
   * @throws \Exception
   *
   * @link http://developers.marketo.com/rest-api/endpoint-reference/lead-database-endpoint-reference/#!/Opportunities/syncOpportunitiesUsingPOST
   *
   */
  public function createOpportunities($opportunities, $dedupeBy = 'dedupeFields', $args = [], $returnRaw = FALSE) {
    return $this->createOrUpdateObjects('Opportunities', 'createOnly', $opportunities, $dedupeBy, $args, $returnRaw);
  }

  /**
   * Create or update the given opportunities.
   *
   * @param array $opportunities Array of arrays.
   * @param string $dedupeBy
   * @param array $args
   * @param bool|false $returnRaw
   *
   * @return GetLeadsResponse
   * @throws \Exception
   *
   * @link http://developers.marketo.com/rest-api/endpoint-reference/lead-database-endpoint-reference/#!/Opportunities/syncOpportunitiesUsingPOST
   *
   */
  public function createOrUpdateOpportunities($opportunities, $dedupeBy = 'dedupeFields', $args = [], $returnRaw = FALSE) {
    return $this->createOrUpdateObjects('Opportunities', 'createOrUpdate', $opportunities, $dedupeBy, $args, $returnRaw);
  }

  /**
   * Only update the given companies.
   *
   * @param array $companies Array of arrays.
   * @param string $dedupeBy
   * @param array $args
   * @param bool|false $returnRaw
   *
   * @return GetLeadsResponse
   * @throws \Exception
   *
   * @link http://developers.marketo.com/rest-api/endpoint-reference/lead-database-endpoint-reference/#!/Companies/syncCompaniesUsingPOST
   *
   */
  public function updateCompanies($companies, $dedupeBy = 'dedupeFields', $args = [], $returnRaw = FALSE) {
    return $this->createOrUpdateObjects('Companies', 'updateOnly', $companies, $dedupeBy, $args, $returnRaw);
  }

  /**
   * Only create the given companies.
   *
   * @param array $companies Array of arrays.
   * @param string $dedupeBy
   * @param array $args
   * @param bool|false $returnRaw
   *
   * @return GetLeadsResponse
   * @throws \Exception
   *
   * @link http://developers.marketo.com/rest-api/endpoint-reference/lead-database-endpoint-reference/#!/Companies/syncCompaniesUsingPOST
   *
   */
  public function createCompanies($companies, $dedupeBy = 'dedupeFields', $args = [], $returnRaw = FALSE) {
    return $this->createOrUpdateObjects('Companies', 'createOnly', $companies, $dedupeBy, $args, $returnRaw);
  }

  /**
   * Create or update the given companies.
   *
   * @param array $companies Array of arrays.
   * @param string $dedupeBy
   * @param array $args
   * @param bool|false $returnRaw
   *
   * @return GetLeadsResponse
   * @throws \Exception
   *
   * @link http://developers.marketo.com/rest-api/endpoint-reference/lead-database-endpoint-reference/#!/Companies/syncCompaniesUsingPOST
   *
   */
  public function createOrUpdateCompanies($companies, $dedupeBy = 'dedupeFields', $args = [], $returnRaw = FALSE) {
    return $this->createOrUpdateObjects('Companies', 'createOrUpdate', $companies, $dedupeBy, $args, $returnRaw);
  }

  /**
   * Generic method to create or update Marketo objects.
   *
   * @param string $objectName
   * @param string $action Should be createOnly, updateOnly, or createOrUpdate.
   * @param array $records Array of arrays.
   * @param string $dedupeBy
   * @param array $args
   * @param bool|false $returnRaw
   *
   * @return GetLeadsResponse
   * @throws \Exception
   *
   */
  private function createOrUpdateObjects($objectName, $action, $records, $dedupeBy, $args = [], $returnRaw = FALSE) {
    if (!isset($this->marketoObjects[$objectName])) {
      throw new \Exception('createOrUpdate() Expected parameter $objectName, to be a valid Marketo object ' . "but $objectName provided");
    };

    $args['objectName'] = $this->marketoObjects[$objectName];
    $args['action'] = $action;
    $args['input'] = $records;
    $args['dedupeBy'] = $dedupeBy;

    return $this->getResult('createOrUpdateObject', $args, FALSE, $returnRaw);
  }

  /**
   * Create the given leads.
   *
   * @param array $leads
   * @param string $lookupField
   * @param array $args
   *
   * @return CreateOrUpdateLeadsResponse
   * @see Client::createOrUpdateLeadsCommand()
   *
   * @link http://developers.marketo.com/documentation/rest/createupdate-leads/
   *
   */
  public function createLeads($leads, $lookupField = NULL, $args = []) {
    return $this->createOrUpdateLeadsCommand('createOnly', $leads, $lookupField, $args);
  }

  /**
   * Update the given leads, or create them if they do not exist.
   *
   * @param array $leads
   * @param string $lookupField
   * @param array $args
   *
   * @return CreateOrUpdateLeadsResponse
   * @see Client::createOrUpdateLeadsCommand()
   *
   * @link http://developers.marketo.com/documentation/rest/createupdate-leads/
   *
   */
  public function createOrUpdateLeads($leads, $lookupField = NULL, $args = []) {
    return $this->createOrUpdateLeadsCommand('createOrUpdate', $leads, $lookupField, $args);
  }

  /**
   * Update the given leads.
   *
   * @param array $leads
   * @param string $lookupField
   * @param array $args
   *
   * @return CreateOrUpdateLeadsResponse
   * @see Client::createOrUpdateLeadsCommand()
   *
   * @link http://developers.marketo.com/documentation/rest/createupdate-leads/
   *
   */
  public function updateLeads($leads, $lookupField = NULL, $args = []) {
    return $this->createOrUpdateLeadsCommand('updateOnly', $leads, $lookupField, $args);
  }

  /**
   * Create duplicates of the given leads.
   *
   * @param array $leads
   * @param string $lookupField
   * @param array $args
   *
   * @return CreateOrUpdateLeadsResponse
   * @see Client::createOrUpdateLeadsCommand()
   *
   * @link http://developers.marketo.com/documentation/rest/createupdate-leads/
   *
   */
  public function createDuplicateLeads($leads, $lookupField = NULL, $args = []) {
    return $this->createOrUpdateLeadsCommand('createDuplicate', $leads, $lookupField, $args);
  }

  /**
   * Get multiple lists.
   *
   * @param int|array $ids Filter by one or more IDs
   * @param array $args
   * @param bool $returnRaw
   *
   * @return GetListsResponse
   * @link http://developers.marketo.com/documentation/rest/get-multiple-lists/
   *
   */
  public function getLists($ids = NULL, $args = [], $returnRaw = FALSE) {
    if ($ids) {
      $args['id'] = $ids;
    }

    return $this->getResult('getLists', $args, is_array($ids), $returnRaw);
  }

  /**
   * Get a list by ID.
   *
   * @param int $id
   * @param array $args
   * @param bool $returnRaw
   *
   * @return GetListResponse
   * @link http://developers.marketo.com/documentation/rest/get-list-by-id/
   *
   */
  public function getList($id, $args = [], $returnRaw = FALSE) {
    $args['id'] = $id;

    return $this->getResult('getList', $args, FALSE, $returnRaw);
  }

  /**
   * Get multiple leads by filter type.
   *
   * @param string $filterType One of the supported filter types, e.g. id,
   *   cookie or email. See Marketo's documentation for all types.
   * @param string $filterValues Comma separated list of filter values
   * @param array $fields Array of field names to be returned in the response
   * @param string $nextPageToken
   * @param bool $returnRaw
   *
   * @return GetLeadsResponse
   * @link http://developers.marketo.com/documentation/rest/get-multiple-leads-by-filter-type/
   *
   */
  public function getLeadsByFilterType($filterType, $filterValues, $fields = [], $nextPageToken = NULL, $returnRaw = FALSE) {
    $args['filterType'] = $filterType;
    $args['filterValues'] = $filterValues;

    if ($nextPageToken) {
      $args['nextPageToken'] = $nextPageToken;
    }

    if (count($fields)) {
      $args['fields'] = implode(',', $fields);
    }

    return $this->getResult('getLeadsByFilterType', $args, FALSE, $returnRaw);
  }

  /**
   * Get a lead by filter type.
   *
   * Convenient method which uses
   * {@link http://developers.marketo.com/documentation/rest/get-multiple-leads-by-filter-type/} internally and just returns the first lead if there is one.
   *
   * @param string $filterType One of the supported filter types, e.g. id,
   *   cookie or email. See Marketo's documentation for all types.
   * @param string $filterValue The value to filter by
   * @param array $fields Array of field names to be returned in the response
   * @param bool $returnRaw
   *
   * @return GetLeadResponse
   * @link http://developers.marketo.com/documentation/rest/get-multiple-leads-by-filter-type/
   *
   */
  public function getLeadByFilterType($filterType, $filterValue, $fields = [], $returnRaw = FALSE) {
    $args['filterType'] = $filterType;
    $args['filterValues'] = $filterValue;

    if (count($fields)) {
      $args['fields'] = implode(',', $fields);
    }

    return $this->getResult('getLeadByFilterType', $args, FALSE, $returnRaw);
  }

  /**
   * Get lead partitions.
   *
   * @param array $args
   * @param bool $returnRaw
   *
   * @return GetLeadPartitionsResponse
   * @link http://developers.marketo.com/documentation/rest/get-lead-partitions/
   *
   */
  public function getLeadPartitions($args = [], $returnRaw = FALSE) {
    return $this->getResult('getLeadPartitions', $args, FALSE, $returnRaw);
  }

  /**
   * Get multiple leads by list ID.
   *
   * @param int $listId
   * @param array $args
   * @param bool $returnRaw
   *
   * @return GetLeadsResponse
   * @link http://developers.marketo.com/documentation/rest/get-multiple-leads-by-list-id/
   *
   */
  public function getLeadsByList($listId, $args = [], $returnRaw = FALSE) {
    $args['listId'] = $listId;

    return $this->getResult('getLeadsByList', $args, FALSE, $returnRaw);
  }

  /**
   * Get a lead by ID.
   *
   * @param int $id
   * @param array $fields
   * @param array $args
   * @param bool $returnRaw
   *
   * @return GetLeadResponse
   * @link http://developers.marketo.com/documentation/rest/get-lead-by-id/
   *
   */
  public function getLead($id, $fields = NULL, $args = [], $returnRaw = FALSE) {
    $args['id'] = $id;

    if (is_array($fields)) {
      $args['fields'] = implode(',', $fields);
    }

    return $this->getResult('getLead', $args, FALSE, $returnRaw);
  }

  /**
   * Check if a lead is a member of a list.
   *
   * @param int $listId List ID
   * @param int|array $id Lead ID or an array of Lead IDs
   * @param array $args
   * @param bool $returnRaw
   *
   * @return IsMemberOfListResponse
   * @link http://developers.marketo.com/documentation/rest/member-of-list/
   *
   */
  public function isMemberOfList($listId, $id, $args = [], $returnRaw = FALSE) {
    $args['listId'] = $listId;
    $args['id'] = $id;

    return $this->getResult('isMemberOfList', $args, is_array($id), $returnRaw);
  }

  /**
   * Get a campaign by ID.
   *
   * @param int $id
   * @param array $args
   * @param bool $returnRaw
   *
   * @return GetCampaignResponse
   * @link http://developers.marketo.com/documentation/rest/get-campaign-by-id/
   *
   */
  public function getCampaign($id, $args = [], $returnRaw = FALSE) {
    $args['id'] = $id;

    return $this->getResult('getCampaign', $args, FALSE, $returnRaw);
  }

  /**
   * Get campaigns.
   *
   * @param int|array $ids A single Campaign ID or an array of Campaign IDs
   * @param array $args
   * @param bool $returnRaw
   *
   * @return GetCampaignsResponse
   * @link http://developers.marketo.com/documentation/rest/get-multiple-campaigns/
   *
   */
  public function getCampaigns($ids = NULL, $args = [], $returnRaw = FALSE) {
    if ($ids) {
      $args['id'] = $ids;
    }

    return $this->getResult('getCampaigns', $args, is_array($ids), $returnRaw);
  }

  /**
   * Add one or more leads to the specified list.
   *
   * @param int $listId List ID
   * @param int|array $leads Either a single lead ID or an array of lead IDs
   * @param array $args
   * @param bool $returnRaw
   *
   * @return AddOrRemoveLeadsToListResponse
   * @link http://developers.marketo.com/documentation/rest/add-leads-to-list/
   *
   */
  public function addLeadsToList($listId, $leads, $args = [], $returnRaw = FALSE) {
    $args['listId'] = $listId;
    $args['id'] = (array) $leads;

    return $this->getResult('addLeadsToList', $args, TRUE, $returnRaw);
  }

  /**
   * Remove one or more leads from the specified list.
   *
   * @param int $listId List ID
   * @param int|array $leads Either a single lead ID or an array of lead IDs
   * @param array $args
   * @param bool $returnRaw
   *
   * @return AddOrRemoveLeadsToListResponse
   * @link http://developers.marketo.com/documentation/rest/remove-leads-from-list/
   *
   */
  public function removeLeadsFromList($listId, $leads, $args = [], $returnRaw = FALSE) {
    $args['listId'] = $listId;
    $args['id'] = (array) $leads;

    return $this->getResult('removeLeadsFromList', $args, TRUE, $returnRaw);
  }

  /**
   * Delete one or more leads
   *
   * @param int|array $leads Either a single lead ID or an array of lead IDs
   * @param array $args
   * @param bool $returnRaw
   *
   * @return \CSD\Marketo\Response\DeleteLeadResponse
   * @link http://developers.marketo.com/documentation/rest/delete-lead/
   *
   */
  public function deleteLead($leads, $args = [], $returnRaw = FALSE) {
    $args['id'] = (array) $leads;

    return $this->getResult('deleteLead', $args, TRUE, $returnRaw);
  }

  /**
   * Trigger a campaign for one or more leads.
   *
   * @param int $id Campaign ID
   * @param int|array $leads Either a single lead ID or an array of lead IDs
   * @param array $tokens Key value array of tokens to send new values for.
   * @param array $args
   * @param bool $returnRaw
   *
   * @return \CSD\Marketo\Response|string
   * @link http://developers.marketo.com/documentation/rest/request-campaign/
   *
   */
  public function requestCampaign($id, $leads, $tokens = [], $args = [], $returnRaw = FALSE) {
    $args['id'] = $id;

    $args['input'] = [
      'leads' => array_map(function ($id) {
        return ['id' => $id];
      }, (array) $leads),
    ];

    if (!empty($tokens)) {
      $args['input']['tokens'] = $tokens;
    }

    return $this->getResult('requestCampaign', $args, FALSE, $returnRaw);
  }

  /**
   * Schedule a campaign
   *
   * @param int $id Campaign ID
   * @param \DateTime $runAt The time to run the campaign. If not provided,
   *   campaign will be run in 5 minutes.
   * @param array $tokens Key value array of tokens to send new values for.
   * @param array $args
   * @param bool $returnRaw
   *
   * @return \CSD\Marketo\Response|string
   * @link http://developers.marketo.com/documentation/rest/schedule-campaign/
   *
   */
  public function scheduleCampaign($id, \DateTime $runAt = NULL, $tokens = [], $args = [], $returnRaw = FALSE) {
    $args['id'] = $id;

    if (!empty($runAt)) {
      $args['input']['runAt'] = $runAt->format('c');
    }

    if (!empty($tokens)) {
      $args['input']['tokens'] = $tokens;
    }

    return $this->getResult('scheduleCampaign', $args, FALSE, $returnRaw);
  }

  /**
   * Associate a lead
   *
   * @param int $id
   * @param string $cookie
   * @param array $args
   * @param bool $returnRaw
   *
   * @return \CSD\Marketo\Response|string
   * @link http://developers.marketo.com/documentation/rest/associate-lead/
   *
   */
  public function associateLead($id, $cookie = NULL, $args = [], $returnRaw = FALSE) {
    $args['id'] = $id;

    if (!empty($cookie)) {
      $args['cookie'] = $cookie;
    }

    return $this->getResult('associateLead', $args, FALSE, $returnRaw);
  }

  /**
   * Get the paging token required for lead activity and changes
   *
   * @param string $sinceDatetime String containing a datetime
   * @param array $args
   * @param bool $returnRaw
   *
   * @return GetPagingToken
   * @link http://developers.marketo.com/documentation/rest/get-paging-token/
   *
   */
  public function getPagingToken($sinceDatetime, $args = [], $returnRaw = FALSE) {
    $args['sinceDatetime'] = $sinceDatetime;

    return $this->getResult('getPagingToken', $args, FALSE, $returnRaw);
  }

  /**
   * Add 1+ custom activities to a lead. Each activity added may be for the
   * same or different lead.
   *
   * @see http://developers.marketo.com/rest-api/lead-database/activities/
   * @see http://developers.marketo.com/rest-api/endpoint-reference/lead-database-endpoint-reference/#/Activities/addCustomActivityUsingPOST
   *
   * @example: Here's some examples of what the $activities parameter may look
   *   like:
   * $activities = [
   *     [ // Example of minimum set of attributes for an activity
   *         'leadId' => 4,
   *         'activityTypeId' => 100002, // Created ahead of time in Marketo
   *   Portal Admin
   *         'primaryAttributeValue' => 'FooBar',
   *     ],
   *     [ // Example of all optional attributes used
   *         'leadId' => 6,
   *         'activityTypeId' => 100003, // Created ahead of time in Marketo
   *   Portal Admin
   *         'primaryAttributeValue' => 42,
   *         'activityDate' => new \DateTime('+1 day'),
   *         'apiName' => 'FooBar',
   *         'status' => 'updated',
   *         'attributes' => [
   *             [
   *                 'name' => 'quantity',
   *                 'value' => 3,
   *             ],
   *             [
   *                 'name' => 'price',
   *                 'value' => 123.45,
   *                 'apiName' => 'FooBar',
   *             ]
   *         ]
   *     ],
   * ];
   *
   * @param array $activities Array of arrays.
   * @param array $args
   * @param bool $returnRaw
   *
   * @return AddCustomActivitiesResponse
   */
  public function addCustomActivities($activities, $args = [], $returnRaw = FALSE) {
    $args['input'] = [];
    foreach ($activities as $activity) {
      // Validation: Required parameters.
      foreach ([
                 'leadId',
                 'activityTypeId',
                 'primaryAttributeValue',
               ] as $required) {
        if (!isset($activity[$required])) {
          throw new \InvalidArgumentException("Required parameter \"{$required}\" is missing.");
        }
      }

      // Validation: Activity date is required by the API, but making it optional here, defaulting to now.
      if (!isset($activity['activityDate'])) {
        $activity['activityDate'] = new \DateTime();
      }
      elseif (!($activity['activityDate'] instanceof \DateTime)) {
        throw new \InvalidArgumentException('Required parameter "activityDate" must be a DateTime object.');
      }

      // Format required parameters
      $input = [
        'leadId' => (int) $activity['leadId'],
        'activityTypeId' => (int) $activity['activityTypeId'],
        'primaryAttributeValue' => (string) $activity['primaryAttributeValue'],
        'activityDate' => $activity['activityDate']->format('c'),
      ];

      // Optional parameters
      if (isset($activity['apiName'])) {
        $input['apiName'] = (string) $activity['apiName'];
      }
      if (isset($activity['status'])) {
        $input['status'] = (string) $activity['status'];
      }

      // The optional 'attributes' parameter has some validation.
      if (isset($activity['attributes'])) {
        if (!is_array($activity['attributes'])) {
          throw new \InvalidArgumentException('Optional parameter "attributes" must be an array.');
        }

        $input['attributes'] = []; // Initialize
        foreach ($activity['attributes'] as $attribute) {
          if (!is_array($attribute)) {
            throw new \InvalidArgumentException('The "attributes" parameter must contain child array(s).');
          }
          // Required child parameters
          foreach (['name', 'value'] as $required) {
            if (!isset($attribute[$required])) {
              throw new \InvalidArgumentException("Required array key \"{$required}\" is missing in the \"attributes\" parameter.");
            }
          }
          $inputAttribute = [
            'name' => (string) $attribute['name'],
            'value' => (string) $attribute['value'],
          ];
          // Optional child parameters
          if (isset($attribute['apiName'])) {
            $inputAttribute['apiName'] = (string) $attribute['apiName'];
          }

          $input['attributes'][] = $inputAttribute;
        }
      }

      $args['input'][] = $input;
    }

    return $this->getResult('addCustomActivities', $args, FALSE, $returnRaw);
  }

  /**
   * Get lead changes
   *
   * @param string $nextPageToken Next page token
   * @param string|array $fields
   * @param array $args
   * @param bool $returnRaw
   *
   * @return GetLeadChanges
   * @see  getPagingToken
   *
   * @link http://developers.marketo.com/documentation/rest/get-lead-changes/
   */
  public function getLeadChanges($nextPageToken, $fields, $args = [], $returnRaw = FALSE) {
    $args['nextPageToken'] = $nextPageToken;
    $args['fields'] = (array) $fields;

    if (count($fields)) {
      $args['fields'] = implode(',', $fields);
    }

    return $this->getResult('getLeadChanges', $args, TRUE, $returnRaw);
  }

  /**
   * Update an editable section in an email
   *
   * @param int $emailId
   * @param array $args
   * @param bool $returnRaw
   *
   * @return \CSD\Marketo\Response|string
   * @link http://developers.marketo.com/documentation/asset-api/update-email-content-by-id/
   *
   */
  public function updateEmailContent($emailId, $args = [], $returnRaw = FALSE) {
    $args['id'] = $emailId;

    return $this->getResult('updateEmailContent', $args, FALSE, $returnRaw);
  }

  /**
   * Update an editable section in an email
   *
   * @param int $emailId
   * @param string $htmlId
   * @param array $args
   * @param bool $returnRaw
   *
   * @return \CSD\Marketo\Response|string
   * @link http://developers.marketo.com/documentation/asset-api/update-email-content-in-editable-section/
   *
   */
  public function updateEmailContentInEditableSection($emailId, $htmlId, $args = [], $returnRaw = FALSE) {
    $args['id'] = $emailId;
    $args['htmlId'] = $htmlId;

    return $this->getResult('updateEmailContentInEditableSection', $args, FALSE, $returnRaw);
  }

  /**
   * Approve an email
   *
   * @param int $emailId
   * @param array $args
   * @param bool $returnRaw
   *
   * @return \CSD\Marketo\Response\ApproveEmailResponse
   * @link http://developers.marketo.com/documentation/asset-api/approve-email-by-id/
   *
   */
  public function approveEmail($emailId, $args = [], $returnRaw = FALSE) {
    $args['id'] = $emailId;

    return $this->getResult('approveEmailbyId', $args, FALSE, $returnRaw);
  }

  /**
   * Get lead activities.
   *
   * @param string $nextPageToken
   *   Next page token @param string|array $leads
   * @param string|array $activityTypeIds
   *   Activity Types @param array $args
   * @param bool $returnRaw
   *
   * @return \CSD\Marketo\Response|string
   * @see: `::getPagingToken`
   * @see: `::getActivityTypes`.
   * @see  getPagingToken
   * @link http://developers.marketo.com/documentation/rest/get-lead-activities/
   *
   */
  public function getLeadActivity($nextPageToken, $leads, $activityTypeIds, $args = [], $returnRaw = FALSE) {
    $args['nextPageToken'] = $nextPageToken;
    $args['leadIds'] = count((array) $leads) ? implode(',', (array) $leads) : '';
    $args['activityTypeIds'] = count((array) $activityTypeIds) ? implode(',', (array) $activityTypeIds) : '';

    return $this->getResult('getLeadActivity', $args, TRUE, $returnRaw);
  }

  /**
   * Describe the leads object
   *
   * @param bool|false $returnRaw
   *
   * @return Response
   * @throws \Exception
   *
   * @link http://developers.marketo.com/rest-api/endpoint-reference/lead-database-endpoint-reference/#!/Leads/describeUsingGET_2
   *
   */
  public function describeLeads($returnRaw = FALSE) {
    return $this->describeObject('Leads', $returnRaw);
  }

  /**
   * Describe the opportunities object
   *
   * @param bool|false $returnRaw
   *
   * @return Response
   * @throws \Exception
   *
   * @link http://developers.marketo.com/rest-api/endpoint-reference/lead-database-endpoint-reference/#!/Opportunities/describeUsingGET_3
   *
   */
  public function describeOpportunities($returnRaw = FALSE) {
    return $this->describeObject('Opportunities', $returnRaw);
  }

  /**
   * Describe the opportunities roles object.
   *
   * @param bool|false $returnRaw
   *
   * @return Response
   * @throws \Exception
   *
   * @link http://developers.marketo.com/rest-api/endpoint-reference/lead-database-endpoint-reference/#!/Opportunities/describeOpportunityRoleUsingGET
   *
   */
  public function describeOpportunityRoles($returnRaw = FALSE) {
    return $this->describeObject('Opportunities Roles', $returnRaw);
  }

  /**
   * Describe the companies object.
   *
   * @param bool|false $returnRaw
   *
   * @return Response
   * @throws \Exception
   *
   * @link http://developers.marketo.com/rest-api/endpoint-reference/lead-database-endpoint-reference/#!/Companies/describeUsingGET
   *
   */
  public function describeCompanies($returnRaw = FALSE) {
    return $this->describeObject('Companies', $returnRaw);
  }

  /**
   * Describe the Sales Persons object.
   *
   * @param bool|false $returnRaw
   *
   * @return Response
   * @throws \Exception
   *
   * @link http://developers.marketo.com/rest-api/endpoint-reference/lead-database-endpoint-reference/#!/Sales_Persons/describeUsingGET_4
   *
   */
  public function describeSalesPersons($returnRaw = FALSE) {
    return $this->describeObject('Sales Persons', $returnRaw);
  }

  /**
   * Generic method to describe a Marketo object.
   *
   * @param string $objectName
   * @param bool|false $returnRaw
   *
   * @return Response
   * @throws \Exception
   */
  private function describeObject($objectName, $returnRaw = FALSE) {
    if (!isset($this->marketoObjects[$objectName])) {
      throw new \Exception('describeObject() Expected parameter $objectName, to be a valid Marketo object ' . "but $objectName provided");
    };

    $args['objectName'] = $this->marketoObjects[$objectName];
    return $this->getResult('describeObject', $args, FALSE, $returnRaw);
  }

  /**
   * Get a lead by filter type.
   *
   * @param string $objectName Name of the custom object.
   * @param string $filterType One of the supported filter types, e.g. id,
   *   cookie or email. See Marketo's documentation for all types.
   * @param string $filterValue The value to filter by.
   * @param array $fields Array of field names to be returned in the response.
   * @param bool $returnRaw Boolean to determine the return value should be
   *   raw.
   *
   * @return GetCustomObjectsResponse
   * @link https://developers.marketo.com/rest-api/endpoint-reference/lead-database-endpoint-reference/#!/Custom_Objects/getCustomObjectsUsingGET
   *
   */
  public function getCustomObjectsByFilterType($objectName, $filterType, $filterValue, $fields = [], $returnRaw = FALSE) {
    $args['objectName'] = $objectName;
    $args['filterType'] = $filterType;
    $args['filterValues'] = $filterValue;

    if (count($fields)) {
      $args['fields'] = implode(',', $fields);
    }

    return $this->getResult('getCustomObjectsByFilterType', $args, FALSE, $returnRaw);
  }

  /**
   * Internal helper method to actually perform command.
   *
   * @param string $command
   * @param array $args
   * @param bool $fixArgs
   * @param bool $returnRaw
   *
   * @return \CSD\Marketo\Response|string
   */
  private function getResult($command, $args, $fixArgs = FALSE, $returnRaw = FALSE) {
    $cmd = $this->getCommand($command, $args);

    // Marketo expects parameter arrays in the format id=1&id=2, Guzzle formats them as id[0]=1&id[1]=2.
    // Use a quick regex to fix it where necessary.
    if ($fixArgs) {
      $cmd->prepare();

      $url = preg_replace('/id%5B([0-9]+)%5D/', 'id', $cmd->getRequest()
        ->getUrl());
      $cmd->getRequest()->setUrl($url);
    }

    $cmd->prepare();

    if ($returnRaw) {
      return $cmd->getResponse()->getBody(TRUE);
    }

    return $cmd->getResult();
  }

}
