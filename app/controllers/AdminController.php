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
        //	AclHelper::apiAuthRequired();

        InputHelper::validateInput(true);

		$valid = true;

		if(!$valid){
			JsonHelper::respondError("Scary error message");
		}

		try{
			//	do something usefull
			
			$payload = array(
			);

			JsonHelper::respond($payload);

		}catch(\Exception $e){
			JsonHelper::respondException($e);
		}
    }
}