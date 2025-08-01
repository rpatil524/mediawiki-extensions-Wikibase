'use strict';

module.exports = {
	"Sitelinks": {
		"type": "object",
		"additionalProperties": {
			"$ref": "#/components/schemas/Sitelink"
		}
	},
	"PropertyValuePair": {
		"type": "object",
		"properties": {
			"property": {
				"type": "object",
				"properties": {
					"id": {
						"description": "The ID of the Property",
						"type": "string"
					},
					"data_type": {
						"description": "The data type of the Property",
						"type": [ "string", "null" ],
						"readOnly": true
					}
				}
			},
			"value": {
				"type": "object",
				"properties": {
					"content": {
						"description": "The value, if type == \"value\", otherwise omitted"
					},
					"type": {
						"description": "The value type",
						"type": "string",
						"enum": [ "value", "somevalue", "novalue" ]
					}
				}
			}
		}
	}
};
