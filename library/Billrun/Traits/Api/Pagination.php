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
    
    protected $pageSize;
    
    protected $pageNumber;
    
    protected $totalPages;
    
    protected $pagination = true;




    private function setPageSize($size){
        $this->pageSize = $size;
    }
    
    private function setPageNumber($page){
        $this->pageNumber = $page;
    }
    
    protected function setPaginationParams($page = -1, $size = -1){
        $this->checkIfValid($page, $size);
        $this->setPageNumber($page);
        $this->setPageSize($size);
    }
    
    protected function fillterEntitiesByPagination($entities){       
        if(!$this->pagination){//for no pagination
            return $entities;
        }
        $this->totalPages = ceil(count($entities) / $this->pageSize);
        $minInRange = ($this->pageNumber - 1) * $this->pageSize;
        $maxInRange = $minInRange + $this->pageSize -1 ;
        return array_slice($entities,$minInRange, $maxInRange);
    }
    
    protected function getTotalPages(){
        return $this->totalPages;
    }
    
    protected function paginationRequest(){
        return $this->pagination;
    }
    
    private function checkIfValid($page, $size){
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
    }
    
}