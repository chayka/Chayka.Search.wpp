<?php

namespace Chayka\Search;

use Chayka\WP\MVC\Controller;

class AdminController extends Controller{

    public function init(){
        // NlsHelper::load('main');
        // InputHelper::captureInput();
    }

    public function searchEngineAction(){
		$this->enqueueNgScriptStyle('chayka-options-form');
    }
    public function indexerAction(){
		$this->enqueueNgScriptStyle('chayka-search-indexer');
    }
}