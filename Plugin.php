<?php

namespace Chayka\Search;

use Chayka\Helpers\HttpHeaderHelper;
use Chayka\Helpers\InputHelper;
use Chayka\WP;

class Plugin extends WP\Plugin{

    /* chayka: constants */
    
    public static $instance = null;

    public static function init(){
        if(!static::$instance){
            static::$instance = $app = new self(__FILE__, array(
                'indexer',
                'search',
                /* chayka: init-controllers */
            ));
            $app->dbUpdate(array());
	        $app->addSupport_UriProcessing();
	        $app->addSupport_ConsolePages();
	        $app->addSupport_Metaboxes();
	        $app->addSupport_PostProcessing(100);


            /* chayka: init-addSupport */
        }
    }


    /**
     * Register your action hooks here using $this->addAction();
     */
    public function registerActions() {
        $this->addAction('lucene_index_post', 'indexPost', 10, 2);
        $this->addAction('lucene_delete_post', 'deletePost', 10, 2);
//        $this->addAction('save_post', 'indexPost', 100, 2);
//        $this->addAction('save_post', 'savePost', 90, 2);
//        $this->addAction('delete_post', 'deletePost', 100, 2);
//        $this->addAction('trashed_post', 'deletePost', 100, 2);

        $this->addAction('lucene_enable_indexer', 'enableIndexer', 10);
        $this->addAction('lucene_disable_indexer', 'disableIndexer', 10);
        $this->addAction('parse_request', function(){
            if(isset($_REQUEST['s']) && !is_admin()){
                $s = InputHelper::getParam('s');
                $url = '/search/?q='.  urldecode($s);
                HttpHeaderHelper::redirect($url);
            }
        });
    	/* chayka: registerActions */
    }

    /**
     * Register your action hooks here using $this->addFilter();
     */
    public function registerFilters() {
		/* chayka: registerFilters */
    }

    /**
     * Register scripts and styles here using $this->registerScript() and $this->registerStyle()
     *
     * @param bool $minimize
     */
    public function registerResources($minimize = false) {
        $this->registerBowerResources(true);

        $this->setResSrcDir('src/');
        $this->setResDistDir('dist/');

        $this->registerNgScript('chayka-search-indexer', 'ng-modules/chayka-search-indexer.js', array('chayka-wp-admin', 'chayka-spinners', 'chayka-ajax', 'chayka-modals', 'chayka-utils'));
        $this->registerStyle('chayka-search-indexer', 'ng-modules/chayka-search-indexer.css', array('chayka-wp-admin', 'chayka-spinners', 'chayka-modals'));

        $this->registerNgScript('chayka-search-engine', 'ng-modules/chayka-search-engine.js', array('chayka-spinners', 'chayka-ajax', 'chayka-utils'));
        $this->registerStyle('chayka-search-engine', 'ng-modules/chayka-search-engine.css', array('chayka-spinners', 'dashicons'));
		/* chayka: registerResources */
    }

    /**
     * Routes are to be added here via $this->addRoute();
     */
    public function registerRoutes() {
        $this->addRoute('default');
        $this->addRoute('search', 'search/?scope/*', array('controller' => 'search', 'action' => 'search', 'scope' => 'all'));
        $this->addRoute('index-post', 'indexer/index-post/:postId/*', array('controller' => 'indexer', 'action' => 'index-post'));
        $this->addRoute('delete-post', 'indexer/delete-post/:postId/*', array('controller' => 'indexer', 'action' => 'delete-post'));
    }

    /**
     * Registering console pages
     */
    public function registerConsolePages(){
        $this->addConsolePage('Search Engine', 'update_core', 'search-engine', '/admin/search-engine', 'dashicons-search', '81');
        $this->addConsoleSubPage('search-engine', 'Indexer', 'update_core', 'indexer', '/admin/indexer');


        /* chayka: registerConsolePages */
    }
    
    /**
     * Add custom metaboxes here via addMetabox() calls;
     */
    public function registerMetaboxes(){
        /* chayka: registerMetaboxes */
    }

    /**
     * Custom Sidebars are to be added here via $this->registerSidebar();
     */
    public function registerSidebars() {
        $this->registerSidebar('Search', 'search');
		/* chayka: registerSidebars */
    }
    
    /* postProcessing */

    /**
     * This is a hook for save_post
     *
     * @param integer $postId
     * @param \WP_Post $post
     */
    public function savePost($postId, $post){
        $this->processRequest('/indexer/index-post/'.$postId);
    }
    
    /**
     * This is a hook for delete_post
     *
     * @param integer $postId
     */
    public function deletePost($postId){
        $this->processRequest('/indexer/delete-post/'.$postId);
    }
    
    /**
     * This is a hook for trashed_post
     *
     * @param integer $postId
     */
    public function trashedPost($postId){
        $this->deletePost($postId);
    }
}