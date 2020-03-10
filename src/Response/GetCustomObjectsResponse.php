<?php
/*
 * This file is part of the Marketo REST API Client package.
 *
 */

namespace CSD\Marketo\Response;

use CSD\Marketo\Response as Response;

/**
 * Response for the getCustomObjectsByFilterType API method.
 *
 * @author Jon te Riele <j.teriele@youweagency.com>
 */
class GetCustomObjectsResponse extends Response {

  /**
   * @return array|null
   */
  public function getCustomObjects() {
    if ($this->isSuccess()) {
      $result = $this->getResult();
      return $result[0];
    }
    return NULL;
  }

  /**
   * Override success function as Marketo incorrectly responds 'success'
   * even if the lead ID does not exist. Overriding it makes it consistent
   * with other API methods such as getList.
   *
   * @return bool
   */
  public function isSuccess() {
    return parent::isSuccess() ? count($this->getResult()) > 0 : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getError() {
    // if it's successful, don't return an error message
    if ($this->isSuccess()) {
      return NULL;
    }

    // if an error has been returned by Marketo, return that
    if ($error = parent::getError()) {
      return $error;
    }

    // if it's not successful and there's no error from Marketo, create one
    return array(
      'code' => '',
      'message' => 'Custom Objects not found',
    );
  }

}
