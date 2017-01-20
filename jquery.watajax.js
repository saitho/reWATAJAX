(function($) {
    $.fn.extend({
        watajax: function(options) {
            var defaults = {
                page: 1,
                per_page: 30,
                pager_max_buttons: 5,
                ajax_connector: '/ajax.php',
                table_id: 1,
                sort_order: "DESC",
                search: "",
                search_timeout: 300,
                tools_position: "under",
                table_class: "watajax_table",
                search_text: "Search...",
                search_results: "%1 results found."
            };
            options = $.extend(defaults, options);
            var table = $(this);
            var dataOptions = {};
            var searchTimeout = null;

            /**
             * Methods
             */
            this.loadFromConnector = function(section, args, callback) {
                var _this = this;
                if(args == undefined) {
                    args = "";
                }
                $('#'+table.attr('id')+"_table_loading").show();

                if (section == "body") {
                    args += "watajax_page="+options.page+"&";
                    args += "watajax_per_page="+options.per_page+"&";

                    if (options.sortBy) {
                        args += "watajax_sortBy="+options.sortBy+"&";
                        args += "watajax_sortOrder="+options.sort_order+"&";
                    }
                    if (options.search != "") {
                        args += "watajax_search="+options.search+"&";
                    }
                }
                $.ajax({
                    url:	options.ajax_connector+"?action=watajax_load_"+section+"&watajax_table="+options.table_id+"&"+args,
                    dataType: "json",
                    success: function(msg, stat){
                        console.log('load '+section+' from connector');
                        if (stat == "success") {
                            $('#'+table.attr('id')+"_table_loading").hide();
                            $('#'+table.attr('id')+"_table_"+section).html(msg.html);
                            if (section == "head") {
                                _this.activateHeader();
                            } else if (section == "body") {
                                dataOptions = msg.dataOptions;
                                $('#'+table.attr('id')+"_table_body tr:odd").addClass('zebra');
                                $('#'+table.attr('id')+"_table_body tr").hover(
                                    function() { $(this).addClass("highlight") },
                                    function() { $(this).removeClass("highlight") }
                                );
                                _this.updatePager();
                            }
                            if(callback != undefined) {
                                callback();
                            }
                        }
                    }}
                );
            };

            this.activateHeader = function () {
                var _this = this;
                $('#'+table.attr('id')+"_table_head tr th").each(function () {
                    $(this).click(function () {
                        _this.sortBy($(this).attr('id'));
                        $('#'+table.attr('id')+"_table_head tr th").removeClass('header_sorting');
                        $(this).addClass('header_sorting');
                    })
                });
                _this.createSearch();
            };

            this.sortBy = function (column) {
                options.sort_order = (options.sort_order == "ASC") ? "DESC" : "ASC";
                options.sortBy = column;
                this.loadFromConnector('body');
            };

            this.gotoPage = function (page) {
                options.page = page;
                this.updatePager();
                this.loadFromConnector('body');
            };

            this.search = function (string) {
                options.page = 1;
                options.search = string;
                this.loadFromConnector('body');
            };

            this.searchKeyUp = function (string) {
                var _this = this;
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(function () {
                    _this.search(string);
                }, options.search_timeout);
            };

            this.format = function(str, arr) {
                return str.replace(/%(\d+)/g, function(_,m) {
                    return arr[--m];
                });
            };

            // altered createSearch: replaced Swedish with English text
            this.createSearch = function () {
                var _this = this;
                var result_counter = document.createElement('span');
                result_counter.style.cssText = "float: right";
                result_counter.id = 'watajax_resultcounter';
                $('#'+table.attr('id')+"_table_search").append(result_counter);

                var search_input = document.createElement('input');
                search_input.type = "text";
                search_input.id = "search_input";
                search_input.style.cssText = "float: left";
                search_input.placeholder = options.search_text;
                $(search_input).keyup(function () {
                    _this.searchKeyUp($(this).val());
                });

                $('#'+table.attr('id')+"_table_search").append(search_input);
            };

            // altered pager: adds Pagination from bootstrap
            this.updatePager = function () {
                $('span#watajax_resultcounter').text(this.format(options.search_results, [dataOptions.results]));

                var _this = this;
                var $pagination = $("#"+table.attr('id')+"_table_pagination");
                $pagination.empty(); // Empty pager first
                if (dataOptions.pages > 1) {
                    var pagination_container = document.createElement("ul");
                    pagination_container.className = "pagination";

                    /**
                     * Check if we need to show the first page button
                     */
                    if (options.page >= options.pager_max_buttons) {
                        var pagination_element = document.createElement("li");
                        pagination_element.innerHTML = 1;
                        pagination_element.className = "page_button";
                        $(pagination_element).click(function () { _this.gotoPage(1);});
                        pagination_container.appendChild(pagination_element);

                        pagination_element = document.createElement("li");
                        pagination_element.innerHTML = "...";
                        pagination_element.className = "page_button page_dots";
                        pagination_container.appendChild(pagination_element);
                    }

                    /**
                     * Render page buttons
                     */
                    var i_start = Math.floor(options.page - (options.pager_max_buttons/2))-1;
                    var i_end = i_start + options.pager_max_buttons+2;

                    i_end = (i_end > dataOptions.pages) ? dataOptions.pages : i_end;
                    i_start = (i_start < 0) ? 0 : i_start;

                    for(var i=i_start; i<i_end; i++) {
                        pagination_element = document.createElement("li");
                        pagination_element.innerHTML = (i+1);
                        pagination_element.className = "page_button";
                        if (options.page == (i+1)) {
                            pagination_element.className = "page_button current_page";
                        }
                        $(pagination_element).click(function () {
                            var page_ref = $(this).html();
                            _this.gotoPage(page_ref);
                        });
                        pagination_container.appendChild(pagination_element);
                    }

                    /**
                     * Check if we need to show the last page button
                     */
                    if (options.page < Math.floor(dataOptions.pages-options.pager_max_buttons/2)) {
                        pagination_element = document.createElement("li");
                        pagination_element.innerHTML = "...";
                        pagination_element.className = "page_button page_dots";
                        pagination_container.appendChild(pagination_element);

                        pagination_element = document.createElement("li");
                        pagination_element.innerHTML = dataOptions.pages;
                        pagination_element.className = "page_button";
                        $(pagination_element).click(function () { _this.gotoPage(dataOptions.pages);});
                        pagination_container.appendChild(pagination_element);
                    }
                    $pagination.html(pagination_container);
                }
            };

            this.initBody = function() {
                var _this = this;
                this.loadFromConnector('body', '', function() {
                    $(document).keydown(function( event ) {
                        var tag = event.target.tagName.toLowerCase();
                        if(tag != "input") {
                            if(event.which == 37) { //37 - back
                                if(options.page > 1) {
                                    _this.gotoPage(Number(options.page)-1);
                                }
                            }else if(event.which == 39) { // 39 - forward
                                if(options.page < dataOptions.pages) {
                                    _this.gotoPage(Number(options.page)+1);
                                }
                            }
                        }
                    });
                });
            };

            this.init = function() {
                table.html(
                    "<table class='"+options.table_class+"' cellspacing='0' id='"+table.attr('id')+"_table'>" +
                    "<thead id='"+table.attr('id')+"_table_head'></thead>" +
                    "<tbody id='"+table.attr('id')+"_table_body'></tbody>" +
                    "</table>");
                var tools = "<div id='"+table.attr('id')+"_table_search' class='table_search'></div>" +
                    "<div id='"+table.attr('id')+"_table_loading' class='ajax_loading'></div>";
                var pagination_container = "<div id='"+table.attr('id')+"_table_pagination' class='pagination_container'></div>";

                table.html(tools+table.html()+pagination_container);
                $('#'+table.attr('id')+"_table_loading").hide();
                this.loadFromConnector('head', '', this.initBody());
            };

            this.init();
        }
    });
})(jQuery);