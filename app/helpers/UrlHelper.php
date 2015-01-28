<?php
/**
 * Created by PhpStorm.
 * User: borismossounov
 * Date: 26.01.15
 * Time: 11:43
 */

namespace Chayka\Search;


class UrlHelper {

    /**
     * Get url to search results
     *
     * @param string $term
     * @param null $scope
     * @param int $page
     * @param bool $debug
     * @return string
     */
    public static function search($term, $scope=null, $page=1, $debug = false){
        if(!$scope){
            $scope = 'all';
        }
        if(is_array($scope)){
            $scope = join(',', $scope);
        }

        $url = '/search/';
        if($scope && $scope !== 'all'){
            $url.=$scope.'/';
        }
        $params = array(
            'q'=>$term,
        );
        if($page!=1){
            $params['page'] = $page;
        }
        if($debug){
            $params['debug'] = 1;
        }

        $url.='?'.build_query($params);

        return $url;
    }

}