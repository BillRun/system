<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2021 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This Trait is used for API pagination.
 */
trait Billrun_Traits_Api_Pagination {
    
    /**
     * The requested page size
     * @var int
     */
    protected $pageSize;
    
    /**
     * The requested page number 
     * @var int
     */
    protected $pageNumber;
    
    /**
     * Get total pages for the entities 
     * @var int
     */
    protected $totalPages;
    
    /**
     * Flag if pagination needed for request
     * @var boolean
     */
    protected $pagination = false;

    /**
     * Set page size
     * @param int $size - requested page size
     */
    protected function setPageSize($size){
        $this->pageSize = $size;
    }
    
    /**
     * Set page number
     * @param int $page - requested page
     */
    protected function setPageNumber($page){
        $this->pageNumber = $page;
    }
    
    /**
     * Set pagination parameters (size and page)
     * @param int $page - requested page
     * @param int $size - requested page size
     */
    protected function setPaginationParams($page, $size){
        $this->checkIfValid($page, $size);
        $this->setPageNumber($page);
        $this->setPageSize($size);
    }
    
    /**
     * Filter entities by page and size page (and save the total pages for this request)
     * @param array $entities
     * @param type $page
     * @param type $size
     * @return type
     */
    protected function filterEntitiesByPagination($entities, $page = -1, $size = -1){
        $this->setPaginationParams($page, $size);
        if(!$this->pagination){//for no pagination
            return $entities;
        }
        $this->totalPages = ceil(count($entities) / $this->pageSize);
        $offset = ($this->pageNumber - 1) * $this->pageSize;
        return array_slice($entities, $offset, $this->pageSize);
    }
    
    /**
     * Return the total pages for the pagination request 
     * @return int
     */
    protected function getTotalPages(){
        return $this->totalPages;
    }
    
    /**
     * Return pagination flag.
     * @return boolean - true if pagination needed for request, false otherwise
     */
    protected function paginationRequest(){
        return $this->pagination;
    }
    
    /**
     * Check if pagination parameters (size and page) are valid parameters -> if no throw exception
     * and check if pagination needed
     * @param int $page - requested page
     * @param int $size - requested page size
     * @throws Exception
     */
    protected function checkIfValid($page, $size){
        if($page == -1 || ($page == 1 && $size == -1)){//-1 for no pagination of for unlimited size
            $this->pagination = false;
            return;
        }
        if(!is_integer(intval($page)) || intval($page) <= 0 ){
            throw new Exception('Unsupport parameter value: "page" : ' . $page);
        }
        
        if(!is_integer(intval($size)) || intval($size) <= 0 ){
            throw new Exception('Unsupport parameter value: "size" : ' . $size);
        }
        $this->pagination = true;
    }
    
}