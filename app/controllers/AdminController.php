<?php

namespace Chayka\Search;

use Chayka\WP\MVC\Controller;

class AdminController extends Controller{

    public function init(){
        // NlsHelper::load('main');
        // InputHelper::captureInput();
    }

    public function searchEngineAction(){
		$this->enqueueNgScriptStyle('chayka-wp-admin');
    }
    public function indexerAction(){
		$this->enqueueNgScriptStyle('chayka-search-indexer');
    }
}