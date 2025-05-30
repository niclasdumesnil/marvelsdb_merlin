(function ui_deck(ui, $) {

var DisplayColumnsTpl = '',
	SortKey = 'type_code',
	SortOrder = 1,
	CardDivs = [[],[],[]],
	Config = null;

/**
 * reads ui configuration from localStorage
 * @memberOf ui
 */
ui.read_config_from_storage = function read_config_from_storage() {
	if (localStorage) {
		var stored = localStorage.getItem('ui.deck.config');
		if(stored) {
			Config = JSON.parse(stored);
		}
	}
	Config = _.extend({
		'show-unusable': false,
		'show-only-deck': false,
		'display-column': 1,
		'core-set': 2,
		'show-suggestions': 0,
		'buttons-behavior': 'exclusive'
	}, Config || {});
}

/**
 * write ui configuration to localStorage
 * @memberOf ui
 */
ui.write_config_to_storage = function write_config_to_storage() {
	if (localStorage) {
		localStorage.setItem('ui.deck.config', JSON.stringify(Config));
	}
}

/**
 * inits the state of config buttons
 * @memberOf ui
 */
ui.init_config_buttons = function init_config_buttons() {
	// radio
	['display-column', 'core-set', 'show-suggestions', 'buttons-behavior'].forEach(function (radio) {
		$('input[name='+radio+'][value='+Config[radio]+']').prop('checked', true);
	});
	// checkbox
	['show-unusable', 'show-only-deck'].forEach(function (checkbox) {
		if(Config[checkbox]) $('input[name='+checkbox+']').prop('checked', true);
	})
}

/**
 * sets the maxqty of each card
 * @memberOf ui
 */
ui.set_max_qty = function set_max_qty() {
	app.data.cards.find().forEach(function(record) {
		var max_qty = Math.min(3, record.deck_limit);
		app.data.cards.updateById(record.code, {
			maxqty : max_qty
		});
	});

}

/**
 * builds the faction selector
 * @memberOf ui
 */
ui.build_faction_selector = function build_faction_selector() {
	//app.deck.choices.push({'faction_select':["guardian","seeker"]});

	$('[data-filter=faction_selector]').hide();

	$('[data-filter=faction_selector]').empty();
	if (app.deck.choices && app.deck.choices.length > 0){
		$('[data-filter=faction_selector]').show();
		for (var i = 0; i < app.deck.choices.length; i++){
			var choice = app.deck.choices[i];
			if (choice.faction_select) {
				choice.faction_select.forEach(function(faction_code){
					var example = app.data.cards.find({"faction_code": faction_code})[0];
					var label = $('<option value="' + faction_code + '" title="'+example.faction_name+'"><span class="icon-' + faction_code + '"></span> ' + example.faction_name + '</option>');
					//label.tooltip({container: 'body'});
					$('[data-filter=faction_selector]').append(label);
				});
			}
		}
	}

	if (app.deck && app.deck.requirements && app.deck.requirements.aspects) {
		if (app.deck.requirements.aspects == 4) {
			$('#aspect_selector').hide();
			$('#aspect2_selector').hide();
		} else if (app.deck.requirements.aspects == 2) {
			$('#aspect2_selector').show();
		}
	} else {
		$('#aspect2_selector').hide();
	}

	$('[data-filter=faction_code]').empty();
	var faction_codes = app.data.cards.distinct('faction_code').sort();
	var neutral_index = faction_codes.indexOf('basic');
	faction_codes.splice(neutral_index, 1);
	faction_codes.push('basic');

	faction_codes.forEach(function(faction_code) {
		// ajout du filtre pour campaign
		if (faction_code == "hero" || faction_code == "encounter" || faction_code == "campaign" ){
			return;
		}
		var example = app.data.cards.find({"faction_code": faction_code})[0];
		var label = $('<label class="btn btn-default btn-sm" data-code="'
				+ faction_code + '" title="'+example.faction_name+'"><input type="checkbox" name="' + faction_code
				+ '"><span class="icon-' + faction_code + '"></span> ' + example.faction_name + '</label>');
		label.tooltip({container: 'body'});
		$('[data-filter=faction_code]').append(label);
	});


	$('[data-filter=faction_code]').button();

}

/**
 * builds the type selector
 * @memberOf ui
 */
ui.build_type_selector = function build_type_selector() {
	$('[data-filter=type_code]').empty();
	['ally', 'event', 'player_side_scheme', 'resource', 'support', 'upgrade'].forEach(function(type_code) {
		var example = app.data.cards.find({"type_code": type_code})[0];
		// not all card types might exist
		if (example) {
			var displayedTypeName = example.type_name;
			if (type_code == 'player_side_scheme') {
				// Player Side Scheme is too long to fit in the button without overflowing
				displayedTypeName = 'Side Scheme';
			}
			var label = $('<label class="btn btn-default btn-sm" data-code="'
					+ type_code + '" title="'+example.type_name+'"><input type="checkbox" name="' + type_code
					+ '"><span class="icon-' + type_code + '"></span>' + displayedTypeName + '</label>');
			label.tooltip({container: 'body'});
			$('[data-filter=type_code]').append(label);
		}
	});
	$('[data-filter=type_code]').button();

}



/**
 * builds the pack selector
 * @memberOf ui
 */
ui.build_pack_selector = function build_pack_selector() {
    $('[data-filter=pack_code]').empty();

    // parse pack owner string
    var collection = {};
    var no_collection = true;
    if (app.user.data && app.user.data.owned_packs) {
        var packs = app.user.data.owned_packs.split(',');
        _.forEach(packs, function(str) {
            collection[str] = 1;
            no_collection = false;
        });
    }

    app.data.packs.find({
        name: {
            '$exists': true
        }
    }, {
        $orderBy: {
            cycle_position: 1,
            position: 1
        }
    }).forEach(function(record) {
        // Ajout du filtre : ne pas afficher le pack si visibility === "false" et que l'utilisateur n'est pas donateur
        if (record.visibility === "false" && (!app.user.donation || app.user.donation === "0")) {
            return;
        }

        // checked or unchecked ? checked by default
        var checked = false;
        if (collection[record.id]){
            checked = true;
        }

        if (no_collection && localStorage && localStorage.getItem('set_code_' + record.code) === "true"){
            checked = true;
        } else if (no_collection && localStorage && localStorage.getItem('set_code_' + record.code) === "false"){
            checked = false;
        } else if (no_collection && record.available !== ""){
            checked = true;
        }

        var cards = app.data.cards.find({
            pack_code: record.code,
            indeck: {
                '$gt': 0
            },
            hidden: {
                '$eq': false
            }
        });
        if(cards.length) {
            checked = true;
        }

        $('<li><a href="#"><label><input type="checkbox" name="' + record.code + '"' + (checked ? ' checked="checked"' : '') + '>' + record.name + '</label></a></li>').appendTo('[data-filter=pack_code]');
        // special case for core set 2
        if (record.code == "core"){
            if (collection[record.id+"-2"]){
                checked = true;
            }else {
                checked = false;
            }

            if (no_collection && localStorage && localStorage.getItem('set_code_' + record.code+"-2") === "true"){
                checked = true;
            } else if (no_collection && localStorage && localStorage.getItem('set_code_' + record.code+"-2") === "false"){
                checked = false;
            }

            var cards = app.data.cards.find({
                pack_code: record.code,
                indeck: {
                    '$gt': 1
                }
            });
            if(cards.length) checked = true;

            $('<li><a href="#"><label><input type="checkbox" name="' + record.code + '-2"' + (checked ? ' checked="checked"' : '') + '>Second ' + record.name + '</label></a></li>').appendTo('[data-filter=pack_code]');
        }
    });
}


/**
 * @memberOf ui
 */
ui.init_selectors = function init_selectors() {
	$('[data-filter=faction_code]').find('input[name=basic]').prop("checked", true).parent().addClass('active');
	var hero = app.data.cards.findById(app.deck.get_hero_code());
	if (hero.faction_code){
		$('[data-filter=faction_code]').find('input[name='+hero.faction_code+']').prop("checked", true).parent().addClass('active');
	}

	if (app.deck.meta && app.deck.meta.faction_selected){
		$('[data-filter=faction_selector]').val(app.deck.meta.faction_selected);
	}

}

function uncheck_all_others() {
	$(this).closest('[data-filter]').find("input[type=checkbox]").prop("checked",false);
	$(this).children('input[type=checkbox]').prop("checked", true).trigger('change');
}

function check_all_others() {
	$(this).closest('[data-filter]').find("input[type=checkbox]").prop("checked",true);
	$(this).children('input[type=checkbox]').prop("checked", false);
}

function uncheck_all_active() {
	$(this).closest('[data-filter]').find("label.active").button('toggle');
}

function check_all_inactive() {
	$(this).closest('[data-filter]').find("label:not(.active)").button('toggle');
}

/**
 * @memberOf ui
 * @param event
 */
ui.on_click_filter = function on_click_filter(event) {
	var dropdown = $(this).closest('ul').hasClass('dropdown-menu');
	if (dropdown) {
		if (event.shiftKey) {
			if (!event.altKey) {
				uncheck_all_others.call(this);
			} else {
				check_all_others.call(this);
			}
		}
		event.stopPropagation();
	} else {
		if (!event.shiftKey && Config['buttons-behavior'] === 'exclusive' || event.shiftKey && Config['buttons-behavior'] === 'cumulative') {
			if (!event.altKey) {
				uncheck_all_active.call(this);
			} else {
				check_all_inactive.call(this);
			}
		}
	}
}

/**
 * @memberOf ui
 * @param event
 */
ui.on_input_smartfilter = function on_input_smartfilter(event) {
	var q = $(this).val();
	if(q.match(/^\w+[:<>!]/)) app.smart_filter.update(q);
	else app.smart_filter.update('');
	ui.refresh_list();
}
/**
 * @memberOf ui
 * @param event
 */
ui.on_input_smartfilter2 = function on_input_smartfilter2(event) {
	var q = $(this).val();
	if(q.match(/^\w[:<>!]/)) app.smart_filter2.update(q);
	else app.smart_filter2.update('');

}

/**
 * @memberOf ui
 * @param event
 */
ui.on_submit_form = function on_submit_form(event) {
	var deck_json = app.deck.get_json();
	var ignored_json = app.deck.get_ignored_json();
	var meta_json = app.deck.get_meta_json();
	$('input[name=content]').val(deck_json);
	$('input[name=ignored]').val(ignored_json);
	$('input[name=meta]').val(meta_json);
	$('input[name=description]').val($('textarea[name=description_]').val());
	$('input[name=tags]').val($('input[name=tags_]').val());
}

/**
 * @memberOf ui
 * @param event
 */
ui.on_config_change = function on_config_change(event) {
	var name = $(this).attr('name');
	var type = $(this).prop('type');
	//console.log(name, type);
	switch(type) {
	case 'radio':
		var value = $(this).val();
		if(!isNaN(parseInt(value, 10))) value = parseInt(value, 10);
		Config[name] = value;
		break;
	case 'checkbox':
		Config[name] = $(this).prop('checked');
		break;
	}
	ui.write_config_to_storage();
	switch(name) {
		case 'buttons-behavior':
		break;
		case 'display-column':
		ui.update_list_template();
		ui.refresh_lists();
		break;
		case 'show-suggestions':
		ui.toggle_suggestions();
		ui.refresh_lists();
		break;
		default:
		ui.refresh_lists();
	}
}


/**
 * @memberOf ui
 * @param event
 */
ui.on_core_change = function on_core_change(event) {
	var name = $(this).attr('name');
	var type = $(this).prop('type');
	if (localStorage) {
		localStorage.setItem('set_code_' + name, $(this).is(":checked")  );
	}
	switch(name) {
		case 'core':
		case 'core-2':
		ui.set_max_qty();
		ui.reset_list();
		break;
		default:
		ui.refresh_lists();
	}
}



ui.toggle_suggestions = function toggle_suggestions() {
	app.suggestions.number = Config['show-suggestions'];
	app.suggestions.show();
}

/**
 * @memberOf ui
 * @param event
 */
ui.on_table_sort_click = function on_table_sort_click(event) {
	event.preventDefault();
	var new_sort = $(this).data('sort');
	if (SortKey == new_sort) {
		SortOrder *= -1;
	} else {
		SortKey = new_sort;
		SortOrder = 1;
	}
	ui.refresh_list();
	ui.update_sort_caret();
}

ui.chaos = function() {

	if (!window.confirm("This will replace your deck with an randomly generated deck, are you sure?")){
		return;
	}

	var counter = 0;
	var	filters = ui.get_filters("potato");
	var query = app.smart_filter.get_query(filters);

	var cards = app.data.cards.find(query);
	var valid_cards = [];

	var dupes_hash = {};
	cards.forEach(function (card) {
		if (card.faction_code != "hero"){
			card.indeck = 0;
			app.deck.set_card_copies(card.code, card.indeck);
			if (app.deck.can_include_card(card)){
				if (card.duplicate_of_code) {
					// if the parent card is included, use that over any other
					var dupe = app.data.cards.findById(card.duplicate_of_code);
					if (dupe && ui.in_selected_packs(dupe, filters)) {
						return;
					}

					// otherwise check the list of duplicates and find the first one that is included
					if (dupe && dupe.duplicated_by && dupe.duplicated_by.length > 0) {
						var duped = [];
						dupe.duplicated_by.forEach(function (another_id) {
							var another_dupe = app.data.cards.findById(another_id);
							if (another_dupe && ui.in_selected_packs(another_dupe, filters)) {
								duped.push(another_dupe);
							}
						});
						if (duped && duped.length > 0) {
							if (duped[0] && duped[0].code != card.code) {
								return;
							}
						}
					}
				}
				// this card has a duplicate. set the quantity to whichever thing has the highest
				if (card.duplicated_by && card.duplicated_by.length > 0) {
					card.duplicated_by.forEach(function (copyId) {
						var dupe = app.data.cards.findById(copyId);
						if (dupe && ui.in_selected_packs(dupe, filters)) {
							if (dupe.maxqty > card.maxqty) {
								card.maxqty = dupe.maxqty;
							}
						}
					})
				}
				valid_cards.push(card);
			}
		}
	});
	app.deck.reset_limit_count();

	var size = valid_cards.length;
	var deck_size = 25;
	if (size >= deck_size){
		while (counter < deck_size){
			var random_id = Math.floor(Math.random() * size)
			var random_card = valid_cards[random_id];
			if (random_card.indeck < random_card.deck_limit){
				if (app.deck.can_include_card(random_card, true, true)){
					if (random_card && random_card.faction_code != "basic") {
						app.deck.meta.aspect = random_card.faction_code;
					}
					random_card.indeck++;
					//console.log(random_card.name, random_card.indeck, counter);
					counter++;
					//console.log(random_card.name, random_card.indeck, counter);
				}
			}
		}
	}

	valid_cards.forEach(function(card){
		app.deck.set_card_copies(card.code, card.indeck);
	})

	ui.on_deck_modified();
};



/**
 * @memberOf ui
 * @param event
 */
ui.on_list_quantity_change = function on_list_quantity_change(event) {
	var row = $(this).closest('.card-container');
	var code = row.data('code');
	var quantity = parseInt($(this).val(), 10);
//	row[quantity ? "addClass" : "removeClass"]('in-deck');
	ui.on_quantity_change(code, quantity);
}
ui.on_suggestion_quantity_change = function on_suggestion_quantity_change(event) {
	var row = $(event.target).closest('.card-container');
	var code = row.data('code');
	var quantity = parseInt($(event.target).val(), 10);
//	row[quantity ? "addClass" : "removeClass"]('in-deck');
	ui.on_quantity_change(code, quantity);
}

/**
 * @memberOf ui
 * @param event
 */
ui.on_modal_quantity_change = function on_modal_quantity_change(event) {
	var modal = $('#cardModal');
	var code =  modal.data('code');
	var quantity = parseInt($(this).val(), 10);
	modal.modal('hide');
	if ($(this).attr("name") == "ignoreqty"){
		ui.on_ignore_quantity_change(code, quantity);
	} else {
		ui.on_quantity_change(code, quantity);
	}

	setTimeout(function () {
		$('#filter-text').typeahead('val', '').focus();
	}, 100);
}

ui.refresh_row = function refresh_row(card_code, quantity) {
	// for each set of divs (1, 2, 3 columns)
	CardDivs.forEach(function(rows) {
		var row = rows[card_code];
		if(!row) return;

		// rows[card_code] is the card row of our card
		// for each "quantity switch" on that row
		row.find('input[name="qty-' + card_code + '"]').each(function(i, element) {
			// if that switch is NOT the one with the new quantity, uncheck it
			// else, check it
			if($(element).val() != quantity) {
				$(element).prop('checked', false).closest('label').removeClass('active');
			} else {
				$(element).prop('checked', true).closest('label').addClass('active');
			}
		});
	});
}

/**
 * @memberOf ui
 */
ui.on_quantity_change = function on_quantity_change(card_code, quantity) {
	// if no aspect is selected, select one when a card is first added
	if (!app.deck.meta || !app.deck.meta.aspect) {
		var card = app.data.cards.findById(card_code);
		if (card && card.faction_code != "basic") {
			app.deck.meta.aspect = card.faction_code;
		}
	} else {
		if (app.deck.requirements && app.deck.requirements.aspects && !app.deck.meta.aspect2) {
			var card = app.data.cards.findById(card_code);
			if (card && card.faction_code != "basic" && app.deck.meta.aspect != card.faction_code) {
				app.deck.meta.aspect2 = card.faction_code;
			}
		}
	}
	var update_all = app.deck.set_card_copies(card_code, quantity);
	ui.refresh_deck();
	app.suggestions.compute();
	if(update_all) {
		ui.refresh_lists();
	}
	else {
		ui.refresh_row(card_code, quantity);
	}
	app.deck_history.all_changes();
}
ui.on_ignore_quantity_change = function on_ignore_quantity_change(card_code, quantity) {
	var update_all = app.deck.set_card_ignores(card_code, quantity);
	ui.refresh_deck();
	app.deck_history.all_changes();
}

/**
 * sets up event handlers ; dataloaded not fired yet
 * @memberOf ui
 */
ui.setup_event_handlers = function setup_event_handlers() {

	$('#global_filters [data-filter]').on({
		click : ui.on_click_filter
	}, 'label');

	$('#build_filters [data-filter]').on({
		change : ui.refresh_list,
		click : ui.on_click_filter
	}, 'label');


	$('#build_filters [data-filter=faction_selector]').on({
		change : function(event){
			app.deck.meta.faction_selected = event.target.value;
			ui.refresh_deck();
			ui.refresh_lists();
		}
	});

	$('#filter-text').on('input', ui.on_input_smartfilter);
	$('#filter-text-personal').on('input', ui.on_input_smartfilter2);

	$('#save_form').on('submit', ui.on_submit_form);

	$('#btn-save-as-copy').on('click', function(event) {
		$('#deck-save-as-copy').val(1);
	});

	$('#btn-cancel-edits').on('click', function(event) {
		var unsaved_edits = app.deck_history.get_unsaved_edits();
		if(unsaved_edits.length) {
			var confirmation = confirm("This operation will revert the changes made to the deck since "+unsaved_edits[0].date_creation.calendar()+". The last "+(unsaved_edits.length > 1 ? unsaved_edits.length+" edits" : "edit")+" will be lost. Do you confirm?");
			if(!confirmation) return false;
		}
		else {
			if(app.deck_history.is_changed_since_last_autosave()) {
				var confirmation = confirm("This operation will revert the changes made to the deck. Do you confirm?");
				if(!confirmation) return false;
			}
		}
		$('#deck-cancel-edits').val(1);
	});

	$('#config-options').on('change', 'input', ui.on_config_change);
	$('#global_filters [data-filter=pack_code]').on('change', 'input', ui.on_core_change);

	$('#collection').on('change', 'input[type=radio]', ui.on_list_quantity_change);
	$('#special-collection').on('change', 'input[type=radio]', ui.on_list_quantity_change);

	$('#global_filters').on('click', '#chaos', ui.chaos);


	$('#cardModal').on('keypress', function(event) {
		var num = parseInt(event.which, 10) - 48;
		$('#cardModal .modal-qty input[type=radio][value=' + num + ']').trigger('change');
	});
	$('#cardModal').on('change', 'input[type=radio]', ui.on_modal_quantity_change);

	$('thead').on('click', 'a[data-sort]', ui.on_table_sort_click);

}

ui.in_selected_packs = function in_selected_packs(card, filters) {
	var found = false;
	if (card && filters && filters.pack_code && filters.pack_code['$in']) {
		filters.pack_code['$in'].forEach(function(pack_code) {
			if (pack_code == card.pack_code) {
				found = true;
			}
		})
	}
	return found;
}

/**
 * returns the current card filters as an array
 * @memberOf ui
 */
ui.get_filters = function get_filters(prefix) {
	var filters = {};
	var target = "#build_filters [data-filter], #global_filters [data-filter]";
	if (prefix){
		target = "#"+prefix+"_filters [data-filter], #global_filters [data-filter]";
	}
	$(target).each(
		function(index, div) {
			var column_name = $(div).data('filter');
			var arr = [];
			$(div).find("input[type=checkbox]").each(
				function(index, elt) {
					if($(elt).prop('checked')) arr.push($(elt).attr('name'));
				}
			);
			if(arr.length) {
				// check both faction codes
				if (column_name == "faction_code"){
					filters['$or'] = [
						{"faction_code": { '$in': arr }},
						{"faction2_code": { '$in': arr }}
					];
				} else {
					filters[column_name] = {
						'$in': arr
					};
				}
			}
		}
	);

	filters['deck_limit'] = {};
	filters['deck_limit']['$exists'] = true;
	//console.log(filters);
	return filters;
}


/**
 * updates internal variables when display columns change
 * @memberOf ui
 */
ui.update_list_template = function update_list_template() {
	switch (Config['display-column']) {
	case 1:
		DisplayColumnsTpl = _.template(
			'<tr>'
				+ '<td><div class="btn-group" data-toggle="buttons"><%= radios %></div></td>'
				+ '<td><span class="fa fa-circle fg-<%= card.faction_code %>"></span> <a class="card card-tip <% if (typeof(card.faction2_code) !== "undefined") { %> fg-dual <% } %>" data-code="<%= card.code %>" href="<%= url %>" data-target="#cardModal" data-remote="false" data-toggle="modal">'
				+ '<%= card.name %></a>'
				+ '<% if (card.exceptional) { %> <span class="icon-eldersign" style="color:orange;" title="Exceptional. Double xp cost and limit one per deck."></span> <% } %>'
				+ '</td>'
				+ '<td class="resources"><%= resources %></td>'
				+ '<td class="cost"><%= card.cost %></td>'
				+ '<td class="type" style="text-align : left;"><span class="" title="<%= card.type_name %>"><%= card.type_name %></span> <% if (card.slot) { %> - <%= card.slot %> <% } %></td>'
				+ '<td class="faction"><span class="fg-<%= card.faction_code %>" title="<%= card.faction_name %>"><%= card.faction_name %></span></td>'
			+ '</tr>'
		);
		break;
	case 2:
		DisplayColumnsTpl = _.template(
			'<div class="col-sm-6">'
				+ '<div class="media">'
					+ '<div class="media-left"><img class="media-object"  onerror="this.onerror=null;this.src=\'/bundles/cards/<%= card.code %>.png\';" src="/bundles/cards/<%= card.code %>.jpg" alt="<%= card.name %>"></div>'
					+ '<div class="media-body">'
						+ '<h4 class="media-heading"><a class="card card-tip" data-code="<%= card.code %>" href="<%= url %>" data-target="#cardModal" data-remote="false" data-toggle="modal"><%= card.name %></a></h4>'
						+ '<div class="btn-group" data-toggle="buttons"><%= radios %></div>'
					+ '</div>'
				+ '</div>'
			+ '</div>'
		);
		break;
	case 3:
		DisplayColumnsTpl = _.template(
			'<div class="col-sm-4">'
				+ '<div class="media">'
					+ '<div class="media-left"><img class="media-object" onerror="this.onerror=null;this.src=\'/bundles/cards/<%= card.code %>.png\';" src="/bundles/cards/<%= card.code %>.jpg" alt="<%= card.name %>"></div>'
					+ '<div class="media-body">'
						+ '<h5 class="media-heading"><a class="card card-tip" data-code="<%= card.code %>" href="<%= url %>" data-target="#cardModal" data-remote="false" data-toggle="modal"><%= card.name %></a></h5>'
						+ '<div class="btn-group" data-toggle="buttons"><%= radios %></div>'
					+ '</div>'
				+ '</div>'
			+ '</div>'
		);
	}
}


/**
 * builds a row for the list of available cards
 * @memberOf ui
 */
ui.build_row = function build_row(card) {
    
	console.log('donation:', app.user.donation);

	// Filtre : ne pas afficher la carte si sa visibilité est false et que l'utilisateur n'est pas donateur
    if (card.visibility === "false" && app.user.donation === "0") { 
        return $('');
    }
    
    console.log('card:', card);

    var radios = '', radioTpl = _.template(
        '<label class="btn btn-xs btn-default <%= active %>"><input type="radio" name="qty-<%= card.code %>" value="<%= i %>"><%= i %></label>'
    );
    var $span = $('<span>');
    if(card.resource_physical && card.resource_physical > 0) {
        $span.append(app.format.resource(card.resource_physical, 'physical'));
    }
    if(card.resource_mental && card.resource_mental > 0) {
        $span.append(app.format.resource(card.resource_mental, 'mental'));
    }
    if(card.resource_energy && card.resource_energy > 0) {
        $span.append(app.format.resource(card.resource_energy, 'energy'));
    }
    if(card.resource_wild && card.resource_wild > 0) {
        $span.append(app.format.resource(card.resource_wild, 'wild'));
    }
    for (var i = 0; i <= card.maxqty; i++) {
        radios += radioTpl({
            i: i,
            active: (i == card.indeck ? ' active' : ''),
            card: card
        });
    }

    var html = DisplayColumnsTpl({
        radios: radios,
        resources: $span.html(),
        url: Routing.generate('cards_zoom', {card_code:card.code}),
        card: card
    });
    return $(html);
}

ui.reset_list = function reset_list() {
	CardDivs = [[],[],[]];
	ui.refresh_lists();
}


ui.refresh_lists = function refresh_lists() {
	ui.refresh_list();
}

/**
 * destroys and rebuilds the list of available cards
 * don't fire unless 250ms has passed since last invocation
 * @memberOf ui
 */
ui.refresh_list = _.debounce(function refresh_list() {
	$('#collection-table').empty();
	$('#collection-grid').empty();

	var counter = 0;
	var container = $('#collection-table');
	var	filters = ui.get_filters();
	var query = app.smart_filter.get_query(filters);
	query['card_set_code'] = {
		'$exists': false
	};
	var orderBy = {};
	SortKey.split('|').forEach(function (key) {
		orderBy[key] = SortOrder;
	});
	if(SortKey !== 'name') orderBy['name'] = 1;
	var cards = app.data.cards.find(query, {'$orderBy': orderBy});
	var divs = CardDivs[ Config['display-column'] - 1 ];
	var dupes_hash = {};
	cards.forEach(function (card) {
		if (Config['show-only-deck'] && !card.indeck) return;
		var unusable = !app.deck.can_include_card(card);
		if (!Config['show-unusable'] && unusable) return;

		// if this card is a duplicate of another
		if (card.duplicate_of_code) {
			// if the parent card is included, use that over any other
			var dupe = app.data.cards.findById(card.duplicate_of_code);
			if (dupe && ui.in_selected_packs(dupe, filters)) {
				return;
			}

			// otherwise check the list of duplicates and find the first one that is included
			if (dupe && dupe.duplicated_by && dupe.duplicated_by.length > 0) {
				var duped = [];
				dupe.duplicated_by.forEach(function (another_id) {
					var another_dupe = app.data.cards.findById(another_id);
					if (another_dupe && ui.in_selected_packs(another_dupe, filters)) {
						duped.push(another_dupe);
					}
				});
				if (duped && duped.length > 0) {
					if (duped[0] && duped[0].code != card.code) {
						return
					}
				}
			}
		}
		// this card has a duplicate. set the quantity to whichever thing has the highest
		if (card.duplicated_by && card.duplicated_by.length > 0) {
			card.duplicated_by.forEach(function (copyId) {
				var dupe = app.data.cards.findById(copyId);
				if (dupe && ui.in_selected_packs(dupe, filters)) {
					if (dupe.maxqty > card.maxqty) {
						card.maxqty = dupe.maxqty;
					}
				}
			})
		}

		var row = divs[card.code];
		if(!row) row = divs[card.code] = ui.build_row(card);

		row.data("code", card.code).addClass('card-container');

		row.find('input[name="qty-' + card.code + '"]').each(
			function(i, element) {
				if($(element).val() == card.indeck) {
					$(element).prop('checked', true).closest('label').addClass('active');
				} else {
					$(element).prop('checked', false).closest('label').removeClass('active');
				}
			}
		);

		if (unusable) {
			row.find('label').addClass("disabled").find('input[type=radio]').attr("disabled", true);
		}

		if (Config['display-column'] > 1 && (counter % Config['display-column'] === 0)) {
			container = $('<div class="row"></div>').appendTo($('#collection-grid'));
		}

		container.append(row);
		counter++;
	});
}, 250);


/**
 * called when the deck is modified and we don't know what has changed
 * @memberOf ui
 */
ui.on_deck_modified = function on_deck_modified() {
	ui.refresh_deck();
	ui.refresh_lists();
	//app.suggestions && app.suggestions.compute();
	//app.deck_history.all_changes();
}


/**
 * @memberOf ui
 */
ui.refresh_deck = function refresh_deck() {
	app.deck.display('#deck');
	app.draw_simulator && app.draw_simulator.reset();
	app.deck_charts && app.deck_charts.setup();
	//app.suggestions && app.suggestions.compute();
}

/**
 * @memberOf ui
 */
ui.setup_typeahead = function setup_typeahead() {

	function findMatches(q, cb) {
		if(q.match(/^\w:/)) return;
		var regexp = new RegExp(q, 'i');
		cb(app.data.cards.find({name: regexp}));
	}

	$('#filter-text').typeahead({
		hint: true,
		highlight: true,
		minLength: 2
	},{
		name : 'cardnames',
		displayKey: 'name',
		source: findMatches
	});

}

ui.update_sort_caret = function update_sort_caret() {
	var elt = $('[data-sort="'+SortKey+'"]');
	$(elt).closest('tr').find('th').removeClass('dropup').find('span.caret').remove();
	$(elt).after('<span class="caret"></span>').closest('th').addClass(SortOrder > 0 ? '' : 'dropup');
}

ui.init_filter_help = function init_filter_help() {
	$('#filter-text-button').popover({
		container: 'body',
		content: app.smart_filter.get_help(),
		html: true,
		placement: 'bottom',
		title: 'Smart filter syntax'
	});
}

ui.setup_dataupdate = function setup_dataupdate() {
	$('a.data-update').click(function (event) {
		$(document).on('data.app', function (event) {
			$('a.data-update').parent().text("Data refreshed. You can save or reload your deck.");
		});
		app.data.update();
		return false;
	})
}

/**
 * called when the DOM is loaded
 * @memberOf ui
 */
ui.on_dom_loaded = function on_dom_loaded() {
	ui.init_config_buttons();
	ui.init_filter_help();
	ui.update_sort_caret();
	ui.toggle_suggestions();
	ui.setup_event_handlers();
	app.textcomplete && app.textcomplete.setup('#description');
	app.markdown && app.markdown.setup('#description', '#description-preview')
	app.draw_simulator && app.draw_simulator.on_dom_loaded();
	app.card_modal && $('#filter-text').on('typeahead:selected typeahead:autocompleted', app.card_modal.typeahead);
};

/**
 * called when the app data is loaded
 * @memberOf ui
 */
ui.on_data_loaded = function on_data_loaded() {
	app.draw_simulator && app.draw_simulator.on_data_loaded();
};

/**
 * called when both the DOM and the data app have finished loading
 * @memberOf ui
 */
ui.on_all_loaded = function on_all_loaded() {
	ui.update_list_template();
	ui.build_faction_selector();
	ui.build_type_selector();
	ui.build_pack_selector();
	ui.init_selectors();
	// for now this needs to be done here
	ui.set_max_qty();
	ui.refresh_deck(); // now updates the deck changes and history too
	ui.refresh_lists(); // update the card selection lists
	ui.setup_typeahead();
	ui.setup_dataupdate();

	var hero = app.data.cards.findById(app.deck.get_hero_code());
	app.suggestions.query("sugg-"+hero.code);

};

ui.read_config_from_storage();

})(app.ui, jQuery);
