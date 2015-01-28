<?php

namespace Chayka\Search;

use Chayka\WP\MVC\Controller;
use Chayka\Helpers\InputHelper;
use Chayka\WP\Helpers\JsonHelper;

class AdminController extends Controller{

    public function init(){
        // NlsHelper::load('main');
        // InputHelper::captureInput();
    }

    public function searchEngineAction(){
		$this->enqueueScriptStyle('chayka-options-form');
    }
    public function indexerAction(){
		$this->enqueueScriptStyle('chayka-search-indexer');
    }
}