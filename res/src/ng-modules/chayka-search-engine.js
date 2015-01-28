'use strict';

(function(angular){

    angular.module('chayka-search-engine', ['chayka-ajax', 'chayka-spinners'])
        .controller('searchForm', ['$scope', '$translate', 'ajax', function($scope, $translate, ajax, modals) {
            $scope.searchScope = 'all';
            $scope.getSearchUri = function(){
                return '/search/' + (!!$scope.searchScope && $scope.searchScope !=='all' ? $scope.searchScope + '/': '');
            };
        }])
    ;
}(window.angular));
