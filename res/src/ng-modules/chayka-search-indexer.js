'use strict';

(function(angular){

angular.module('chayka-search-indexer', ['chayka-wp-admin', 'chayka-modals', 'chayka-ajax', 'chayka-spinners'])
    .controller('controlPanel', ['$scope', '$filter', '$translate', 'ajax', 'modals', function($scope, $filter, $translate, ajax, modals){

        /**
         * Index stats by post types
         *
         * @type {object}
         */
        $scope.indexState = {};

        /**
         * Index overall stats
         *
         * @type {object}
         */
        $scope.indexStats = {
            indexed: 0,
            total: 0
        };

        /**
         * Current operation in progress.
         *
         * @type {{number: number, postTypes: Array, command: string, state: string, start: string, stop: string, total: int}}
         */
        $scope.operation = {
            number: 0,
            postTypes: [],
            command: '',
            state: '',
            start: '',
            stop: '',
            total: 0
        };

        /**
         * Datetime of last index optimization
         *
         * @type {null|Date}
         */
        $scope.lastOptimized = null;

        /**
         * JobControl component
         *
         * @type {obj}
         */
        $scope.jobControl = null;

        /**
         * Add log message
         *
         * @param message
         */
        $scope.addLogMessage = function(message){
            $scope.jobControl.addLogMessage(message);
        };

        /**
         * Update overall stats (indexed/total)
         */
        $scope.updateIndexStats = function(){
            $scope.indexStats.indexed = 0;
            $scope.indexStats.total = 0;
            angular.forEach($scope.indexState, function(state){
                $scope.indexStats.indexed += state.indexed;
                $scope.indexStats.total += state.total;
            });
        };

        /**
         * Load index state
         */
        $scope.loadIndexState = function(){
            ajax.get('/api/indexer/info', {
                success: function(data){
                    console.dir({indexState: data.payload});
                    angular.extend($scope.indexState, data.payload.stats);
                    $scope.lastOptimized = data.payload.last_optimized ? new Date(data.payload.last_optimized):null;
                    $scope.updateIndexStats();
                }
            });
        };

        /**
         * Optimize index
         */
        $scope.buttonOptimizeClicked = function(){
            ajax.get('/api/indexer/optimize', {
                success: function(data){
                    console.dir({indexState: data.payload});
                    angular.extend($scope.indexState, data.payload.stats);
                    $scope.lastOptimized = data.payload.last_optimized ? new Date(data.payload.last_optimized):null;
                    $scope.updateIndexStats();
                }
            });
        };

        /**
         * Get search enabled postTypes
         *
         * @returns {Array}
         */
        $scope.getEnabledPostTypes = function(){
            var postTypes = [];

            angular.forEach($scope.indexState, function(state, pt){
                if(state.enabled){
                    postTypes.push(pt);
                }
            });

            return postTypes;
        };

        /**
         * Triggered when user turns on or off search for some postType
         *
         * @param {string} postType
         */
        $scope.searchEnabledChanged = function(postType) {
            var value = parseInt($scope.indexState[postType].enabled);
            console.log('search for ' + postType + ' is now ' + (value ? 'enabled' : 'disabled'));
            var url = value ? '/api/indexer/enable-type' : '/api/indexer/disable-type';

            ajax.post(url, {
                postType: postType
            },{
                spinnerMessage: 'Updating settings...',
                errorMessage: 'Error updating settings'
                //success: function (data) {
                //    console.dir({'data': data});
                //    angular.extend($scope.indexState, data.payload);
                //    $scope.updateIndexStats();
                //}
            });
        };

        /**
         * Triggered, when button delete clicked
         *
         * @param {string} postType
         */
        $scope.buttonDeleteIndexClicked = function(postType){
            var postTypes = postType?[postType]:$scope.getEnabledPostTypes();
            var message = $translate.instant( postType?
                    'Delete index for {{ postTypes }}?':
                    'Delete index?',
                {postTypes: postTypes.join(', ')});
            modals.confirm(message, function(){
                $scope.deleteIndex(postTypes);
            });
        };

        /**
         * Delete index for specified postType.
         * If postType omitted, drop whole index.
         *
         * @param postTypes
         */
        $scope.deleteIndex = function(postTypes) {
            console.log('deleting index for ' + postTypes.join(', '));

            ajax.post('/api/indexer/delete-posts', {
                postType: postTypes.join(',') || ''
            }, {
                spinnerMessage: 'Deleting index...',
                errorMessage: 'Error deleteing index',
                success: function (data) {
                    angular.extend($scope.indexState, data.payload);
                    $scope.updateIndexStats();
                }
            });
        };


        /**
         * Triggered, when button build clicked
         *
         * @param {string} postType
         */
        $scope.buttonBuildIndexClicked = function(postType){
            var postTypes = postType?[postType]:$scope.getEnabledPostTypes();
            var message = $translate.instant( postType?
                    'Build index for {{ postTypes }}?':
                    'Build index?',
                {postTypes: postTypes.join(', ')});
            modals.confirm(message, function(){
                $scope.processCommand('build-index', postTypes);
            });
        };

        /**
         * Triggered, when button update clicked
         *
         * @param {string} postType
         */
        $scope.buttonUpdateIndexClicked = function(postType){
            var postTypes = postType?[postType]:$scope.getEnabledPostTypes();
            var message = $translate.instant( postType?
                    'Update index for {{ postTypes }}?':
                    'Update index?',
                {postTypes: postTypes.join(', ')});
            modals.confirm(message, function(){
                $scope.processCommand('update-index', postTypes);
            });
        };

        /**
         * Build Index
         *
         * @param {string} [command]
         * @param {Array} [postTypes]
         * @param {int} [number]
         */
        $scope.processCommand = function(command, postTypes, number) {
            var o = $scope.operation;
            number = number || $scope.jobControl.getPerIteration() || 10;
            o.number = number;
            o.command = command || o.command;
            o.postTypes = postTypes || o.postTypes;
            o.state = 'running';
            console.log('processing "' + o.command + '" for ' + o.postTypes.join(', '));
            $scope.jobControl.started();
            var url = '';
            var data = {postType: o.postTypes.join(',')};
            switch (o.command) {
                case 'build-index':
                    url = '/api/indexer/index-posts/';
                    data.number = number;
                    break;
                case 'update-index':
                    url = '/api/indexer/index-posts/';
                    data.number = number;
                    data.update = 1;
                    break;
            }

            ajax.post(url, data, {
                spinner: false,
                //errorMessage: 'Error',

                /**
                 * @param {{start: string, stop: string, total: int, posts_indexed: Array, posts_found: int, posts_left: int}} data
                 */
                success: function (data) {
                    console.dir({'data': data});
                    if (data.payload.start) {
                        o.start = data.payload.start;
                        o.total = data.payload.posts_found;
                        $scope.addLogMessage('Operation started: ' + $filter('date')(data.payload.start, 'd MMM y, HH:mm:ss'));
                    }
                    if (!o.total) {
                        o.total = data.payload.posts_found;
                    }
                    o.postTypes.forEach(function(postType){
                        $scope.indexState[postType].indexed = data.payload.posts_indexed[postType];
                    });
                    $scope.updateIndexStats();
                    data.payload.log.forEach(function(message){
                        $scope.addLogMessage(message);
                    });
                    $scope.jobControl.setProgress(o.total - data.payload.posts_left, o.total);
                    if (data.payload.stop) {
                        o.stop = data.payload.stop;
                        $scope.addLogMessage('Operation finished: ' +  $filter('date')(data.payload.stop, 'd MMM y, HH:mm:ss'));
                        $scope.jobControl.stopped();
                    } else if (o.state === 'paused') {
                        $scope.addLogMessage('Operation paused');
                        $scope.jobControl.paused();
                    } else if (o.state === 'running') {
                        $scope.processCommand();
                    } else if (o.state === 'stopped') {
                        $scope.addLogMessage('Operation stopped');
                        $scope.operation = {};
                        $scope.jobControl.stopped();
                    }
                },
                error: function (response) {
                    $scope.jobControl.paused();
                }
            });
        };

        /**
         * Triggered when User clicks stop on JobControl
         */
        $scope.startOperation = function(){
            alert('start!');
        };

        /**
         * Triggered when User clicks stop on JobControl
         */
        $scope.stopOperation = function(){
            if($scope.jobControl.getState()==='paused'){
                $scope.operation = {'state': 'stopped'};
                $scope.jobControl.stopped();
            }
            $scope.operation.state = 'stopped';
        };

        /**
         * Triggered when User clicks pause on JobControl
         */
        $scope.pauseOperation = function(){
            $scope.operation.state = 'paused';
        };

        /**
         * Triggered when User clicks resume on JobControl
         */
        $scope.resumeOperation = function(){
            $scope.operation.state = 'running';
            $scope.processCommand();
            $scope.jobControl.resumed();
        };

        $scope.$on('JobControl.start', $scope.startOperation);
        $scope.$on('JobControl.stop', $scope.stopOperation);
        $scope.$on('JobControl.pause', $scope.pauseOperation);
        $scope.$on('JobControl.resume', $scope.resumeOperation);

        $scope.loadIndexState();
    }])
    .config(['$translateProvider', function($translateProvider) {

        $translateProvider.translations('en-US', {
            'Post Type': 'Post Type',
            'Search': 'Search',
            'Indexed': 'Indexed',
            'Controls': 'Controls',
            'No': 'No',
            'Yes': 'Yes',
            'Build Index': 'Build Index',
            'Update Index': 'Update Index',
            'Delete Index': 'Delete Index',
            'Build index for {{ postTypes }}?': 'Build index for {{ postTypes }}?',
            'Build index?': 'Build index?',
            'Update index for {{ postTypes }}?': 'Update index for {{ postTypes }}?',
            'Update index?': 'Update index?',
            'Delete index for {{ postTypes }}?': 'Delete index for {{ postTypes }}?',
            'Delete index?': 'Delete index?',
            'You need optimize index each time you index posts.': 'You need optimize index each time you index posts.',
            'Last optimized': 'Last optimized',
            'Optimize Index': 'Optimize Index'
        });

        $translateProvider.translations('ru-RU', {
            'Post Type': 'Тип записи',
            'Search': 'Поиск',
            'Indexed': 'В индексе',
            'Controls': 'Управление',
            'No': 'Нет',
            'Yes': 'Да',
            'Build Index': 'Построить индекс',
            'Update Index': 'Обновить индекс',
            'Delete Index': 'Удалить индекс',
            'Build index for {{ postTypes }}?': 'Построить индекс для {{ postTypes }}?',
            'Build index?': 'Построить индекс?',
            'Update index for {{ postTypes }}?': 'Обновить индекс для {{ postTypes }}?',
            'Update index?': 'Обновить индекс?',
            'Delete index for {{ postTypes }}?': 'Удалить индекс для {{ postTypes }}?',
            'Delete index?': 'Удалить индекс?',
            'You need optimize index each time you index posts.': 'После индексации записей, индекс необходимо оптимизировать.',
            'Last optimized': 'Последний раз:',
            'Optimize Index': 'Оптимизировать индекс'
        });
    }])
;
}(window.angular));
