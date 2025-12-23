(function app_data(data, $) {

var force_update = false;

/**
 * loads the database from local
 * sets up a Promise on all data loading/updating
 * @memberOf data
 */
data.load = function load() {

	console.log('[DATA DEBUG] data.load() start');

	data.isLoaded = false;

	var fdb = new ForerunnerDB();
	data.db = fdb.db('marvelsdb');
	// seems that indexedDB is failing in chrome, so switching to localstorage for now
	data.db.persist.driver("IndexedDB");

	data.masters = {
		packs: data.db.collection('master_pack', {primaryKey:'code', changeTimestamp: true}),
		cards: data.db.collection('master_card', {primaryKey:'code', changeTimestamp: true}),
	};

	data.dfd = {
		packs: new $.Deferred(),
		cards: new $.Deferred(),
	};

	$.when(data.dfd.packs, data.dfd.cards).done(data.update_done).fail(data.update_fail);

	// load pack data
	data.masters.packs.load(function (err) {
		console.log('[DATA DEBUG] masters.packs.load callback, err=', err);
		if(err) {
			console.log('error when loading packs', err);
			force_update = true;
		}
		// loading cards
		data.masters.cards.load(function (err) {
			console.log('[DATA DEBUG] masters.cards.load callback, err=', err);
			if(err) {
				console.log('error when loading cards', err);
				force_update = true;
			}

			/*
			 * data has been fetched from local store
			 */

			/*
			 * if database is older than 10 days, we assume it's obsolete and delete it
			 */
			var age_of_database = new Date() - new Date(data.masters.cards.metaData().lastChange);
			if(age_of_database > 864000000) {
				console.log('database is older than 10 days => refresh it');
				force_update = true;
			}

			/*
			 * if database is empty, we will wait for the new data
			 */
			if(data.masters.packs.count() === 0 || data.masters.cards.count() === 0) {
				console.log('database is empty => load it', data.masters.packs.count(), data.masters.cards.count());
				force_update = true;
			}

			/*
			 * triggering event that data is loaded
			 */
			if(!force_update) {
				data.release();
			}

			/*
			 * then we ask the server if new data is available
			 */
			data.query();
		});
	});
}

/**
 * release the data for consumption by other modules
 * @memberOf data
 */
data.release = function release() {
	data.packs = data.db.collection('pack', {primaryKey:'code', changeTimestamp: false});
	// populate working packs collection from persisted master_pack data
	try {
		// copy persisted master_pack rows into the runtime `pack` collection
		// and ensure `environment` and `creator` are normalized/defaulted so UI can rely on them
		try {
			var _packs = data.masters.packs.find();
			// enrich packs: normalize existing env/creator, and if env missing try master cards
			if (Array.isArray(_packs)) {
				_packs.forEach(function(r){
					try {
						if (r && r.environment) {
							r.environment = String(r.environment).trim().toLowerCase();
						} else {
							// try find a sample card in persisted master cards to get pack_environment
							try {
								if (data.masters && data.masters.cards && typeof data.masters.cards.findOne === 'function') {
									var sample = data.masters.cards.findOne({ pack_code: r.code });
									if (sample && sample.pack_environment) {
										r.environment = String(sample.pack_environment).trim().toLowerCase();
									}
								}
							} catch(e) {}
						}
						if (r && r.creator) {
							r.creator = String(r.creator).trim().toLowerCase();
						}
					} catch(e) {}
				});
			}
			data.packs.setData(_packs);
		} catch (e) {
			// fallback: set data directly if anything goes wrong
			data.packs.setData(data.masters.packs.find());
		}
	} catch (e) {}
	data.cards = data.db.collection('card', {primaryKey:'code', changeTimestamp: false});
	data.cards.setData(data.masters.cards.find());

	data.isLoaded = true;

	$(document).trigger('data.app');
}

/**
 * triggers a forced update of the database
 * @memberOf data
 */
data.update = function update() {
	// Force an update regardless of local meta timestamps to ensure fresh data
	force_update = true;
	_.each(data.masters, function (collection) {
		try { collection.drop(); } catch(e) { console.warn('drop failed', e); }
	});
	// Also attempt to remove persisted DB for ForerunnerDB if available
	try {
		if (data.db && typeof data.db.drop === 'function') {
			data.db.drop();
		}
	} catch(e) {
		console.warn('db.drop failed', e);
	}
	data.load();
}

/**
 * queries the server to update data
 * @memberOf data
 */
data.query = function query() {
	console.log('[DATA DEBUG] query: requesting api_packs');
	$.ajax({
		url: Routing.generate('api_packs'),
		success: function(response, textStatus, jqXHR) {
			console.log('[DATA DEBUG] api_packs success, items=', (Array.isArray(response) ? response.length : 'unknown'));
			data.parse_packs(response, textStatus, jqXHR);
		},
		error: function (jqXHR, textStatus, errorThrown) {
			console.log('[DATA DEBUG] error when requesting packs', errorThrown, jqXHR.status);
			data.dfd.packs.reject(false);
		}
	});

	console.log('[DATA DEBUG] query: requesting api_cards');
	$.ajax({
		url: Routing.generate('api_cards')+"?encounter=1",
		success: function(response, textStatus, jqXHR) {
			console.log('[DATA DEBUG] api_cards success, items=', (Array.isArray(response) ? response.length : 'unknown'));
			data.parse_cards(response, textStatus, jqXHR);
		},
		error: function (jqXHR, textStatus, errorThrown) {
			console.log('[DATA DEBUG] error when requesting cards', errorThrown, jqXHR.status);
			data.dfd.cards.reject(false);
		}
	});
};

/**
 * called if all operations (load+update) succeed (resolve)
 * deferred returns true if data has been updated
 * @memberOf data
 */
data.update_done = function update_done(packs_updated, cards_updated) {
	if(force_update) {
		data.release();
		return;
	}

	// If server returned updated collections, refresh the runtime collections
	if (packs_updated === true) {
		try {
			// enrich runtime packs as in data.release
			var _packs2 = data.masters.packs.find();
			if (Array.isArray(_packs2)) {
				_packs2.forEach(function(r){
					try {
						if (r && r.environment) {
							r.environment = String(r.environment).trim().toLowerCase();
						} else {
							try {
								if (data.masters && data.masters.cards && typeof data.masters.cards.findOne === 'function') {
									var sample2 = data.masters.cards.findOne({ pack_code: r.code });
									if (sample2 && sample2.pack_environment) {
										r.environment = String(sample2.pack_environment).trim().toLowerCase();
									}
								}
							} catch(e) {}
						}
						if (r && r.creator) {
							r.creator = String(r.creator).trim().toLowerCase();
						}
					} catch(e) {}
				});
			}
			data.packs.setData(_packs2);
		} catch (e) {}
	}
	if (cards_updated === true) {
		try { data.cards.setData(data.masters.cards.find()); } catch (e) {}
	}
	if(packs_updated === true || cards_updated === true) {
		// notify modules that data has been updated
		try { $(document).trigger('data.app'); } catch (e) {}
		/*
		 * we display a message informing the user that they can reload their page to use the updated data
		 * except if we are on the front page, because data is not essential on the front page
		 */
		if($('.site-title').size() === 0) {
			var message = "A new version of the data is available. Click <a href=\"javascript:window.location.reload(true)\">here</a> to reload your page.";
			app.ui.insert_alert_message('warning', message);
		}
	}
};

/**
 * called if an operation (load+update) fails (reject)
 * deferred returns true if data has been loaded
 * @memberOf data
 */
data.update_fail = function update_fail(packs_loaded, cards_loaded) {
	if(packs_loaded === false || cards_loaded === false) {
		var message = "Unable to load the data. Click <a href=\"javascript:window.location.reload(true)\">here</a> to reload your page.";
		app.ui.insert_alert_message('danger', message);
	} else {
		/*
		 * since data hasn't been persisted, we will have to do the query next time as well
		 * -- not much we can do about it
		 * but since data has been loaded, we call the promise
		 */
		data.release();
	}
};

/**
 * updates the database if necessary, from fetched data
 * @memberOf data
 */
data.update_collection = function update_collection(data, collection, lastModifiedData, deferred) {
	var lastChangeDatabase = new Date(collection.metaData().lastChange)
	var isCollectionUpdated = false;
		console.log('[DATA DEBUG] update_collection for', collection.name(), 'lastChangeDatabase=', lastChangeDatabase, 'lastModifiedData=', lastModifiedData, 'incoming_count=', (Array.isArray(data) ? data.length : 'unknown'));

	/*
	 * if we decided to force the update,
	 * or if the database is fresh,
	 * or if the database is older than the data,
	 * then we update the database
	 */
	if(force_update || !lastChangeDatabase || lastChangeDatabase < lastModifiedData) {
		console.log('data is newer than database or update forced => update the database')
		// Normalize pack environment/creator when updating packs so client-side code can rely on them
		try {
			if (collection && typeof collection.name === 'function' && collection.name() && collection.name().toLowerCase().indexOf('pack') !== -1) {
				if (Array.isArray(data)) {
					console.log('[DATA DEBUG] Normalizing pack environment for', data.length, 'items');
					data.forEach(function(r){
						try {
							if (r && r.environment) {
								r.environment = String(r.environment).trim().toLowerCase();
							}
							if (r && r.creator) {
								r.creator = String(r.creator).trim().toLowerCase();
							}
						} catch (e) {}
					});
				}
			}
		} catch (e) { console.warn('Normalization failed', e); }
		collection.setData(data);
		isCollectionUpdated = true;
	}

	collection.save(function (err) {
		if(err) {
			console.log('error when saving '+collection.name(), err, collection);
			deferred.reject(true)
		} else {
			deferred.resolve(isCollectionUpdated);
		}
	});
}

/**
 * handles the response to the ajax query for packs data
 * @memberOf data
 */
data.parse_packs = function parse_packs(response, textStatus, jqXHR) {
	try {
		if (window && window.console) {
			console.log('[DATA DEBUG] parse_packs: response sample', Array.isArray(response) ? response.slice(0,6) : response);
		}
	} catch (e) {}
	var lastModified = new Date(jqXHR.getResponseHeader('Last-Modified'));
	data.update_collection(response, data.masters.packs, lastModified, data.dfd.packs);
};

/**
 * handles the response to the ajax query for the cards data
 * @memberOf data
 */
data.parse_cards = function parse_cards(response, textStatus, jqXHR) {
	try {
		if (window && window.console) {
			console.log('[DATA DEBUG] parse_cards: response length', (Array.isArray(response) ? response.length : 'unknown'));
		}
	} catch (e) {}
	var lastModified = new Date(jqXHR.getResponseHeader('Last-Modified'));
	data.update_collection(response, data.masters.cards, lastModified, data.dfd.cards);
};

$(function() {
	data.load();
});

})(app.data = {}, jQuery);
