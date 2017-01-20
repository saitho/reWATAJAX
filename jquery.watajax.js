(function($) {
	var Watajax = function(element, options) {

		var defaults = {
			page: 1,
			per_page: 30,
			pager_max_buttons: 5,
			ajax_connector: '/ajax.php',
			table_id: 1,
			sort_order: "DESC",
			search: "",
			search_timeout: 300,
			disable_search: false,
			tools_position: "under",
			table_class: "watajax_table",
			use_cookies: false,
			callback: null, // Runs after body is loaded
			after_settings: null, // Runs after settings is loaded
			after_table_change: null, // Runs after tbody is updated
			cookie_life: 12, // Days
			filters: {},
			lang: {
				could_not_load_settings : "Could not load WATAJAX",
				all: "All",
				no_rows_found: "No rows could be found",
				rows: "rows",
				page: "Page",
				search: "Find",
				export_csv: "Export to file",
				more_rows: "Show more rows..",
				all_rows: "Show all rows..",
				search_in: "in column",
				reset_search: "Reset search"
			},
			enable_export: true,
			export_format: "csv", // other possibility: "xls"
			ajax_loading_text: "",
			enable_keyboard: false,
			enable_header_sorting: true,
			enable_more_rows_button: true,
			enable_all_rows_button: false,
			fuzzy_search: true,
			contains_search: false,
			min_search_length: 3,
			jump_on_pagechange: "none", // "page_top", "none"
			zebra: "odd",
			row_tag: "tr",
			column_tag: "td",
			header_column_tag: "th",
			table_tag: "table",
			thead_tag: "thead",
			tbody_tag: "tbody",
			filter_tag: "tr",
			filter_column_tag: "td",
			wrap_tools: false,
			default_filter: null,
			limit_search: true, // This will limit search to one column at the time
			search_column: null
		};
		var options = $.extend(defaults, options);
		var table = $(element);
		var dataOptions = {};
		var searchTimeout = null;
		this.num_rows = 0;
		this.hasActiveFilter = false;
		this.hasActiveSearch = true;


		/**
		 * Methods
		 */
		this.changeConnector = function(connector) {
			if(connector == undefined){
				connector = options.ajax_connector
			}
			options.ajax_connector = connector;
			this.loadFromConnector('head');
			this.loadFromConnector('body');
			this.loadSettingsFromConnector();
		}

		this.loadFromConnector = function(section, args) {
			var _this = this;
			var args = (args == undefined) ? "" : args;
			//$('#'+table.attr('id')+"_table_loading").show();
			this.showLoading();

			if (section == "body" || section == "csv" || section == "xls") {
				args += "watajax_page="+options.page+"&";
				args += "watajax_per_page="+options.per_page+"&";

				if (options.sortBy) {
					args += "watajax_sortBy="+options.sortBy+"&";
					args += "watajax_sortOrder="+options.sort_order+"&";
				}
				if (options.search != "") {
					args += "watajax_search="+encodeURIComponent(options.search)+"&";
				}
				if(options.limit_search == true) {
					if($("#"+table.attr('id')+"_table_search_column").val() == undefined && options.use_cookies == true && options.search_column != null) {
						args += "watajax_searchColumn="+options.search_column+"&";
					} else {
						args += "watajax_searchColumn="+$("#"+table.attr('id')+"_table_search_column").val()+"&";
					}
				}
				var filters = this.objectToQuery(options.filters);
				if (filters != '') {
					args += "watajax_filter="+filters+"&";
				}
				if(section == "csv" || section == "xls") {
					if(options.data != undefined && options.data.key_column != undefined && options.data.key_column != "" && options.data.owner_id != undefined && options.data.owner_id != undefined != "") {
						args += "where="+options.data.key_column+"="+options.data.owner_id+"&";
					}
				}
			}
			args += "fuzzy_search=" + options.fuzzy_search + '&contains_search=' + options.contains_search + '&';

			var q = (options.ajax_connector.charAt(options.ajax_connector.length-1) == "?" || options.ajax_connector.charAt(options.ajax_connector.length-1) == "&") ? "" : "?";
			var url = options.ajax_connector+q+"action=watajax_load_"+section+"&watajax_table="+options.table_id+"&"+args;
			var data = options.data;
			if (section == "csv" || section == "xls") {
				document.location = url;
				//$('#'+table.attr('id')+"_table_loading").hide();
				this.hideLoading();
				return true;
			}
			$.ajax({
				url: url,
				dataType: "html",
				type: "post",
				data: {
					params: data
				},
				success: function(msg, stat){
					if (stat == "success") {
						//$('#'+table.attr('id')+"_table_loading").hide();
						_this.hideLoading();
						if(msg != 'no_rows_found') {
							$('#'+table.attr('id')+"_table_"+section).html(msg);
						} else {
							$('#'+table.attr('id')+"_table_"+section).html('<tr><td colspan="'+$('#'+table.attr('id')+"_table_head "+options.row_tag+" "+options.header_column_tag).length +'">'+options.lang.no_rows_found+'</td></tr>');
						}
						if (section == "head") {
							_this.activateHeader();
							_this.loadSettingsFromConnector();
						} else if (section == "body") {
							/* Adding markings to the sorted column */
							$('#'+table.attr('id')+"_table_head "+options.row_tag+" "+options.header_column_tag).removeClass('header_sorting');
							$('#'+table.attr('id')+"_table_head "+options.row_tag+" "+options.header_column_tag).removeClass('sort_asc');
							$('#'+table.attr('id')+"_table_head "+options.row_tag+" "+options.header_column_tag).removeClass('sort_desc');
							$('#'+table.attr('id')+"_table_head "+options.row_tag+" "+options.header_column_tag).removeClass('sort_ASC');
							$('#'+table.attr('id')+"_table_head "+options.row_tag+" "+options.header_column_tag).removeClass('sort_DESC');

							$('#'+table.attr('id')+"_table_head "+options.row_tag+" "+options.header_column_tag+"#"+options.sortBy).addClass('header_sorting');
							$('#'+table.attr('id')+"_table_head "+options.row_tag+" "+options.header_column_tag+"#"+options.sortBy).addClass('sort_'+options.sort_order);

							$('#'+table.attr('id')+"_table_body "+options.row_tag+":"+options.zebra).addClass('zebra');
							$('#'+table.attr('id')+"_table_body "+options.row_tag).hover(
								function() {
									$(this).addClass("highlight")
									},
								function() {
									$(this).removeClass("highlight")
									}
								)
								if (options.after_table_change != null) { options.after_table_change(_this);} // This is being run twice if outside of body section if
							}
							if (options.callback != null) {options.callback(_this);}
					}
				}
			}
			);
		}

		this.loadSettingsFromConnector = function (args, create) {
			var _this = this;
			var create = (create == undefined) ? "all" : create;

			//$('#'+table.attr('id')+"_table_loading").show();
			this.showLoading();

			if (args == undefined) {
				args = new String();
			} else {
				args += "&";
			}
			args += "watajax_page="+options.page+"&";
			args += "watajax_per_page="+options.per_page+"&";
			var filters = this.objectToQuery(options.filters);

			if (filters != '') {
				args += "watajax_filter="+encodeURIComponent(filters)+"&";
			}
			if (options.search != '') {
				args += "watajax_search="+encodeURIComponent(options.search)+"&";
			}

			args += "fuzzy_search=" + options.fuzzy_search + '&contains_search=' + options.contains_search + '&';
			var q = (options.ajax_connector.charAt(options.ajax_connector.length-1) == "?" || options.ajax_connector.charAt(options.ajax_connector.length-1) == "&") ? "" : "?";
			var data = options.data;
			$.ajax({
				url:	options.ajax_connector+q+"action=watajax_load_settings&watajax_table="+options.table_id+"&"+args,
				dataType: "json",
				type: "post",
				data: {
				      params: data
				},
				success: function(msg, stat){
					if (stat == "success") {
						//$('#'+table.attr('id')+"_table_loading").hide();
						_this.hideLoading();

						dataOptions = msg;
						_this.num_rows = dataOptions.items;
						if(create == "all" || create == "pager") {
							_this.createPager();
						}
						if(options.tools_position != "none" && (create == "all" || create == "search")) {
							_this.createSearch();
						}
						if(create == "all" || create == "filter") {
							_this.createFilter();
						}
						if (options.after_settings != null) {options.after_settings(_this);}
					} else {
						alert(options.lang.could_not_load_settings);
					}
				}
			}
			)
		}

		this.activateHeader = function () {
			if(options.enable_header_sorting == true) {
				var _this = this;
				$('#'+table.attr('id')+"_table_head "+options.row_tag+" "+options.header_column_tag).each(function () {
					if (!$(this).hasClass('disable_sorting')) {
						$(this).click(function () {
							_this.sortBy($(this).attr('id'));
						})
					}
				})
			}
		}

		this.sortBy = function (column, order) {
			if (order == undefined) {
				options.sort_order = (options.sort_order == "ASC") ? "DESC" : "ASC";
			} else {
				options.sort_order = order;
			}
			options.sortBy = column;
			this.loadFromConnector('body');
			this.saveSettings();
		}

		this.gotoNextPage = function() {
			if(options.page < dataOptions.pages) {
				this.gotoPage((options.page*1)+1);
				return false;
			} else {
				return false;
			}
		}

		this.gotoPreviousPage = function() {
			if (options.page == 1) {
				return false;
			} else {
				this.gotoPage(options.page-1);
				return false;
			}
		}

		this.gotoPage = function (page) {
			options.page = page;
			this.createPager();
			this.loadFromConnector('body');
			this.saveSettings();
			if(options.jump_on_pagechange == "table_top") {
				document.location = "#"+options.table_id+"_scroll_top";
			} else if(options.jump_on_pagechange == "page_top") {
				window.scrollTo(0,0);
			}
		}

		this.refresh = function() {
			this.init();
		}

		this.refreshTableBody = function() {
			this.loadFromConnector('body');
		}

		this.search = function (string) {
			if(string.length >= options.min_search_length || options.search.length > 0) {
				$('#'+table.attr('id')+"_table_search .watajax_search_reset").show();
				options.page = 1;
				options.search = string;
				this.loadSettingsFromConnector('', 'pager');
				this.loadFromConnector('head');
				this.loadFromConnector('body');
				this.saveSettings();
				this.hasActiveSearch = true;
			}
			if(string.length === 0 && this.hasActiveFilter == false) {
				$('#'+table.attr('id')+"_table_search .watajax_search_reset").hide();
				this.hasActiveSearch = false;
			}
		}

		this.searchKeyUp = function (string) {
			var _this = this;
			clearTimeout(searchTimeout);
			searchTimeout = setTimeout(function () {
				_this.search(string);
			}, options.search_timeout);
		}

		this.setPerPage = function(num) {
			options.per_page = num;
			this.loadFromConnector('body');
			this.loadSettingsFromConnector();
			this.saveSettings();
		}

		this.resetFilter = function () {
			options.filters = {};
		}

		this.objectToQuery = function (obj) {
			var query = '';
			for (var i in obj) {
				query += i+':'+obj[i]+'|';
			}
			return query.substr(0, (query.length-1));
		}

		this.applyFilter = function (column, filter_value) {
			if (filter_value == '') {
				delete options.filters[column];
				this.hasActiveFilter = false;
				if(this.hasActiveSearch == false) {
					$('#'+table.attr('id')+"_table_search .watajax_search_reset").hide();
				}
			} else {
				this.hasActiveFilter = true;
				options.filters[column] = encodeURIComponent(filter_value);
				$('#'+table.attr('id')+"_table_search .watajax_search_reset").show();
			}

			options.page = 1;
			this.loadSettingsFromConnector();
			this.loadFromConnector('body');
			this.saveSettings();
		}

		this.createFilter = function () {
			var _this = this;
			if (dataOptions.filters != undefined && dataOptions.filters.watajax_has_filters == true) {
				// First create filter row in thead (if not there)
				if ($('#'+table.attr('id')+"_table_filter").html() == null) {
					var filter = document.createElement(options.filter_tag);
					filter.id = table.attr('id')+"_table_filter";
					filter.className = 'filter';
					$('#'+table.attr('id')+"_table_head").append(filter);
				} else {
					$('#'+table.attr('id')+"_table_filter").html(''); // Empty filters
				}

				$('#'+table.attr('id')+"_table_head "+options.row_tag+" "+options.header_column_tag).each(function() {
					var column = $(this).attr('id');

					var filter_html = $("<"+options.filter_column_tag+">");
					var filter_obj = dataOptions.filters[column];

					if (filter_obj == undefined) {
						filter_html.html('&nbsp;');
					} else if(filter_obj.type == "select") {
						var select_contents = '';
						var select_box = $('<select>');
						select_box.attr('id', column+'_filter');
						select_contents = '<option value="">- '+options.lang.all+'</option>';
                        if(typeof(filter_obj.order) === "object" && filter_obj.order.length) {
                            for(var j=0; j<filter_obj.order.length; j++) {
                                var i = filter_obj.order[j];
                                if (filter_obj.contents[i] != "") {
                                    if(filter_obj.contents_override == true) {
                                        var selected = (options.filters[column] != undefined && options.filters[column] == encodeURIComponent(i)) ? ' selected="selected"' : '';
                                        select_contents += '<option'+selected+' value="'+i+'">'+filter_obj.contents[i]+'</option>';
                                    } else {
                                        var selected = (options.filters[column] != undefined && options.filters[column] == encodeURIComponent(filter_obj.contents[i])) ? ' selected="selected"' : '';
                                        select_contents += '<option'+selected+' value="'+filter_obj.contents[i]+'">'+filter_obj.contents[i]+'</option>';
                                    }

                                }
                            }
                        } else {
                            for(var i in filter_obj.contents) {
                                if (filter_obj.contents[i] != "") {
                                    if(filter_obj.contents_override == true) {
                                        var selected = (options.filters[column] != undefined && options.filters[column] == encodeURIComponent(i)) ? ' selected="selected"' : '';
                                        select_contents += '<option'+selected+' value="'+i+'">'+filter_obj.contents[i]+'</option>';
                                    } else {
                                        var selected = (options.filters[column] != undefined && options.filters[column] == encodeURIComponent(filter_obj.contents[i])) ? ' selected="selected"' : '';
                                        select_contents += '<option'+selected+' value="'+filter_obj.contents[i]+'">'+filter_obj.contents[i]+'</option>';
                                    }

                                }
                            }
                        }

						select_box.html(select_contents);
						select_box.change(function () {
							_this.applyFilter($(this).attr('id').replace('_filter', ''), $(this).val());
						});
						filter_html.append(select_box);
					} else if (filter_obj.type == "text") {
						filter_html.html('- text not implemented -');
					}
					$('#'+table.attr('id')+"_table_filter").append(filter_html);
				})

			}
		}

		this.createSearch = function () {
			if(options.disable_search != true) {
				var _this = this;
				if($('#'+table.attr('id')+"_table_search #search_input").length == 0) {
					var search_title = document.createElement('div');
					search_title.className = "search_title";
					search_title.innerHTML = options.lang.search+":";

					var search_input = document.createElement('input');
					search_input.value = options.search;
					search_input.type = "text";
					search_input.id = "search_input";
					$(search_input).keyup(function () {
						_this.searchKeyUp($(this).val());
					});

					var search_reset = document.createElement("a");
					search_reset.className = "watajax_search_reset btn button btn-default";
					search_reset.href = "#";
					search_reset.style.display = "none";
					if(options.lang.reset_search == undefined) { options.lang.reset_search = "Reset search"; }
					search_reset.innerHTML = "<i class='icon icon-trash'></i> "+options.lang.reset_search;
					$(search_reset).click(function () {
						_this.clearFiltersAndSearch();
					});


					$('#'+table.attr('id')+"_table_search").html('');
					$('#'+table.attr('id')+"_table_search").append(search_reset);
					$('#'+table.attr('id')+"_table_search").append(search_title);
					$('#'+table.attr('id')+"_table_search").append(search_input);

					if(options.limit_search == true) {
						var search_columns_input = document.createElement('select');
						search_columns_input.value = options.search_column;
						search_columns_input.id = table.attr('id')+"_table_search_column";
						$(search_columns_input).change(function() {
							_this.onLimitSearchChange($(this).val());
						});
						//var search_columns = options.lang.search_in+" <select class='watajax_search_column_chooser' id='"+table.attr('id')+"_table_search_column'>";
						for(var i in dataOptions.search_columns) {
							search_column_option = document.createElement('option');
							search_column_option.value = i;
							if(options.search_column == i) {
								search_column_option.selected = true;
							}
							search_column_option.innerHTML = dataOptions.search_columns[i];
							search_columns_input.appendChild(search_column_option);
							//search_columns += "<option value='"+i+"'>"+dataOptions.search_columns[i]+"</option>";
						}
						if(i == undefined) { // Seems like we don't have anything to search in, hiding search
							$('#'+table.attr('id')+"_table_search").hide();
						} else {
							$('#'+table.attr('id')+"_table_search").show();
						}
						$('#'+table.attr('id')+"_table_search").append(options.lang.search_in+" ");
						$('#'+table.attr('id')+"_table_search").append(search_columns_input);
					}
				} else {
					$('#'+table.attr('id')+"_table_search #search_input").keyup(function () {
						_this.searchKeyUp($(this).val());
					});
				}
			}
		}

		this.clearFiltersAndSearch = function() {
			this.resetFilter();
			$('#'+table.attr('id')+"_table_search #search_input").val('');
			$('#'+table.attr('id')+"_table_search .watajax_search_reset").hide();
			options.page = 1;
			options.search = "";
			this.loadSettingsFromConnector('', 'pager');
			this.loadFromConnector('head');
			this.loadFromConnector('body');
			this.saveSettings();
		}

		this.onLimitSearchChange = function(value) {
			options.search_column = value;
			this.search(options.search);
		}

		this.createPager = function () {
			var _this = this;
			$('#'+table.attr('id')+"_table_pager").html(''); // Empty pager first
			if (dataOptions.pages > 1) {
				var pager_title = document.createElement('div');
				pager_title.innerHTML = options.lang.page+' ';
				pager_title.className = 'pager_title';
				$('#'+table.attr('id')+"_table_pager").append(pager_title);

				/**
				 * Check if we need to show the first page button
				 */
				if (options.page > ((options.pager_max_buttons-1)/2)+1) {
					var pager_button = document.createElement('div');
					pager_button.className = "page_button";
					pager_button.innerHTML = 1;
					$(pager_button).click(function () {
						_this.gotoPage(1);
					});
					$('#'+table.attr('id')+"_table_pager").append(pager_button);
					var pager_button = document.createElement('div');
					pager_button.className = "page_button page_dots";
					pager_button.innerHTML = "...";
					$('#'+table.attr('id')+"_table_pager").append(pager_button);
				}

				/**
				 * Render page buttons
				 */
				var i_start = (options.page - ((options.pager_max_buttons-1)/2))-1;
				var i_end = i_start + options.pager_max_buttons;
				i_end = (i_end > dataOptions.pages) ? dataOptions.pages : i_end;
				i_start = (i_start < 0) ? 0 : i_start;

				for(var i=i_start; i<i_end; i++) {
					var pager_button = document.createElement('div');
					pager_button.className = "page_button";
					pager_button.innerHTML = (i+1);
					if (options.page == (i+1)) {
						pager_button.className = "page_button current_page";
					}
					$(pager_button).click(function () {
						var page_ref = $(this).html();
						_this.gotoPage(page_ref);
					});
					$('#'+table.attr('id')+"_table_pager").append(pager_button);
				}

				/**
				 * Check if we need to show the last page button
				 */
				if (options.page < (dataOptions.pages-(((options.pager_max_buttons-1)/2)))) {
					var pager_button = document.createElement('div');
					pager_button.className = "page_button page_dots";
					pager_button.innerHTML = "...";
					$('#'+table.attr('id')+"_table_pager").append(pager_button);
					var pager_button = document.createElement('div');
					pager_button.className = "page_button";
					pager_button.innerHTML = dataOptions.pages;
					$(pager_button).click(function () {
						_this.gotoPage(dataOptions.pages);
					});
					$('#'+table.attr('id')+"_table_pager").append(pager_button);
				}
				$('#'+table.attr('id')+"_table_pager").append("<span class='number_items'>"+dataOptions.items+" "+options.lang.rows+"</span>");
				if (options.enable_more_rows_button == true && options.page != dataOptions.pages) {
					var Ref = this;
					if(!$('div#'+table.attr('id')+'_more_rows_button').length) {
						table.append('<div id="'+table.attr('id')+'_more_rows_button" class="more_rows_button btn btn-default" title="'+options.lang.more_rows+'"><span>'+options.lang.more_rows+'</span></div>');
						$("#"+table.attr('id')+"_more_rows_button").click(function() {
							options.per_page = (options.per_page*1) + 10;
							Ref.loadFromConnector('head');
							Ref.loadFromConnector('body');
						});
					}
				} else {
					// Remove "More rows"
					if($('div#'+table.attr('id')+'_more_rows_button').length) {
						$('div#'+table.attr('id')+'_more_rows_button').remove();
					}
				}
				if((options.enable_all_rows_button == "true" || options.enable_all_rows_button == true) && options.page != dataOptions.pages) {
					var Ref = this;
					if(!$('div#'+table.attr('id')+'_all_rows_button').length) {
						table.append('<div id="'+table.attr('id')+'_all_rows_button" onclick="document.getElementById(\''+table.attr('id')+'_all_rows_button\').style.display=\'none\'" class="all_rows_button btn btn-default" title="'+options.lang.all_rows+'"><span>'+options.lang.all_rows+'</span></div>');
						$("#"+table.attr('id')+"_all_rows_button").click(function() {
							options.per_page = (options.per_page*1) + 500000;
							Ref.loadFromConnector('head');
							Ref.loadFromConnector('body');
						});
					}
				}
			} else {
				// Remove "More rows"
				if($('div#'+table.attr('id')+'_more_rows_button').length) {
					$('div#'+table.attr('id')+'_more_rows_button').remove();
				}
				if (!$('#'+table.attr('id')+"_table_pager span.number_items").length) {
					$('#'+table.attr('id')+"_table_pager").append("<span class='number_items'>"+dataOptions.items+" "+options.lang.rows+"</span>");
				}
			}
		}

		/**
		 * Cookie settings handling
		 */
		this.createCookie = function(name,value,days) {
			if (days) {
				var date = new Date();
				date.setTime(date.getTime()+(days*24*60*60*1000));
				var expires = "; expires="+date.toGMTString();
			} else {
				var expires = "";
			}
			document.cookie = name+"="+value+expires+"; path=/";
		}

		this.readCookie = function(name) {
			var nameEQ = name + "=";
			var ca = document.cookie.split(';');
			for(var i=0;i < ca.length;i++) {
				var c = ca[i];
				while (c.charAt(0)==' ') c = c.substring(1,c.length);
				if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);
			}
			return null;
		}

		this.eraseCookie = function(name) {
			this.createCookie(name,"",-1);
		}

		this.saveSettings = function () {
			if (options.use_cookies == false) {
				return false;
			}
			/* Saving settings */
			this.createCookie('watajax_'+table.attr('id')+'_page',              options.page, options.cookie_life);
			this.createCookie('watajax_'+table.attr('id')+'_per_page',          options.per_page, options.cookie_life);
			this.createCookie('watajax_'+table.attr('id')+'_ajax_connector',    options.ajax_connector, options.cookie_life);
			this.createCookie('watajax_'+table.attr('id')+'_sort_order',        options.sort_order, options.cookie_life);
			this.createCookie('watajax_'+table.attr('id')+'_searchColumn',        $("#"+table.attr('id')+"_table_search_column").val(), options.cookie_life);
			if (options.search != undefined && options.search != "") {
				this.createCookie('watajax_'+table.attr('id')+'_search',        options.search, options.cookie_life);
			} else {
				this.eraseCookie('watajax_'+table.attr('id')+'_search');
			}
			if (options.sortBy != undefined) {
				this.createCookie('watajax_'+table.attr('id')+'_sortBy',            options.sortBy, options.cookie_life);
			} else {
				this.eraseCookie('watajax_'+table.attr('id')+'_sortBy');
			}
			// Saving filters
			var added_filters = 0;
			var save_filters = "";
			for (var i in options.filters) {
				added_filters++;
				save_filters += i+":"+options.filters[i]+"&";
			}
			if (added_filters <= 0) {
				this.eraseCookie('watajax_'+table.attr('id')+'_filters');
			} else {
				this.createCookie('watajax_'+table.attr('id')+'_filters', save_filters.slice(0, -1), options.cookie_life);
			}
		}

		this.clearSettings = function () {
			this.eraseCookie('watajax_'+table.attr('id')+"_page");
			this.eraseCookie('watajax_'+table.attr('id')+"_per_page");
			this.eraseCookie('watajax_'+table.attr('id')+"_ajax_connector");
			this.eraseCookie('watajax_'+table.attr('id')+"_sort_order");
			this.eraseCookie('watajax_'+table.attr('id')+"_search");
			this.eraseCookie('watajax_'+table.attr('id')+"_sortBy");
			this.eraseCookie('watajax_'+table.attr('id')+"_filters");
			this.eraseCookie('watajax_'+table.attr('id')+"_searchColumn");
		}

		this.loadSettings = function () {
			if (options.use_cookies == false) {
				return false;
			}
			var cookie_page = this.readCookie('watajax_'+table.attr('id')+'_page');
			var cookie_per_page = this.readCookie('watajax_'+table.attr('id')+'_per_page');
			var cookie_ajax_connector = this.readCookie('watajax_'+table.attr('id')+'_ajax_connector');
			var cookie_sort_order = this.readCookie('watajax_'+table.attr('id')+'_sort_order');
			var cookie_search = this.readCookie('watajax_'+table.attr('id')+'_search');
			var cookie_sortBy = this.readCookie('watajax_'+table.attr('id')+'_sortBy');
			var cookie_filters = this.readCookie('watajax_'+table.attr('id')+'_filters');
			var cookie_searchColumn = this.readCookie('watajax_'+table.attr('id')+'_searchColumn');

			if (cookie_page != undefined) {
				options.page = cookie_page;
			}
			if (cookie_per_page != undefined) {
				options.per_page = cookie_per_page;
			}
			if (cookie_ajax_connector != undefined) {
				options.ajax_connector = cookie_ajax_connector;
			}
			if (cookie_sort_order != undefined) {
				options.sort_order = cookie_sort_order;
			}
			if (cookie_search != undefined && cookie_search != null) {
				options.search = cookie_search;
			}
			if (cookie_sortBy != undefined) {
				options.sortBy = cookie_sortBy;
			}
			if(cookie_searchColumn != undefined) {
				options.search_column = cookie_searchColumn;
			}

			// Read filters
			if (cookie_filters != undefined) {
				var cookie_filters_array = cookie_filters.split("&");
				for(var i in cookie_filters_array) {
					var column = cookie_filters_array[i].split(":");
					options.filters[column[0]] = column[1];
				}
			}

		}

		/**
                 * Init
                 */
		this.init = function() {
			var Ref = this;
			if(options.default_filter != null) {
				options.filters = options.default_filter;
			}

			this.loadFiltersFromGet(options);

			this.loadSettings();
			table.html(
				"<a name='"+options.table_id+"_scroll_top"+"'></a>" +
				"<"+options.table_tag+" class='"+options.table_class+"' cellspacing='0' id='"+table.attr('id')+"_table'>" +
				"<"+options.thead_tag+" id='"+table.attr('id')+"_table_head'></"+options.thead_tag+">" +
				"<"+options.tbody_tag+" id='"+table.attr('id')+"_table_body'></"+options.tbody_tag+">" +
				"</"+options.table_tag+">");

			if(options.ajax_loading_text != ''){
				var ajax_loading_text = "<span>" + options.ajax_loading_text + "</span>";
			}else{
				var ajax_loading_text = "";
			}

			if(options.wrap_tools) {
				wrapper_start = "<div class='watajax_tools_wrapper'>";
				wrapper_end = "</div>";
			} else {
				wrapper_start = "";
				wrapper_end = "";
			}


			var tools = wrapper_start+"<div id='"+table.attr('id')+"_table_pager' class='table_pager'></div>" +
			"<div id='"+table.attr('id')+"_table_search' class='table_search'></div>" + wrapper_end +
			"<div id='"+table.attr('id')+"_table_loading' class='ajax_loading'>" + ajax_loading_text + "</div>";

			if (options.tools_position == "both") { // TODO: Not done yet
				table.html(tools+table.html()+tools)
			} else if (options.tools_position == "above") {
				table.html(tools+table.html())
			} else {
				table.html(table.html()+tools)
			}

			if (options.enable_export == true) {
				table.html(table.html()+'<div id="'+table.attr('id')+'_export_file" class="export_file" title="'+options.lang.export_csv+'"><span>'+options.lang.export_csv+'</span></div>');
				$("#"+table.attr('id')+"_export_file").click(function() {
					Ref.loadFromConnector(options.export_format);
				});
			}

			//$('#'+table.attr('id')+"_table_loading").hide();
			this.hideLoading();

			this.loadFromConnector('head');
			this.loadFromConnector('body');

			if (options.enable_keyboard) {
				$(document).keydown(function(e){
					if (e.keyCode == 37) {
						Ref.gotoPreviousPage();
						return false;
					} else if (e.keyCode == 39) {
						Ref.gotoNextPage();
						return false;
					}
				});
			}
		}

		this.showLoading = function() {
			if($('#'+table.attr('id')+"_table").block != undefined) {
				$('#'+table.attr('id')+"_table").block({ message: null, overlayCSS: { backgroundColor: "#FFF" } });
			}
			$('#'+table.attr('id')+"_table_loading").show();
			if($('.ajax_loading').length){
				$('.ajax_loading').show();
			}
		}

		this.hideLoading = function() {
			if($('.ajax_loading').length){
				$('.ajax_loading').hide();
			}
			$('#'+table.attr('id')+"_table_loading").hide();
			if($('#'+table.attr('id')+"_table").unblock != undefined) {
				$('#'+table.attr('id')+"_table").unblock();
			}
		}

		this.loadFiltersFromGet = function(options) {
			var vars = [], hash;
			var q = document.URL.split('?')[1];
			if(q != undefined){
				q = q.split('&');
				for(var i = 0; i < q.length; i++){
					hash = q[i].split('=');
					vars.push(hash[1]);
					vars[hash[0]] = hash[1];
				}
			}
			for(var i in vars) {
				filter_prefix = "watajax_"+table.attr('id')+"_filter";
				if(i.toString().substr(0,filter_prefix.length) == "watajax_"+table.attr('id')+"_filter") {
					field = i.toString().substr(filter_prefix.length+1, i.length);
					console.log("Setting "+"#"+field+"_filter"+" to "+vars[i]);
					options.filters[field] = vars[i];
					$("#"+field+"_filter").val(vars[i]);
					$("#"+field+"_filter").change();
				}

				if(i == "watajax_"+table.attr('id')+"_query_column") {
					options.search_column = vars[i];
					$("#"+table.attr('id')+"_table_search_column").val(vars[i]);
				}
				if(i == "watajax_"+table.attr('id')+"_query") {
					$("#"+table.attr('id')+"_table_search #search_input").val(vars[i]);
					this.searchKeyUp(vars[i]);
				}
			}
		}

		this.init();

	}
	/* Wrapper */
	$.fn.watajax = function(options) {
		return this.each(function() {
			var element = $(this);

			// Return early if this element already has a plugin instance
			if (element.data('watajax')) return;

			// pass options to plugin constructor
			var watajax = new Watajax(this, options);

			// Store plugin object in this element's data
			element.data('watajax', watajax);
		});
	};


})(jQuery);
