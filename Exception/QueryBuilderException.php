<?php

/**
 * Custom Query Builder Exception
 * This will handle all errors
 * from the query builder
 * (database error , syntax )
 * 
 * @package friendsher
 * 
 * @version 1.0
 */

class QueryBuilderException extends Exception {


    public function errorMessage() {
      return $this->getMessage();
    }

}