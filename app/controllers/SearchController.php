<?php

namespace Chayka\Search;

use Chayka\Helpers\Util;
use Chayka\WP\MVC\Controller;
use Chayka\Helpers\InputHelper;
use Chayka\WP\Helpers\JsonHelper;

class SearchController extends Controller{

    public function init(){
        // NlsHelper::load('main');
        // InputHelper::captureInput();
    }

    public function searchAction(){
        $term = InputHelper::getParam('q');
        $scope = InputHelper::getParam('scope', 'all');
        $page = InputHelper::getParam('page', 1);
        $debug = InputHelper::getParam('debug', 0);
        $limit = OptionHelper::getOption('searchLimit', 0);
        $customLimit = InputHelper::getParam('limit', false);
        if($customLimit!==false){
            $limit = $customLimit;
        }

        $posts = array();
        $terms = array();
        $vipPosts = array();
        $itemsPerPage = 10;
        if($term){
            SearchHelper::setLimit($limit);
            $itemsPerPage = OptionHelper::getOption('itemsPerPage', 10);
            $_SESSION['search_scope'] = $scope;
            $title = NlsHelper::_('Search Results');
            if('all' != $scope){
                $scopes = SearchHelper::getScopes();
                $scopeData = Util::getItem($scopes, $scope);
                $scopeLabel = Util::getItem($scopeData, 'label');
                $title = sprintf('&quot;%s&quot; %s', $term, $scopeLabel);

            }
            $this->setTitle($title);

            $vipsPerPage = OptionHelper::getOption('vipItemsPerPage', 3);

            $vipPosts = $vipsPerPage?
                SearchHelper::searchPosts($term, $scope, $page, $vipsPerPage, 'vip_keywords', true):
                array();

            $posts = SearchHelper::searchPosts($term, $scope, $page, $itemsPerPage, null);

            $pageLinkPattern = UrlHelper::search($term, $scope, '.page.', $debug);
            $this->getPagination()
                ->setupItems($pageLinkPattern, $page, SearchHelper::getTotalFound(), $itemsPerPage);
//            $this->setupNavigation($term, $scope, $page, $itemsPerPage, SearchHelper::getTotalFound(), $debug);
            foreach ($posts as $post) {
                $post->loadTerms();
            }
//            $words = preg_split('%[\s]+%u', $term);
//            $pieces = array();
//            foreach ($words as $word) {
//                $pieces[] = "name LIKE '$word%'";
//            }
//            $where = join(' OR ', $pieces);
//            global $wpdb;
//            $wpdbquery = "
//                SELECT *
//                FROM $wpdb->terms AS tr
//                JOIN $wpdb->term_taxonomy AS tx USING (term_id)
//                WHERE $where
//                ";
//            $terms = $wpdb->get_results($wpdbquery);
        }
        $this->view->assign('posts', $posts);
        $this->view->assign('vipPosts', $vipPosts);
        $this->view->assign('itemsPerPage', $itemsPerPage);

        $this->view->assign('scope', $scope);
        $this->view->assign('term', $term);
        $this->view->assign('terms', $terms);
        $this->view->assign('debug', $debug);
//        HtmlHelper::setSidebarId('search-results');

        $this->enqueueScriptStyle('chayka-search-engine');
//        $this->enqueueStyle('se-search-page');
//        wp_enqueue_style('pagination');
//        $this->enqueueScript('se-search-form');
        if($debug){
//            $this->enqueueStyle('se-search-debug');
        }
        $template = SearchHelper::getPageTemplate();
        if($template){
            $this->setWpTemplate($template);
        }

    }

//    protected function setupNavigation($term, $scope, $page, $itemsPerPage, $total, $debug = false){
//        $pagination = new PaginationModel();
//        $pagination->setCurrentPage($page);
//        $pagination->setPackSize(10);
//        $pagination->setTotalPages(ceil($total / $itemsPerPage));
//        $pagination->setItemsPerPage($itemsPerPage);
////        $router = Util::getFront()->getRouter();
//        $pageLinkPattern = UrlHelper_wpp_BRX_SearchEngine::search($term, $scope, '.page.', $debug);//$router->assemble(array('mode'=>$mode, 'page'=>'.page.', 'taxonomy'=>$taxonomy, 'scope'=>$scope), 'tag');
//        $pagination->setPageLinkPattern($pageLinkPattern);
//        $this->view->pagination = $pagination;
//    }


} 