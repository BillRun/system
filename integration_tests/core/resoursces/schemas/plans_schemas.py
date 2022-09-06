from .revision_info_schema import REVISION_INFO_SCHEMA

PLANS_GET_SCHEMA = {
    "$schema": "http://json-schema.org/draft-04/schema#",
    "type": "object",
    "properties": {
        "status": {
            "type": "integer"
        },
        "next_page": {
            "type": "boolean"
        },
        "details": {
            "type": "array",
            "items": [
                {
                    "type": "object",
                    "properties": {
                        "_id": {
                            "type": "object",
                            "properties": {
                                "$id": {
                                    "type": "string"
                                }
                            },
                            "required": [
                                "$id"
                            ]
                        },
                        "from": {
                            "type": "string"
                        },
                        "to": {
                            "type": "string"
                        },
                        "name": {
                            "type": "string"
                        },
                        "price": {
                            "type": "array",
                            "items": [
                                {
                                    "type": "object",
                                    "properties": {
                                        "price": {
                                            "type": "integer"
                                        },
                                        "from": {
                                            "type": "integer"
                                        },
                                        "to": {
                                            "type": "string"
                                        }
                                    },
                                    "required": [
                                        "price",
                                        "from",
                                        "to"
                                    ]
                                }
                            ]
                        },
                        "description": {
                            "type": "string"
                        },
                        "recurrence": {
                            "type": "object",
                            "properties": {
                                "unit": {
                                    "type": "integer"
                                },
                                "frequency": {
                                    "type": "integer"
                                },
                                "periodicity": {
                                    "type": "string"
                                }
                            },
                            "required": [
                                "unit",
                                "frequency",
                                "periodicity"
                            ]
                        },
                        "upfront": {
                            "type": "boolean"
                        },
                        "connection_type": {
                            "type": "string"
                        },
                        "rates": {
                            "type": "array",
                            "items": {}
                        },
                        "charging_type": {
                            "type": "string"
                        },
                        "creation_time": {
                            "type": "string"
                        },
                        "revision_info": {
                            "type": "object",
                            "properties": REVISION_INFO_SCHEMA['properties'],
                            "required": [
                                "status",
                                "is_last",
                                "early_expiration",
                                "updatable",
                                "closeandnewable",
                                "movable",
                                "removable",
                                "movable_from",
                                "movable_to"
                            ]
                        }
                    },
                    "required": [
                        "_id",
                        "from",
                        "to",
                        "name",
                        "price",
                        "description",
                        "recurrence",
                        "upfront",
                        "connection_type",
                        "charging_type",
                        "creation_time",
                        "revision_info"
                    ]
                }
            ]
        }
    },
    "required": [
        "status",
        "next_page",
        "details"
    ]
}

PLANS_POST_SCHEMA = {
  "$schema": "http://json-schema.org/draft-04/schema#",
  "type": "object",
  "properties": {
    "status": {
      "type": "integer"
    },
    "details": {
      "type": "boolean"
    },
    "entity": {
      "type": "object",
      "properties": {
        "_id": {
          "type": "object",
          "properties": {
            "$id": {
              "type": "string"
            }
          },
          "required": [
            "$id"
          ]
        },
        "name": {
          "type": "string"
        },
        "price": {
          "type": "object",
          "properties": {
            "$in": {
              "type": "array",
              "items": [
                {
                  "type": "string"
                }
              ]
            }
          },
          "required": [
            "$in"
          ]
        },
        "recurrence": {
          "type": "object",
          "properties": {
            "$in": {
              "type": "array",
              "items": [
                {
                  "type": "string"
                }
              ]
            }
          },
          "required": [
            "$in"
          ]
        },
        "upfront": {
          "type": "boolean"
        },
        "connection_type": {
          "type": "string"
        },
        "from": {
          "type": "object",
          "properties": {
            "sec": {
              "type": "integer"
            },
            "usec": {
              "type": "integer"
            }
          },
          "required": [
            "sec",
            "usec"
          ]
        },
        "to": {
          "type": "object",
          "properties": {
            "sec": {
              "type": "integer"
            },
            "usec": {
              "type": "integer"
            }
          },
          "required": [
            "sec",
            "usec"
          ]
        },
        "creation_time": {
          "type": "object",
          "properties": {
            "sec": {
              "type": "integer"
            },
            "usec": {
              "type": "integer"
            }
          },
          "required": [
            "sec",
            "usec"
          ]
        }
      },
      "required": [
        "_id",
        "name",
        "price",
        "recurrence",
        "upfront",
        "connection_type",
        "from",
        "to",
        "creation_time"
      ]
    }
  },
  "required": [
    "status",
    "details",
    "entity"
  ]
}
