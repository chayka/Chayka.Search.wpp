'use strict';

(function(angular){

    angular.module('chayka-search-engine', ['chayka-ajax', 'chayka-spinners'])
        .controller('searchForm', ['$scope', function($scope) {
            $scope.searchScope = 'all';
            $scope.getSearchUri = function(){
                return '/search/' + (!!$scope.searchScope && $scope.searchScope !=='all' ? $scope.searchScope + '/': '');
            };
        }])
    ;
}(window.angular));
