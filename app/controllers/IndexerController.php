<?php

namespace Chayka\Search;

use Chayka\Helpers\DateHelper;
use Chayka\Helpers\Util;
use Chayka\WP\Models\PostModel;
use Chayka\WP\MVC\Controller;
use Chayka\Helpers\InputHelper;
use Chayka\WP\Helpers\JsonHelper;

class IndexerController extends Controller{

    public function init(){
        // NlsHelper::load('main');
        // InputHelper::captureInput();
    }

    public function indexPostAction(){
        if(!SearchHelper::isIndexerEnabled()){
            return;
        }
        $postId = InputHelper::getParam('postId');
        if(!$postId){
            return;
        }
        $post = get_post($postId);
        if(!$post || is_wp_error($post)){
            return;
        }
        if(wp_is_post_autosave($post) || wp_is_post_revision($post)){
            return;
        }
        if($post->post_status == 'publish'
            && SearchHelper::isSearchEnabled($post->post_type)){
            SearchHelper::indexPost($post);
        }else{
            SearchHelper::deletePost($postId);
        }
    }

    public function deletePostAction(){
        if(!SearchHelper::isIndexerEnabled()){
            return;
        }
        $postId = InputHelper::getParam('postId');
        if(!$postId){
            return;
        }
        $post = get_post($postId);
        if(wp_is_post_autosave($post) || wp_is_post_revision($post)){
            return;
        }

        SearchHelper::deletePost($postId);

    }

    public function indexPostsAction(){
        set_time_limit(0);
        global $wpdb;
        Util::sessionStart();
        $number = InputHelper::getParam('number', 10);
        $postTypes = InputHelper::getParam('postType', '');
        $start = Util::getItem($_SESSION, 'wpp_BRX_SearchEngine.indexStarted');
        $update = InputHelper::getParam('update');
        $payload = array();
        if(!$start){
            $now = new \DateTime();
            $start = $_SESSION['wpp_BRX_SearchEngine.indexStarted'] = DateHelper::datetimeToDbStr($now);
            $payload['start'] = DateHelper::datetimeToJsonStr($now);
        }
        if($postTypes){
            $postTypes = explode(',', $postTypes);
        }else{
            $postTypes = SearchHelper::getSearchEnabledPostTypes();
        }
        $sql = sprintf(
            "SELECT SQL_CALC_FOUND_ROWS p.*, pm.meta_value AS last_indexed
            FROM $wpdb->posts AS p
            LEFT JOIN $wpdb->postmeta AS pm
                ON (p.ID = pm.post_id AND pm.meta_key = '%s')
            WHERE p.post_type IN ('%s')
                AND (p.post_status = 'publish')
                AND (pm.post_id IS NULL OR CAST( pm.meta_value AS DATETIME ) < %s)
            GROUP BY p.ID ORDER BY p.ID ASC LIMIT 0, %d",
            SearchHelper::META_FIELD_INDEXED, join("','", $postTypes), $update? "post_modified" :"'$start'", $number);

        $posts = PostModel::selectSql($sql);
        if(empty($posts)){
            $payload['error'] = mysql_error();
        }

        $payload['posts_found'] = $payload['posts_left'] = PostModel::postsFound();
        $payload['posts_indexed_before'] = SearchHelper::postsInIndex();
        $payload['log'] = array();
        $payload['posts_indexed'] = array();
        try{
            foreach($posts as $post){
                SearchHelper::indexPost($post);
                $payload['log'][] = sprintf("[%d:%s] %s",$post->getId(), $post->getType(), $post->getTitle());
                $payload['posts_left']--;
            }
            $payload['posts_indexed']['total'] = SearchHelper::postsInIndex();
            if(!$payload['posts_left']){
                unset($_SESSION['wpp_BRX_SearchEngine.indexStarted']);
                $payload['stop'] = DateHelper::datetimeToJsonStr(new \DateTime());
            }
        }catch(\Exception $e){
            JsonHelper::respond(null, $e->getCode(), $e->getMessage());
        }
        SearchHelper::commit();
        foreach($postTypes as $postType){
            $payload['posts_indexed'][$postType] = SearchHelper::postsInIndex($postType);
        }
        JsonHelper::respond($payload);

    }

    public function deletePostsAction(){
        set_time_limit(0);
        global $wpdb;
        $postTypes = InputHelper::getParam('postType', '');
        if($postTypes){
            $postTypes = explode(',', $postTypes);
            $total = SearchHelper::postsInIndex();
            foreach ($postTypes as $postType) {
                $total-=SearchHelper::postsInIndex($postType);
            }
            if($total >= 0){
                foreach ($postTypes as $postType) {
                    SearchHelper::deletePostsByKey('post_type', $postType);
                }
            }else{
                SearchHelper::flush();
            }
            JsonHelper::respond(SearchHelper::getPostTypeInfo($postTypes));
        }else{
            SearchHelper::flush();
            JsonHelper::respond(SearchHelper::getPostTypeInfo());
        }

    }

    public function flushAction(){
        SearchHelper::flush();
        echo SearchHelper::postsInIndex();
    }

    public function enableTypeAction(){
        $postType = InputHelper::getParam('postType');
        if($postType){
            SearchHelper::enableSearch($postType);
        }
        JsonHelper::respond(SearchHelper::getSearchEnabledPostTypes());
    }

    public function disableTypeAction(){
        $postType = InputHelper::getParam('postType');
        if($postType){
            SearchHelper::disableSearch($postType);
        }
        JsonHelper::respond(SearchHelper::getSearchEnabledPostTypes());
    }

    public function optimizeAction(){
        set_time_limit(0);
        SearchHelper::optimize();
        $dbDate = OptionHelper::getOption('lastOptimized');
        $date = DateHelper::dbStrToDatetime($dbDate);
        JsonHelper::respond(array('last_optimized'=>$date));
    }

}