
Ext.override(Ext.data.reader.Reader,
{
	 readRecords: function(data) {
		 	var data = this.callOverridden(arguments);
		 	
		 	if (data.success == false) {
		 		//Ext.Msg.alert("Error", data.message);
		 	}
		 	
		 	
		 	return data;
	 }

		});


Ext.override(Ext.data.Connection, {
	/**
	 * Inject session header. I haven't found a better way to do
	 * it :(
	 */
	setupHeaders: function (xhr, options, data, params) {
		var session;
		
		if (!options.headers) {
			options.headers = {};
		}
		
		if (PartDB2.getApplication() !== null) {
			session = PartDB2.getApplication().getSession();
			if (session !== null) {
				options.headers.session = session;
			}
		}
		
		var headers = this.callOverridden(arguments);
		
		return headers;
	}

});

Ext.tree.View.override({
	ensureVisible: function (record) {
		if (!record) { return; }
		
		if (record.parentNode) {
			record.parentNode.expand();
			this.ensureVisible(record.parentNode);
		}
	},
	scrollIntoView: function (record) {
		var node = this.getNode(record);
		//focusRow
		if (node) {
			console.log(this.getEl());
			node.scrollIntoView(this.getEl());
		}
	}
});